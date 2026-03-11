<?php
require_once __DIR__ . "/connect.php";

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;

if ($eventId <= 0) {
    echo json_encode(['error' => 'Hiányzó vagy érvénytelen eventId']);
    exit;
}

// Meccs alap adatok az adatbázisból
$stmt = $conn->prepare("
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_utc,
        m.is_live,
        m.live_time,
        m.score,
        m.sport_id,
        c.name AS country_name,
        ch.name AS championship_name
    FROM Matches m
    JOIN Championships ch ON m.championship_id = ch.id
    JOIN Countries c ON ch.country_code = c.code
    WHERE m.api_id = ?
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();
$matchRow = $result->fetch_assoc();
$stmt->close();

if (!$matchRow) {
    echo json_encode(['error' => 'Meccs nem található']);
    exit;
}

// API hívás az odds/markets adatokért
$url = "http://localhost:5000/api/matches/" . $eventId;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(['error' => 'API hiba: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$apiData = json_decode($response, true);

// Összeállítjuk a választ
$result = [
    'match' => [
        'id' => $matchRow['api_id'],
        'name' => $matchRow['match_name'],
        'startUtc' => $matchRow['start_utc'],
        'isLive' => (bool)$matchRow['is_live'],
        'liveTime' => $matchRow['live_time'],
        'score' => $matchRow['score'],
        'sportId' => $matchRow['sport_id'],
        'country' => $matchRow['country_name'],
        'championship' => $matchRow['championship_name'],
    ],
    'markets' => []
];

// Ha van API adat, hozzáadjuk a markets-et
if (is_array($apiData)) {
    // Az API válasz közvetlenül tartalmazza a markets tömböt
    if (isset($apiData['markets'])) {
        $result['markets'] = $apiData['markets'];
        // homeTeam / awayTeam ha van
        if (isset($apiData['homeTeam'])) {
            $result['match']['homeTeam'] = $apiData['homeTeam'];
        }
        if (isset($apiData['awayTeam'])) {
            $result['match']['awayTeam'] = $apiData['awayTeam'];
        }
        if (isset($apiData['liveTime'])) {
            $result['match']['liveTime'] = $apiData['liveTime'];
        }
        if (isset($apiData['liveStatus'])) {
            $result['match']['liveStatus'] = $apiData['liveStatus'];
        }
        if (isset($apiData['score']) && is_array($apiData['score']) && count($apiData['score']) >= 2) {
            $result['match']['score'] = $apiData['score'][0] . ' - ' . $apiData['score'][1];
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
