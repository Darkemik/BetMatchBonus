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
    SELECT id FROM Competitions WHERE api_id = ?
");

// Sport belső id keresése / létrehozása (api_id → Sports.id)
$stmtFindSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
$stmtInsertSport = $conn->prepare("INSERT INTO Sports (api_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");

// Meccs upsert
$stmtUpsertMatch = $conn->prepare("
    INSERT INTO Events (api_id, sport_id, competition_id, name, home_team_name, away_team_name, start_time, is_live, live_time, status_id, home_score, away_score)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      sport_id = VALUES(sport_id),
      competition_id = VALUES(competition_id),
      name = VALUES(name),
      home_team_name = VALUES(home_team_name),
      away_team_name = VALUES(away_team_name),
      start_time = VALUES(start_time),
      is_live = CASE WHEN VALUES(is_live) = 1 THEN 1 ELSE is_live END,
      live_time = CASE WHEN VALUES(is_live) = 1 THEN VALUES(live_time) ELSE live_time END,
      status_id = VALUES(status_id),
      home_score = CASE WHEN VALUES(is_live) = 1 THEN VALUES(home_score) ELSE home_score END,
      away_score = CASE WHEN VALUES(is_live) = 1 THEN VALUES(away_score) ELSE away_score END
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
    $statusId     = $isLive ? 2 : 1;

    $scoreArr  = $match['score'] ?? [];
    $homeScore = (is_array($scoreArr) && isset($scoreArr[0]) && is_numeric($scoreArr[0])) ? (int)$scoreArr[0] : null;
    $awayScore = (is_array($scoreArr) && isset($scoreArr[1]) && is_numeric($scoreArr[1])) ? (int)$scoreArr[1] : null;

    $teams = explode(' vs. ', $name);
    if (count($teams) < 2) {
        $teams = explode(' - ', $name);
    }
    $homeTeamName = trim($teams[0] ?? $name);
    $awayTeamName = trim($teams[1] ?? '');

    if ($isLive) $liveCount++;

    // Competition ID keresése
    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    // API sport id → belső Sports.id
    $stmtFindSport->bind_param("i", $sportFromApi);
    $stmtFindSport->execute();
    $resSport = $stmtFindSport->get_result();
    if ($rowSport = $resSport->fetch_assoc()) {
        $internalSportId = (int)$rowSport['id'];
    } else {
        $sportApiName = "Sport {$sportFromApi}";
        $stmtInsertSport->bind_param("is", $sportFromApi, $sportApiName);
        $stmtInsertSport->execute();
        $internalSportId = (int)$stmtInsertSport->insert_id;
    }

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

        $stmtSelectCountry = $conn->prepare("SELECT id FROM Countries WHERE code = ?");
        $stmtSelectCountry->bind_param("s", $countryCode);
        $stmtSelectCountry->execute();
        $countryResult = $stmtSelectCountry->get_result();
        $countryRow = $countryResult->fetch_assoc();
        $countryId = $countryRow ? (int)$countryRow['id'] : null;
        $stmtSelectCountry->close();

        $champName = "Ismeretlen bajnokság (ID: {$leagueId})";

        $stmtInsertComp = $conn->prepare("
            INSERT INTO Competitions (api_id, sport_id, country_id, name)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmtInsertComp->bind_param("iiis", $leagueId, $internalSportId, $countryId, $champName);
        $stmtInsertComp->execute();
        $stmtInsertComp->close();

        $stmtFindChamp->bind_param("i", $leagueId);
        $stmtFindChamp->execute();
        $resultChamp = $stmtFindChamp->get_result();
        $champRow = $resultChamp->fetch_assoc();

        if (!$champRow) continue;
    }

    $competitionId = (int)$champRow['id'];

    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startTimeMysql = $dt->format('Y-m-d H:i:s');

    $stmtUpsertMatch->bind_param(
        "iiissssisiii",
        $apiMatchId,
        $internalSportId,
        $competitionId,
        $name,
        $homeTeamName,
        $awayTeamName,
        $startTimeMysql,
        $isLive,
        $liveTime,
        $statusId,
        $homeScore,
        $awayScore
    );
    $stmtUpsertMatch->execute();
    $importedCount++;
}

$stmtFindChamp->close();
$stmtUpsertMatch->close();
$stmtFindSport->close();
$stmtInsertSport->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'total' => $importedCount,
    'live' => $liveCount
]);