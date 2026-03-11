<?php
require_once __DIR__ . "/connect.php";

// score oszlop hozzáadása ha még nincs
$conn->query("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS score VARCHAR(20) DEFAULT NULL");

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
$conn->query("DELETE FROM Matches WHERE is_live = 1");

// Prepared statementek
$stmtFindChamp = $conn->prepare("
    SELECT id 
    FROM Championships 
    WHERE api_id = ?
");

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

    $scoreArr = $match['score'] ?? [];
    $scoreStr = (is_array($scoreArr) && count($scoreArr) >= 2)
        ? $scoreArr[0] . ' - ' . $scoreArr[1]
        : null;

    if (!$isLive) {
        continue;
    }

    // Championship ID kikeresése
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

        if (!$champRow) {
            continue;
        }
    }

    $championshipId = (int)$champRow['id'];

    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('CET'));
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