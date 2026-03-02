<?php
require_once "connect.php";

$sportId = 67; // pl. Kosárlabda
$date = date('Y-m-d'); // mai nap

$url = "http://localhost:5000/api/matches/date?sportId={$sportId}&date={$date}";

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

// Championship keresése (api_id alapján)
$stmtFindChamp = $conn->prepare("
    SELECT id FROM Championships WHERE api_id = ?
");

// Meccs beszúrása / frissítése
$stmtUpsertMatch = $conn->prepare("
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

foreach ($data as $match) {
    $apiMatchId   = $match['id'];
    $sportFromApi = $match['sportId'];
    $leagueId     = $match['leagueId'];
    $name         = $match['name'];
    $startUtcStr  = $match['startDateUtc'];
    $isLive       = $match['isLive'] ? 1 : 0;
    $liveTime     = $match['liveTime'] ?? null;

    // Championship ID lekérése
    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    // Ha hiányzik a bajnokság → automatikusan létrehozzuk INT/International-lal
    if (!$champRow) {
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

        $champName = "Ismeretlen bajnokság (ID: {$leagueId})";

        $stmtInsertChamp = $conn->prepare("
            INSERT INTO Championships (api_id, sport_id, country_code, name)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmtInsertChamp->bind_param("iiss", $leagueId, $sportFromApi, $countryCode, $champName);
        $stmtInsertChamp->execute();
        $stmtInsertChamp->close();

        // újra lekérjük
        $stmtFindChamp->bind_param("i", $leagueId);
        $stmtFindChamp->execute();
        $resultChamp = $stmtFindChamp->get_result();
        $champRow = $resultChamp->fetch_assoc();

        if (!$champRow) {
            continue;
        }
    }

    $championshipId = (int)$champRow['id'];

    // startDateUtc → MySQL DATETIME (UTC)
    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('UTC'));
    $startUtcMysql = $dt->format('Y-m-d H:i:s');

    // upsert
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

// nem kötelező, de szép
$stmtFindChamp->close();
$stmtUpsertMatch->close();

echo "Napi meccsek importja kész.\n";