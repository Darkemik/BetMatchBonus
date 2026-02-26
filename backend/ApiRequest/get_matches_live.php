<?php
require_once "connect.php";

// 0) A Matches táblában csak az aktuális élő focimeccsek legyenek
$mysqli->query("TRUNCATE TABLE Matches");

// 1) Csak foci (sportId = 66)
$sportId = 66;

// 2) API hívás – élő focimeccsek
$url = "http://localhost:5000/api/matches/live?sportId=" . $sportId;

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

// 3) Prepared statementek

// Bajnokság keresése api_id alapján
$stmtFindChamp = $mysqli->prepare("
    SELECT id 
    FROM Championships 
    WHERE api_id = ?
");

// Meccs beszúrása / frissítése
$stmtUpsertMatch = $mysqli->prepare("
    INSERT INTO Matches (api_id, sport_id, championship_id, name, start_utc, is_live, live_time)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        sport_id = VALUES(sport_id),
        championship_id = VALUES(championship_id),
        name = VALUES(name),
        start_utc = VALUES(start_utc),
        is_live = VALUES(is_live),
        live_time = VALUES(live_time)
");

// 4) Végigmegyünk az összes élő focimeccsen
foreach ($data as $match) {
    $apiMatchId   = $match['id'];          // BIGINT az adatbázisban
    $sportFromApi = $match['sportId'];     // elvileg 66
    $leagueId     = $match['leagueId'];    // Championship.api_id
    $name         = $match['name'];
    $startUtcStr  = $match['startDateUtc'];
    $isLive       = $match['isLive'] ? 1 : 0;
    $liveTime     = $match['liveTime'] ?? null;

    // biztos, ami biztos: csak élő
    if (!$isLive) {
        continue;
    }

    // 4/a) Championship ID kikeresése
    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    if (!$champRow) {
        // DEBUG: ideiglenesen kiírjuk, melyik liga hiányzik
        echo "Hiányzó championship az adatbázisban, leagueId = {$leagueId}<br>";
        continue;
    }

    $championshipId = (int)$champRow['id'];

    // 4/b) startDateUtc konvertálása MySQL DATETIME-ra (UTC-ben)
    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('UTC'));
    $startUtcMysql = $dt->format('Y-m-d H:i:s');

    // 4/c) Meccs beszúrása / frissítése
    $stmtUpsertMatch->bind_param(
        "iiissis",
        $apiMatchId,
        $sportFromApi,
        $championshipId,
        $name,
        $startUtcMysql,
        $isLive,
        $liveTime
    );
    $stmtUpsertMatch->execute();
}

echo "Élő focimeccsek frissítve.";