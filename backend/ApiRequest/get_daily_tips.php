<?php
/**
 * GET_DAILY_TIPS.PHP — Napi népszerű fogadási tippek
 * 
 * Determinisztikusan kiválaszt 4-6 meccset a főbb bajnokságokból,
 * mindegyikhez lekéri az odds-ot az API-ból, és egy-egy tippet ad.
 * A tippek minden nap változnak (dátum-alapú hash).
 * 
 * Output: JSON [ { eventId, homeTeam, awayTeam, league, pick, market, odds, startTime }, ... ]
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');

// Közelgő meccsek a főbb bajnokságokból (3 napon belül)
$from = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$to   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$priorityOrder = str_replace('comp.', 'ch.', LEAGUE_PRIORITY_SQL);

$sql = "
SELECT 
    m.api_id,
    m.name AS match_name,
    m.start_time AS start_utc,
    ch.name AS championship_name,
    c.name AS country_name
FROM Events m
JOIN Competitions ch ON m.competition_id = ch.id
LEFT JOIN Countries c ON ch.country_id = c.id
WHERE m.start_time BETWEEN ? AND ?
  AND m.status_id != 3
  AND m.name IS NOT NULL AND TRIM(m.name) != ''
  AND m.api_id IS NOT NULL AND m.api_id > 0
  AND ({$priorityOrder}) < 99
ORDER BY {$priorityOrder}, m.start_time ASC
LIMIT 80
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'DB hiba']);
    exit;
}
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();

$candidates = [];
while ($row = $res->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();

if (empty($candidates)) {
    echo json_encode([]);
    exit;
}

// Determinisztikusan meccset választunk (napi hash), max 3 tipp
$targetTipCount = 3;
$tipCount = min($targetTipCount + 4, count($candidates)); // +4 tartalék ha nincs elég odds

$selectedIndices = [];
$pool = range(0, count($candidates) - 1);

for ($i = 0; $i < $tipCount; $i++) {
    $h = abs(crc32($today . 'tip' . $i));
    $idx = $h % count($pool);
    $selectedIndices[] = $pool[$idx];
    array_splice($pool, $idx, 1);
    if (empty($pool)) break;
}

$tips = [];

foreach ($selectedIndices as $si) {
    $match = $candidates[$si];
    $eventId = (int)$match['api_id'];

    // Csapatnevek
    $matchName = $match['match_name'];
    $homeTeam = '';
    $awayTeam = '';
    foreach ([' vs. ', ' vs ', ' - ', ' – '] as $sep) {
        if (strpos($matchName, $sep) !== false) {
            $parts = explode($sep, $matchName, 2);
            $homeTeam = trim($parts[0]);
            $awayTeam = trim($parts[1]);
            break;
        }
    }
    if (!$homeTeam) continue;

    // Időformázás UTC → Budapest
    $dt = new DateTime($match['start_utc'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startFormatted = $dt->format('m.d. H:i');

    // Odds lekérése az API-ból
    try {
        $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);

        if (!isset($apiData['markets']) || !is_array($apiData['markets'])) continue;

        // Érdemi piacok szűrése (min 2 selection, különböző piacok)
        $validMarkets = [];
        foreach ($apiData['markets'] as $market) {
            $sels = $market['selections'] ?? [];
            if (count($sels) >= 2) {
                $validMarkets[] = $market;
            }
        }
        if (count($validMarkets) < 2) continue;

        // 2 különböző piacból 1-1 selection’t választunk
        $mPool = range(0, count($validMarkets) - 1);
        $m1Idx = abs(crc32($today . 'mA' . $si)) % count($mPool);
        $market1 = $validMarkets[$mPool[$m1Idx]];
        array_splice($mPool, $m1Idx, 1);
        $m2Idx = abs(crc32($today . 'mB' . $si)) % count($mPool);
        $market2 = $validMarkets[$mPool[$m2Idx]];

        $sel1Idx = abs(crc32($today . 'sA' . $si)) % count($market1['selections']);
        $sel2Idx = abs(crc32($today . 'sB' . $si)) % count($market2['selections']);

        $s1 = $market1['selections'][$sel1Idx];
        $s2 = $market2['selections'][$sel2Idx];

        $odd1 = round((float)($s1['odd'] ?? 1.0), 2);
        $odd2 = round((float)($s2['odd'] ?? 1.0), 2);

        $tips[] = [
            'eventId'   => $eventId,
            'homeTeam'  => $homeTeam,
            'awayTeam'  => $awayTeam,
            'league'    => $match['championship_name'] ?? '',
            'startTime' => $startFormatted,
            'picks'     => [
                ['market' => $market1['name'] ?? '', 'pick' => $s1['name'] ?? '', 'odds' => $odd1],
                ['market' => $market2['name'] ?? '', 'pick' => $s2['name'] ?? '', 'odds' => $odd2],
            ],
            'comboOdds' => round($odd1 * $odd2, 2),
        ];

        // Ha elértük a kívánt számot, ne keressünk tovább
        if (count($tips) >= $targetTipCount) break;
    } catch (Throwable $e) {
        error_log("daily_tips API hiba eventId={$eventId}: " . $e->getMessage());
        continue;
    }
}

echo json_encode($tips, JSON_UNESCAPED_UNICODE);
