<?php
/**
 * GET_DAILY_TIPS.PHP — Napi népszerű fogadási tippek (cache-elt)
 * 
 * Determinisztikusan kiválaszt 3 meccset a főbb bajnokságokból,
 * mindegyikhez lekéri az odds-ot az API-ból, és 2 tippet ad meccsenként.
 * A tippek naponta változnak (dátum-hash), de az oddsok mindig
 * az aktuális meccs-oddsokból érkeznek.
 * 
 * Output: JSON [ { eventId, homeTeam, awayTeam, league, picks, comboOdds, startTime, isDailyTip }, ... ]
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');
$cacheDir  = dirname(__DIR__) . '/uploads';
$cacheFile = $cacheDir . '/daily_tips_cache.json';

// Nem adunk vissza egész napos statikus cache-t, mert az oddsoknak
// követniük kell az élő változásokat.

// 2) Jelöltek lekérdezése (fix napi ablak: ma 00:00 UTC → +3 nap)
$from = (new DateTime('today 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$to   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$priorityOrder = str_replace('comp.', 'ch.', LEAGUE_PRIORITY_SQL);

// Esport sport ID-k kizárása (e-Labdarúgás stb.)
$esportIds = [];
$esStmt = $conn->query("SELECT id FROM Sports WHERE api_id IN (146, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160) OR name LIKE 'e-%' OR name LIKE 'E-%' OR name LIKE '%eSport%' OR name LIKE '%esport%'");
if ($esStmt) {
    while ($esRow = $esStmt->fetch_assoc()) {
        $esportIds[] = (int)$esRow['id'];
    }
}
$esportFilter = '';
if (!empty($esportIds)) {
    $esportFilter = 'AND m.sport_id NOT IN (' . implode(',', $esportIds) . ')';
}

$sql = "
SELECT 
    m.api_id,
    m.name AS match_name,
    m.start_time AS start_utc,
    ch.name AS championship_name,
    c.name AS country_name
FROM Events m
JOIN Competitions ch ON m.competition_id = ch.id
JOIN Sports s ON m.sport_id = s.id
LEFT JOIN Countries c ON ch.country_id = c.id
WHERE m.start_time BETWEEN ? AND ?
    AND m.start_time > UTC_TIMESTAMP()
  AND m.status_id NOT IN (3, 5)
  AND m.name IS NOT NULL AND TRIM(m.name) != ''
  AND m.api_id IS NOT NULL AND m.api_id > 0
  {$esportFilter}
  AND ({$priorityOrder}) < 99
ORDER BY {$priorityOrder}, m.start_time ASC
LIMIT 120
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Ha a prioritásos query nem ad eleget, fallback: bármely valódi sport
    $sqlFallback = "
    SELECT m.api_id, m.name AS match_name, m.start_time AS start_utc,
           ch.name AS championship_name, c.name AS country_name
    FROM Events m
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
            AND m.start_time > UTC_TIMESTAMP()
      AND m.status_id NOT IN (3, 5)
      AND m.name IS NOT NULL AND TRIM(m.name) != ''
      AND m.api_id > 0
      {$esportFilter}
    ORDER BY m.start_time ASC
    LIMIT 120";
    $stmt = $conn->prepare($sqlFallback);
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
}
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();

$candidates = [];
while ($row = $res->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();

// Ha a prioritásos lista túl rövid, fallback meccsekkel kiegészítjük
if (count($candidates) < 20) {
    $existingIds = array_column($candidates, 'api_id');
    $placeholders = !empty($existingIds) ? 'AND m.api_id NOT IN (' . implode(',', array_map('intval', $existingIds)) . ')' : '';
    $sqlExtra = "
    SELECT m.api_id, m.name AS match_name, m.start_time AS start_utc,
           ch.name AS championship_name, c.name AS country_name
    FROM Events m
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
            AND m.start_time > UTC_TIMESTAMP()
      AND m.status_id NOT IN (3, 5)
      AND m.name IS NOT NULL AND TRIM(m.name) != ''
      AND m.api_id > 0
      {$esportFilter}
      {$placeholders}
    ORDER BY m.start_time ASC
    LIMIT 60";
    $stmtExtra = $conn->prepare($sqlExtra);
    if ($stmtExtra) {
        $stmtExtra->bind_param("ss", $from, $to);
        $stmtExtra->execute();
        $resExtra = $stmtExtra->get_result();
        while ($row = $resExtra->fetch_assoc()) {
            $candidates[] = $row;
        }
        $stmtExtra->close();
    }
}

if (empty($candidates)) {
    echo json_encode([]);
    exit;
}

// 3) Determinisztikusan meccset választunk (napi hash), max 3 tipp
$targetTipCount = 3;
$tipCount = min($targetTipCount + 12, count($candidates));

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

        $matchInfo = (isset($apiData['match']) && is_array($apiData['match'])) ? $apiData['match'] : [];
        $isLiveNow = !empty($matchInfo['isLive']);
        $hasStarted = !empty($matchInfo['hasStarted']);
        if ($isLiveNow || $hasStarted) {
            continue;
        }

        if (!empty($matchInfo['startUtc'])) {
            $startTs = strtotime((string)$matchInfo['startUtc']);
            if ($startTs !== false && $startTs <= time()) {
                continue;
            }
        }

        if (!isset($apiData['markets']) || !is_array($apiData['markets'])) continue;

        // Érdemi piacok szűrése (min 2 selection)
        $validMarkets = [];
        foreach ($apiData['markets'] as $market) {
            $sels = $market['selections'] ?? [];
            if (count($sels) >= 2) {
                $validMarkets[] = $market;
            }
        }
        if (count($validMarkets) < 2) continue;

        // 2 különböző piacból 1-1 selection-t választunk
        $mPool = range(0, count($validMarkets) - 1);
        $m1Idx = abs(crc32($today . 'mA' . $si)) % count($mPool);
        $market1 = $validMarkets[$mPool[$m1Idx]];
        array_splice($mPool, $m1Idx, 1);
        $m2Idx = abs(crc32($today . 'mB' . $si)) % count($mPool);
        $market2 = $validMarkets[$mPool[$m2Idx]];

        $validSelections1 = array_values(array_filter($market1['selections'], function ($sel) {
            $odd = (float)($sel['odd'] ?? 0);
            return $odd > 1;
        }));
        $validSelections2 = array_values(array_filter($market2['selections'], function ($sel) {
            $odd = (float)($sel['odd'] ?? 0);
            return $odd > 1;
        }));

        if (empty($validSelections1) || empty($validSelections2)) continue;

        $sel1Idx = abs(crc32($today . 'sA' . $si)) % count($validSelections1);
        $sel2Idx = abs(crc32($today . 'sB' . $si)) % count($validSelections2);

        $s1 = $validSelections1[$sel1Idx];
        $s2 = $validSelections2[$sel2Idx];

        $market1Name = (string)($market1['name'] ?? '');
        $market2Name = (string)($market2['name'] ?? '');
        $market1Special = isset($market1['specialValue']) ? trim((string)$market1['specialValue']) : '';
        $market2Special = isset($market2['specialValue']) ? trim((string)$market2['specialValue']) : '';
        $market1Full = $market1Name . ($market1Special !== '' ? ' (' . $market1Special . ')' : '');
        $market2Full = $market2Name . ($market2Special !== '' ? ' (' . $market2Special . ')' : '');

        $odd1 = round((float)($s1['odd'] ?? 1.0), 2);
        $odd2 = round((float)($s2['odd'] ?? 1.0), 2);

        $comboOdds = round($odd1 * $odd2, 2);

        $tips[] = [
            'eventId'   => $eventId,
            'homeTeam'  => $homeTeam,
            'awayTeam'  => $awayTeam,
            'league'    => $match['championship_name'] ?? '',
            'startTime' => $startFormatted,
            'picks'     => [
                [
                    'market' => $market1Full,
                    'marketId' => (int)($market1['id'] ?? ($market1['marketId'] ?? 0)),
                    'specialValue' => $market1Special,
                    'pick' => $s1['name'] ?? '',
                    'selectionId' => (int)($s1['id'] ?? ($s1['selectionId'] ?? 0)),
                    'odds' => $odd1
                ],
                [
                    'market' => $market2Full,
                    'marketId' => (int)($market2['id'] ?? ($market2['marketId'] ?? 0)),
                    'specialValue' => $market2Special,
                    'pick' => $s2['name'] ?? '',
                    'selectionId' => (int)($s2['id'] ?? ($s2['selectionId'] ?? 0)),
                    'odds' => $odd2
                ],
            ],
            'comboOdds' => $comboOdds,
            'isDailyTip' => true,
        ];

        if (count($tips) >= $targetTipCount) break;
    } catch (Throwable $e) {
        error_log("daily_tips API hiba eventId={$eventId}: " . $e->getMessage());
        continue;
    }
}

// 4) Cache mentése
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
file_put_contents($cacheFile, json_encode([
    'date' => $today,
    'tips' => $tips,
], JSON_UNESCAPED_UNICODE));

echo json_encode($tips, JSON_UNESCAPED_UNICODE);
