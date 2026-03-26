<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

/**
 * FONTOS JAVÍTÁSOK:
 * 1) Nem DATE(e.start_time)=today, hanem időablak (tegnap->holnap), hogy timezone miatt ne tűnjenek el meccsek.
 * 2) Kivettük a túl szigorú "vs." / " - " névellenőrzést, mert sok valid feed más formátumot ad.
 */

$from = (new DateTime('yesterday 00:00:00'))->format('Y-m-d H:i:s');
$to   = (new DateTime('tomorrow 23:59:59'))->format('Y-m-d H:i:s');

$mode = isset($_GET['mode']) && $_GET['mode'] === 'live' ? 'live' : 'all';

$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    146 => 'fa-futbol',
    147 => 'fa-basketball-ball',
    148 => 'fa-hockey-puck',
    77  => 'fa-table-tennis',
    76  => 'fa-running',
    90  => 'fa-hockey-puck',
    68  => 'fa-baseball-ball',
    69  => 'fa-football-ball',
    71  => 'fa-volleyball-ball',
    72  => 'fa-golf-ball',
    74  => 'fa-fist-raised',
    75  => 'fa-biking',
    79  => 'fa-skiing',
    80  => 'fa-snowflake',
    84  => 'fa-table-tennis',
    85  => 'fa-chess',
    109 => 'fa-volleyball-ball',
    110 => 'fa-futbol',
    138 => 'fa-running',
    151 => 'fa-trophy',
];

$sportNames = [
    66  => 'Labdarúgás',
    67  => 'Kosárlabda',
    78  => 'Darts',
    83  => 'Vízilabda',
    73  => 'Kézilabda',
    70  => 'Jégkorong',
    145 => 'E-sportok',
    146 => 'e-Labdarúgás',
    147 => 'e-Kosárlabda',
    148 => 'e-Jégkorong',
    77  => 'Pingpong',
    76  => 'Futsal',
    90  => 'Floorball',
    68  => 'Baseball',
    69  => 'Amerikai foci',
    71  => 'Röplabda',
    72  => 'Golf',
    74  => 'MMA',
    75  => 'Kerékpár',
    79  => 'Síelés',
    80  => 'Téli sport',
    84  => 'Badminton',
    85  => 'Sakk',
    109 => 'Strandröplabda',
    110 => 'Futsal',
    138 => 'Krikett',
    151 => 'Snooker',
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
WHERE e.start_time BETWEEN ? AND ?
  AND e.name IS NOT NULL
  AND TRIM(e.name) != ''
  AND e.start_time IS NOT NULL
  AND (c.name IS NULL OR (LOWER(TRIM(c.name)) != 'n/a' AND TRIM(c.name) != ''))
  AND LOWER(TRIM(comp.name)) != 'n/a'
  AND TRIM(comp.name) != ''
" . ($mode === 'live' ? "  AND e.is_live = 1\n" : "") . "ORDER BY s.api_id, c.name, comp.name, e.start_time
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'SQL hiba: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();

$sports = [];
$sportIndex = [];

while ($row = $res->fetch_assoc()) {
    $sportApiId  = (int)($row['sport_api_id'] ?? 0);
    $countryName = trim((string)($row['country_name'] ?? 'International'));
    $compName    = trim((string)($row['competition_name'] ?? ''));
    $matchName   = trim((string)($row['match_name'] ?? ''));

    if ($sportApiId <= 0 || $compName === '') {
        continue;
    }

    // Csak teljesen üres / "-" meccsnév szűrése
    if ($matchName === '' || $matchName === '-') {
        continue;
    }

    // SPORT
    if (!isset($sportIndex[$sportApiId])) {
        $sportIndex[$sportApiId] = count($sports);
        $sports[] = [
            'sport_api_id' => $sportApiId,
            'sport_name'   => $sportNames[$sportApiId] ?? (string)$row['sport_name'],
            'icon'         => $sportIcons[$sportApiId] ?? 'fa-trophy',
            'match_count'  => 0,
            'countries'    => []
        ];
    }
    $si = $sportIndex[$sportApiId];

    // COUNTRY
    $countryIdx = null;
    foreach ($sports[$si]['countries'] as $idx => $country) {
        if (($country['country_name'] ?? '') === $countryName) {
            $countryIdx = $idx;
            break;
        }
    }
    if ($countryIdx === null) {
        $sports[$si]['countries'][] = [
            'country_name' => $countryName !== '' ? $countryName : 'International',
            'competitions' => []
        ];
        $countryIdx = count($sports[$si]['countries']) - 1;
    }

    // COMPETITION
    $compIdx = null;
    foreach ($sports[$si]['countries'][$countryIdx]['competitions'] as $idx => $comp) {
        if (($comp['competition_name'] ?? '') === $compName) {
            $compIdx = $idx;
            break;
        }
    }
    if ($compIdx === null) {
        $sports[$si]['countries'][$countryIdx]['competitions'][] = [
            'competition_name' => $compName,
            'matches' => []
        ];
        $compIdx = count($sports[$si]['countries'][$countryIdx]['competitions']) - 1;
    }

    // MATCH
    $sports[$si]['countries'][$countryIdx]['competitions'][$compIdx]['matches'][] = [
        'api_id'     => (int)$row['match_api_id'],
        'name'       => (string)$row['match_name'],
        'start_time' => (string)$row['start_time'],
        'is_live'    => (int)$row['is_live'],
        'live_time'  => $row['live_time']
    ];

    $sports[$si]['match_count']++;
}

$stmt->close();

echo json_encode($sports, JSON_UNESCAPED_UNICODE);