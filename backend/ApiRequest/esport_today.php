<?php
require_once __DIR__ . "/connect.php";

$sportId = 145; // eSport
$date = date('Y-m-d'); // mai nap

$url = "http://localhost:5000/api/matches/date?sportId={$sportId}&date={$date}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);

if ($response === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'API hiba: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
if (!is_array($data)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'API nem tömböt adott vissza']);
    exit;
}

// Championship keresése
$stmtFindChamp = $conn->prepare("
    SELECT id FROM Championships WHERE api_id = ?
");

// Meccs upsert
$stmtUpsertMatch = $conn->prepare("
    INSERT INTO Matches (api_id, sport_id, championship_id, name, start_utc, is_live, live_time, score)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      sport_id = VALUES(sport_id),
      championship_id = VALUES(championship_id),
      name = VALUES(name),
      start_utc = VALUES(start_utc),
      is_live = CASE WHEN VALUES(is_live) = 1 THEN 1 ELSE is_live END,
      live_time = CASE WHEN VALUES(is_live) = 1 THEN VALUES(live_time) ELSE live_time END,
      score = CASE WHEN VALUES(is_live) = 1 THEN VALUES(score) ELSE score END
");

$importedCount = 0;
$liveCount = 0;

foreach ($data as $match) {
    $apiMatchId   = $match['id'] ?? 0;
    $sportFromApi = $match['sportId'] ?? $sportId;
    $leagueId     = $match['leagueId'] ?? 0;
    $name         = $match['name'] ?? '';
    $startUtcStr  = $match['startDateUtc'] ?? '';
    $isLive       = !empty($match['isLive']) ? 1 : 0;
    $liveTime     = $match['liveTime'] ?? null;

    $scoreArr = $match['score'] ?? [];
    $scoreStr = (is_array($scoreArr) && count($scoreArr) >= 2)
        ? $scoreArr[0] . ' - ' . $scoreArr[1]
        : null;

    if ($isLive) $liveCount++;

    // Championship ID keresése
    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    if (!$champRow) {
        $countryCode = "INT";
        $countryName = "International";

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

        $stmtFindChamp->bind_param("i", $leagueId);
        $stmtFindChamp->execute();
        $resultChamp = $stmtFindChamp->get_result();
        $champRow = $resultChamp->fetch_assoc();

        if (!$champRow) continue;
    }

    $championshipId = (int)$champRow['id'];

    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startUtcMysql = $dt->format('Y-m-d H:i:s');

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
    $importedCount++;
}

$stmtFindChamp->close();
$stmtUpsertMatch->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'total' => $importedCount,
    'live' => $liveCount
]);