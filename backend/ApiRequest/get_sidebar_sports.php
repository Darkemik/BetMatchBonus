<?php
/**
 * GET_SIDEBAR_SPORTS.PHP — Sidebar hierarchia (CSAK DB-ből)
 * 
 * Query: ?mode=live (opcionális, csak élő meccseket szűri)
 * Output: JSON [ { sport_api_id, sport_name, icon, match_count, countries: [...] } ]
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$mode = isset($_GET['mode']) && $_GET['mode'] === 'live' ? 'live' : 'all';

// Élő módban a start_time a múltban van (a meccs már elkezdődött), ezért korábbi from kell
if ($mode === 'live') {
    $from = (new DateTime('-1 day', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
} else {
    $from = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}
$to = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

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
  AND e.api_id IS NOT NULL
  AND e.api_id > 0
  AND (e.status_id IS NULL OR e.status_id != 3)
  AND (c.name IS NULL OR (LOWER(TRIM(c.name)) != 'n/a' AND TRIM(c.name) != ''))
  AND LOWER(TRIM(comp.name)) != 'n/a'
  AND TRIM(comp.name) != ''
" . ($mode === 'live' ? "  AND e.is_live = 1\n  AND (e.live_time IS NULL OR LOWER(TRIM(e.live_time)) NOT IN ('nem kezdődött el', 'not started', '', 'unknown'))\n" : "") . "ORDER BY s.api_id, c.name, comp.name, e.start_time
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
            'sport_name'   => SPORT_NAMES[$sportApiId] ?? (string)$row['sport_name'],
            'icon'         => getSportIcon($sportApiId),
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

// Lejátszott/megtörtént meccsek száma sportáganként (utolsó 3 nap, UTC)
$finFrom = (new DateTime('-3 days 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$finNow  = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$finSql = "
SELECT s.api_id AS sport_api_id, COUNT(*) AS finished_count
FROM Events e
JOIN Sports s ON e.sport_id = s.id
JOIN Competitions comp ON e.competition_id = comp.id
LEFT JOIN Countries c ON comp.country_id = c.id
WHERE (
        e.status_id = 3
                OR (e.home_score IS NOT NULL AND e.away_score IS NOT NULL)
)
  AND e.start_time BETWEEN ? AND ?
  AND e.name IS NOT NULL AND TRIM(e.name) != '' AND e.name != '-'
  AND (c.name IS NULL OR (LOWER(TRIM(c.name)) != 'n/a' AND TRIM(c.name) != ''))
  AND LOWER(TRIM(comp.name)) != 'n/a' AND TRIM(comp.name) != ''
GROUP BY s.api_id
";
$finStmt = $conn->prepare($finSql);
if ($finStmt) {
        $finStmt->bind_param("ss", $finFrom, $finNow);
    $finStmt->execute();
    $finRes = $finStmt->get_result();
    $finCounts = [];
    while ($fr = $finRes->fetch_assoc()) {
        $finCounts[(int)$fr['sport_api_id']] = (int)$fr['finished_count'];
    }
    $finStmt->close();
    foreach ($sports as &$sp) {
        $sp['finished_count'] = $finCounts[$sp['sport_api_id']] ?? 0;
    }
    unset($sp);
}

echo json_encode($sports, JSON_UNESCAPED_UNICODE);