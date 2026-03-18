<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/connect.php";

$today = date('Y-m-d');

// Sport ikonok mapping
$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    77  => 'fa-table-tennis'
];

$sql = "
SELECT 
    s.id AS sport_id, s.api_id AS sport_api_id, s.name AS sport_name,
    comp.id AS comp_id, comp.name AS comp_name,
    c.name AS country_name,
    e.api_id AS event_api_id, e.name AS event_name, 
    e.start_time, e.is_live, e.live_time, e.home_score, e.away_score
FROM Sports s
LEFT JOIN Competitions comp ON comp.sport_id = s.id
LEFT JOIN Countries c ON comp.country_id = c.id
LEFT JOIN Events e ON e.competition_id = comp.id AND DATE(e.start_time) = ?
WHERE s.is_active = 1
ORDER BY s.name, c.name, comp.name, e.start_time
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'SQL hiba: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();

$sports = [];

while ($row = $res->fetch_assoc()) {
    $sportId = (int)$row['sport_id'];
    $sportApiId = (int)$row['sport_api_id'];

    if (!isset($sports[$sportId])) {
        $sports[$sportId] = [
            'id' => $sportId,
            'api_id' => $sportApiId,
            'name' => $row['sport_name'],
            'icon' => $sportIcons[$sportApiId] ?? 'fa-trophy',
            'competitions' => [],
            'match_count' => 0
        ];
    }

    $compId = $row['comp_id'];
    if ($compId && !isset($sports[$sportId]['competitions'][$compId])) {
        $sports[$sportId]['competitions'][$compId] = [
            'id' => (int)$compId,
            'name' => $row['comp_name'],
            'country' => $row['country_name'] ?? '',
            'events' => []
        ];
    }

    if ($compId && $row['event_name']) {
        $sports[$sportId]['competitions'][$compId]['events'][] = [
            'api_id' => (int)$row['event_api_id'],
            'name' => $row['event_name'],
            'start_time' => $row['start_time'],
            'is_live' => (bool)$row['is_live'],
            'live_time' => $row['live_time'],
            'home_score' => $row['home_score'],
            'away_score' => $row['away_score']
        ];
        $sports[$sportId]['match_count']++;
    }
}

$stmt->close();

// Tömb formátumra alakítás (competitions is)
$output = [];
foreach ($sports as $sport) {
    $sport['competitions'] = array_values($sport['competitions']);
    $output[] = $sport;
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);