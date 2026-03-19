<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');

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

$sportNames = [
    66  => 'Labdarúgás',
    67  => 'Kosárlabda',
    78  => 'Darts',
    83  => 'Vízilabda',
    73  => 'Kézilabda',
    70  => 'Jégkorong',
    145 => 'eSport',
    77  => 'Pingpong'
];

$sql = "
SELECT 
    s.api_id AS sport_api_id,
    s.name AS sport_name,
    c.name AS country_name,
    comp.name AS competition_name,
    comp.id AS competition_id,
    e.api_id AS match_api_id,
    e.name AS match_name,
    e.start_time,
    e.is_live,
    e.live_time
FROM Events e
JOIN Sports s ON e.sport_id = s.id
JOIN Competitions comp ON e.competition_id = comp.id
LEFT JOIN Countries c ON comp.country_id = c.id
WHERE DATE(e.start_time) = ?
  AND e.name IS NOT NULL
  AND TRIM(e.name) != ''
  AND e.start_time IS NOT NULL
ORDER BY s.api_id, c.name, comp.name, e.start_time
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
$sportIndex = [];

while ($row = $res->fetch_assoc()) {
    $sportApiId = (int)$row['sport_api_id'];
    $countryName = $row['country_name'] ?? 'International';
    $compName = $row['competition_name'];
    $matchName = trim($row['match_name']);

    // Kihagyjuk az üres vagy érvénytelen nevű meccseket
    if ($matchName === '' || $matchName === '-') {
        continue;
    }

    // Kihagyjuk ahol nincs " - " (azaz nem érvényes hazai - vendég formátum)
    $parts = explode(' - ', $matchName, 2);
    if (count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
        continue;
    }

    if (!isset($sportIndex[$sportApiId])) {
        $sportIndex[$sportApiId] = count($sports);
        $sports[] = [
            'sport_api_id' => $sportApiId,
            'sport_name' => $sportNames[$sportApiId] ?? $row['sport_name'],
            'icon' => $sportIcons[$sportApiId] ?? 'fa-trophy',
            'match_count' => 0,
            'countries' => []
        ];
    }
    $si = $sportIndex[$sportApiId];

    // Find or create country
    $countryFound = false;
    foreach ($sports[$si]['countries'] as &$country) {
        if ($country['country_name'] === $countryName) {
            $countryFound = true;
            // Find or create competition
            $compFound = false;
            foreach ($country['competitions'] as &$comp) {
                if ($comp['competition_name'] === $compName) {
                    $compFound = true;
                    $comp['matches'][] = [
                        'api_id' => (int)$row['match_api_id'],
                        'name' => $row['match_name'],
                        'start_time' => $row['start_time'],
                        'is_live' => (int)$row['is_live'],
                        'live_time' => $row['live_time']
                    ];
                    break;
                }
            }
            unset($comp);
            if (!$compFound) {
                $country['competitions'][] = [
                    'competition_name' => $compName,
                    'matches' => [[
                        'api_id' => (int)$row['match_api_id'],
                        'name' => $row['match_name'],
                        'start_time' => $row['start_time'],
                        'is_live' => (int)$row['is_live'],
                        'live_time' => $row['live_time']
                    ]]
                ];
            }
            break;
        }
    }
    unset($country);

    if (!$countryFound) {
        $sports[$si]['countries'][] = [
            'country_name' => $countryName,
            'competitions' => [[
                'competition_name' => $compName,
                'matches' => [[
                    'api_id' => (int)$row['match_api_id'],
                    'name' => $row['match_name'],
                    'start_time' => $row['start_time'],
                    'is_live' => (int)$row['is_live'],
                    'live_time' => $row['live_time']
                ]]
            ]]
        ];
    }

    $sports[$si]['match_count']++;
}

$stmt->close();
echo json_encode($sports, JSON_UNESCAPED_UNICODE);