<?php
require_once __DIR__ . "/connect.php";

// A mi 8 sportunk ID-ja
$ourSportIds = [66, 67, 78, 83, 73, 70, 145, 77];

$allMatches = [];

// Minden sporthoz külön API hívás
foreach ($ourSportIds as $sportId) {
    // Próbáljuk sportId paraméterrel
    $url = "http://localhost:5000/api/matches/live?sportId=" . $sportId;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        continue;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (is_array($data) && !empty($data)) {
        foreach ($data as $match) {
            $allMatches[] = $match;
        }
    }
}

// Ha sehonnan nem jött adat, megpróbáljuk paraméter nélkül is
if (empty($allMatches)) {
    $url = "http://localhost:5000/api/matches/live";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            $allMatches = $data;
        }
    }
    curl_close($ch);
}

if (empty($allMatches)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'No live matches from API']);
    exit;
}

// Duplikátumok szűrése (ha ugyanaz a meccs többször jön)
$uniqueMatches = [];
foreach ($allMatches as $match) {
    $id = $match['id'] ?? 0;
    if ($id > 0) {
        $uniqueMatches[$id] = $match;
    }
}
$allMatches = array_values($uniqueMatches);

// Csak MOST töröljük az élő meccseket, ha van friss adat
$conn->query("DELETE FROM Events WHERE is_live = 1");

// Prepared statementek
$stmtFindChamp = $conn->prepare("
    SELECT id 
    FROM Competitions 
    WHERE api_id = ?
");

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
        is_live = VALUES(is_live),
        live_time = VALUES(live_time),
        status_id = VALUES(status_id),
        home_score = VALUES(home_score),
        away_score = VALUES(away_score)
");

$importedCount = 0;
$sportCounts = [];

foreach ($allMatches as $match) {
    $apiMatchId   = $match['id'] ?? 0;
    $sportFromApi = $match['sportId'] ?? 0;
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

    if (!$isLive) {
        continue;
    }

    // Competition ID kikeresése
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
        $stmtInsertComp->bind_param("iiis", $leagueId, $sportFromApi, $countryId, $champName);
        $stmtInsertComp->execute();
        $stmtInsertComp->close();

        $stmtFindChamp->bind_param("i", $leagueId);
        $stmtFindChamp->execute();
        $resultChamp = $stmtFindChamp->get_result();
        $champRow = $resultChamp->fetch_assoc();

        if (!$champRow) {
            continue;
        }
    }

    $competitionId = (int)$champRow['id'];

    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('CET'));
    $startTimeMysql = $dt->format('Y-m-d H:i:s');

    $stmtUpsertMatch->bind_param(
        "iiissssisiii",
        $apiMatchId,
        $sportFromApi,
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
    if (!isset($sportCounts[$sportFromApi])) {
        $sportCounts[$sportFromApi] = 0;
    }
    $sportCounts[$sportFromApi]++;
}

$stmtFindChamp->close();
$stmtUpsertMatch->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'total' => $importedCount,
    'sports' => $sportCounts
]);