<?php
/**
 * GET_BOOSTED_MATCH.PHP — Napi "Oddsűrhajó" kiemelt meccs
 * 
 * Naponta determinisztikusan kiválaszt 1 meccset a főbb bajnokságokból,
 * és az első piac első odds-ára 1.5x szorzót alkalmaz.
 * 
 * Output: JSON { match: {...}, boostedMarket: {...}, boostedSelection: {...} }
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');

// Főbb bajnokságok keresése a következő 3 napra
$from = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$to   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$priorityOrder = str_replace('comp.', 'ch.', LEAGUE_PRIORITY_SQL);

$sql = "
SELECT 
    m.api_id,
    m.name AS match_name,
    m.start_time AS start_utc,
    m.is_live,
    c.name AS country_name,
    ch.name AS championship_name,
    s.api_id AS sport_api_id
FROM Events m
JOIN Sports s ON m.sport_id = s.id
JOIN Competitions ch ON m.competition_id = ch.id
LEFT JOIN Countries c ON ch.country_id = c.id
WHERE m.start_time BETWEEN ? AND ?
  AND m.status_id != 3
  AND m.name IS NOT NULL
  AND TRIM(m.name) != ''
  AND m.api_id IS NOT NULL
  AND m.api_id > 0
  AND ({$priorityOrder}) < 99
ORDER BY {$priorityOrder}, m.start_time ASC
LIMIT 50
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'DB hiba']);
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
    echo json_encode(['success' => false, 'error' => 'Nincs elérhető meccs']);
    exit;
}

// Determinisztikus napi kiválasztás: a dátum hash-e határozza meg az indexet
$dayHash = crc32($today . 'oddsboost');
$selectedIndex = abs($dayHash) % count($candidates);
$selected = $candidates[$selectedIndex];

$eventId = (int)$selected['api_id'];

// Csapatnevek bontása
$matchName = $selected['match_name'];
$homeTeam = '';
$awayTeam = '';
$separators = [' vs. ', ' vs ', ' - ', ' – '];
foreach ($separators as $sep) {
    if (strpos($matchName, $sep) !== false) {
        $parts = explode($sep, $matchName, 2);
        $homeTeam = trim($parts[0]);
        $awayTeam = trim($parts[1]);
        break;
    }
}

// Időformázás (UTC → Budapest)
$startUtcDt = new DateTime($selected['start_utc'], new DateTimeZone('UTC'));
$startUtcDt->setTimezone(new DateTimeZone('Europe/Budapest'));
$startFormatted = $startUtcDt->format('m.d. H:i');

// Odds lekérése az API-ból
$boostedMarket = null;
$boostedSelection = null;
$originalOdd = null;
$boostedOdd = null;

try {
    $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);

    if (isset($apiData['markets']) && is_array($apiData['markets'])) {
        // Keressünk "Győztes" / "1X2" / első érdemi piacot
        $targetMarket = null;
        foreach ($apiData['markets'] as $market) {
            $mName = mb_strtolower($market['name'] ?? '');
            $sels = $market['selections'] ?? [];
            if (count($sels) < 2) continue;

            // Preferáljuk a győztes/1X2 piacot
            if (strpos($mName, 'győztes') !== false ||
                strpos($mName, 'winner') !== false ||
                strpos($mName, '1x2') !== false ||
                strpos($mName, 'végeredmény') !== false ||
                strpos($mName, 'match result') !== false) {
                $targetMarket = $market;
                break;
            }
            // Ha nincs specifikus, az első érdemi piacot vesszük
            if ($targetMarket === null) {
                $targetMarket = $market;
            }
        }

        if ($targetMarket && !empty($targetMarket['selections'])) {
            $boostedMarket = $targetMarket['name'];

            // Determinisztikusan választunk egy selection-t
            $selCount = count($targetMarket['selections']);
            $selIndex = abs(crc32($today . 'sel')) % $selCount;
            $sel = $targetMarket['selections'][$selIndex];

            $originalOdd = round((float)($sel['odd'] ?? 1.0), 2);
            $boostedOdd = round($originalOdd * 1.5, 2);
            $boostedSelection = $sel['name'] ?? '';
        }
    }
} catch (Throwable $e) {
    error_log("Oddsűrhajó API hiba: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'eventId' => $eventId,
    'matchName' => $matchName,
    'homeTeam' => $homeTeam,
    'awayTeam' => $awayTeam,
    'country' => $selected['country_name'] ?: 'Nemzetközi',
    'championship' => $selected['championship_name'],
    'startTime' => $startFormatted,
    'sportApiId' => (int)$selected['sport_api_id'],
    'boostedMarket' => $boostedMarket,
    'boostedSelection' => $boostedSelection,
    'originalOdd' => $originalOdd,
    'boostedOdd' => $boostedOdd,
    'boostMultiplier' => 1.5,
    'date' => $today,
], JSON_UNESCAPED_UNICODE);
