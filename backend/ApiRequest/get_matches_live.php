<?php
require_once "connect.php";

// score oszlop hozzáadása ha még nincs
$conn->query("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS score VARCHAR(20) DEFAULT NULL");

// Csak az élő meccseket töröljük, a napi (nem élő) meccsek maradnak
$conn->query("DELETE FROM Matches WHERE is_live = 1");

// 1) API hívás – ÖSSZES élő meccs, minden sport
$url = "http://localhost:5000/api/matches/live";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if ($response === false) {
    die("cURL hiba: " . curl_error($ch));
}
curl_close($ch);

$data = json_decode($response, true);
if (!is_array($data)) {
    die("API HIBA: nem tömb érkezett.");
}

// 2) Prepared statementek

// Bajnokság keresése api_id alapján
$stmtFindChamp = $conn->prepare("
    SELECT id 
    FROM Championships 
    WHERE api_id = ?
");

// Meccs beszúrása / frissítése
$stmtUpsertMatch = $conn->prepare("
    INSERT INTO Matches (api_id, sport_id, championship_id, name, start_utc, is_live, live_time, score)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        sport_id = VALUES(sport_id),
        championship_id = VALUES(championship_id),
        name = VALUES(name),
        start_utc = VALUES(start_utc),
        is_live = VALUES(is_live),
        live_time = VALUES(live_time),
        score = VALUES(score)
");

// 3) Végigmegyünk az összes élő meccsen (minden sport)
foreach ($data as $match) {
    $apiMatchId   = $match['id'];             // BIGINT az adatbázisban
    $sportFromApi = $match['sportId'];        // sport az API-ból (nem csak 66)
    $leagueId     = $match['leagueId'];       // Championship.api_id
    $name         = $match['name'];
    $startUtcStr  = $match['startDateUtc'];
    $isLive       = $match['isLive'] ? 1 : 0;
    $liveTime     = $match['liveTime'] ?? null;
    
    // Score kezelése
    $scoreArr = $match['score'] ?? [];
    $scoreStr = (!empty($scoreArr) && count($scoreArr) >= 2) 
        ? $scoreArr[0] . ' - ' . $scoreArr[1] 
        : null;

    // biztos, ami biztos: csak élő
    if (!$isLive) {
        continue;
    }

    // 3/a) Championship ID kikeresése
    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    // Ha nincs ilyen bajnokság az adatbázisban, automatikusan létrehozzuk
    if (!$champRow) {
        // Itt most egy default országot használunk, amíg nincs rendes adat az API-ból
        $countryCode = "INT";
        $countryName = "International";

        // Ország upsert
        $stmtCountry = $conn->prepare("
            INSERT INTO Countries (code, name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmtCountry->bind_param("ss", $countryCode, $countryName);
        $stmtCountry->execute();
        $stmtCountry->close();

        // Bajnokság név – amíg nincs rendes név az API-ban, egy alap név
        $champName = "Ismeretlen bajnokság (ID: {$leagueId})";

        $stmtInsertChamp = $conn->prepare("
            INSERT INTO Championships (api_id, sport_id, country_code, name)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmtInsertChamp->bind_param("iiss", $leagueId, $sportFromApi, $countryCode, $champName);
        $stmtInsertChamp->execute();
        $stmtInsertChamp->close();

        // Újra megpróbáljuk lekérni az ID-t
        $stmtFindChamp->bind_param("i", $leagueId);
        $stmtFindChamp->execute();
        $resultChamp = $stmtFindChamp->get_result();
        $champRow = $resultChamp->fetch_assoc();

        // Ha még így sincs, akkor lépjünk tovább
        if (!$champRow) {
            continue;
        }
    }

    $championshipId = (int)$champRow['id'];

    // 3/b) startDateUtc konvertálása MySQL DATETIME-ra (UTC-ben)
    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('CET')); // Magyar időzóna
    $startUtcMysql = $dt->format('Y-m-d H:i:s');

    // 3/c) Meccs beszúrása / frissítése
    $stmtUpsertMatch->bind_param(
        "iiississ",
        $apiMatchId,
        $sportFromApi,
        $championshipId,
        $name,
        $startUtcMysql,
        $isLive,
        $liveTime,
        $scoreStr
    );
    $stmtUpsertMatch->execute();
}

// statementek lezárása
$stmtFindChamp->close();
$stmtUpsertMatch->close();

echo "Összes élő meccs frissítve (minden sportág).";