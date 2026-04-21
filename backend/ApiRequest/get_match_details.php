<?php
/**
 * GET_MATCH_DETAILS.PHP — Meccs részletek + odds
 * 
 * Meccs alapadatok: DB-ből (Events + Competitions + Countries)
 * Odds/piacok: API-ból real-time (mert másodpercenként változnak)
 * 
 * Query: ?eventId=12345
 * Output: JSON { match: {...}, markets: [...] }
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";
require_once __DIR__ . '/boosted_match_cache.php';

header('Content-Type: application/json; charset=utf-8');

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Hiányzó vagy érvénytelen eventId']);
    exit;
}

// ── 1) MECCS ALAPADATOK DB-BŐL ──────────────────
$stmt = $conn->prepare("
    SELECT 
        e.api_id, e.name, e.start_time, e.is_live, e.live_time,
        e.home_score, e.away_score, e.status_id,
        s.api_id AS sport_api_id,
        s.name AS sport_name,
        comp.name AS league_name,
        c.name AS country_name
    FROM Events e
    LEFT JOIN Sports s ON e.sport_id = s.id
    LEFT JOIN Competitions comp ON e.competition_id = comp.id
    LEFT JOIN Countries c ON comp.country_id = c.id
    WHERE e.api_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$dbRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Csapatnevek kinyerése a meccs nevéből
$matchName = $dbRow['name'] ?? 'Ismeretlen meccs';
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

// Score DB-ből
$score = '';
if ($dbRow && $dbRow['home_score'] !== null && $dbRow['away_score'] !== null) {
    $score = (int)$dbRow['home_score'] . ' - ' . (int)$dbRow['away_score'];
}

// Start time → UTC formátum (DB-ben UTC van tárolva)
$startUtcValue = null;
if (!empty($dbRow['start_time'])) {
    try {
        $dt = new DateTime($dbRow['start_time'], new DateTimeZone('UTC'));
        $startUtcValue = $dt->format('Y-m-d\TH:i:s\Z');
    } catch (Exception $e) {
        $startUtcValue = $dbRow['start_time'];
    }
}

$response_data = [
    'match' => [
        'id'           => $eventId,
        'name'         => $matchName,
        'homeTeam'     => $homeTeam,
        'awayTeam'     => $awayTeam,
        'score'        => $score ?: '0 - 0',
        'isLive'       => $dbRow ? (bool)$dbRow['is_live'] : false,
        'statusId'     => $dbRow ? (int)($dbRow['status_id'] ?? 0) : 0,
        'sportApiId'   => $dbRow ? (int)($dbRow['sport_api_id'] ?? 0) : 0,
        'sportName'    => $dbRow['sport_name'] ?? null,
        'hasStarted'   => false,
        'liveTime'     => $dbRow['live_time'] ?? null,
        'liveStatus'   => null,
        'country'      => $dbRow['country_name'] ?: 'Nemzetközi',
        'championship' => $dbRow['league_name'] ?? 'Ismeretlen',
        'startUtc'     => $startUtcValue,
    ],
    'markets' => []
];

if ($dbRow) {
    $startTs = 0;
    if (!empty($dbRow['start_time'])) {
        try {
            $dtStart = new DateTime($dbRow['start_time'], new DateTimeZone('UTC'));
            $startTs = $dtStart->getTimestamp();
        } catch (Throwable $e) {
            $startTs = 0;
        }
    }

    if ((int)($dbRow['is_live'] ?? 0) === 1 || ($startTs > 0 && $startTs <= time())) {
        $response_data['match']['hasStarted'] = true;
    }
}

// ── 2) ODDS/PIACOK API-BÓL (real-time) ──────────

// Oddsűrhajó: napi boost ellenőrzés cache-ből
$boostedCache = getDailyBoostedMatch();
$isBoostedMatch = false;
$boostedMarketName = null;
$boostedSelectionName = null;

if ($boostedCache && (int)($boostedCache['eventId'] ?? 0) === $eventId) {
    $isBoostedMatch = true;
    $boostedMarketName = $boostedCache['boostedMarket'] ?? null;
    $boostedSelectionName = $boostedCache['boostedSelection'] ?? null;
}

try {
    $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);

    // Ha az API-nak van frissebb score/live adat, felülírjuk
    if (!empty($apiData['isLive'])) {
        $response_data['match']['isLive'] = true;
        $response_data['match']['hasStarted'] = true;
    }
    if (!empty($apiData['liveTime'])) {
        $response_data['match']['liveTime'] = $apiData['liveTime'];
    }
    if (!empty($apiData['liveStatus'])) {
        $response_data['match']['liveStatus'] = $apiData['liveStatus'];
    }
    if (!empty($apiData['homeTeam'])) {
        $response_data['match']['homeTeam'] = $apiData['homeTeam'];
    }
    if (!empty($apiData['awayTeam'])) {
        $response_data['match']['awayTeam'] = $apiData['awayTeam'];
    }

    // Score: API-ból ha van, különben DB marad
    if (isset($apiData['score']) && is_array($apiData['score']) && count($apiData['score']) >= 2) {
        $response_data['match']['score'] = $apiData['score'][0] . ' - ' . $apiData['score'][1];
    }

    // Piacok feldolgozása
    if (isset($apiData['markets']) && is_array($apiData['markets'])) {
        // Oddsűrhajó meccs élőbe váltás vagy befejezés után nem rakható: piacokat lezárjuk.
        if ($isBoostedMatch && (
            !empty($response_data['match']['isLive'])
            || (int)($response_data['match']['statusId'] ?? 0) === 3
        )) {
            $apiData['markets'] = [];
        }

        $seen = [];
        foreach ($apiData['markets'] as $market) {
            $marketName = $market['name'] ?? '';
            $specialVal = $market['specialValue'] ?? null;
            $marketKey  = $marketName . '||' . ($specialVal ?? '');

            if (isset($seen[$marketKey])) continue;
            $seen[$marketKey] = true;

            $marketData = [
                'name'         => $marketName,
                'specialValue' => $specialVal,
                'selections'   => []
            ];

            if (isset($market['selections']) && is_array($market['selections'])) {
                $seenSel = [];
                foreach ($market['selections'] as $selection) {
                    $selName = $selection['name'] ?? '';
                    if (isset($seenSel[$selName])) continue;
                    $seenSel[$selName] = true;

                    $odd = (float)($selection['odd'] ?? 1.0);
                    $isBoosted = false;

                    // Boost alkalmazása ha egyezik a meccs, piac és selection
                    if ($isBoostedMatch &&
                        $marketName === $boostedMarketName &&
                        $selName === $boostedSelectionName) {
                        $isBoosted = true;
                        $originalOdd = $odd;
                        $odd = round($odd * 1.5, 2);
                    }

                    $selData = [
                        'name' => $selName,
                        'odds' => $odd
                    ];
                    if ($isBoosted) {
                        $selData['boosted'] = true;
                        $selData['originalOdds'] = $originalOdd;
                    }

                    $marketData['selections'][] = $selData;
                }
            }

            if (!empty($marketData['selections'])) {
                $response_data['markets'][] = $marketData;
            }
        }
    }
} catch (Throwable $e) {
    // API nem elérhető → meccs adatok DB-ből akkor is visszaadjuk, csak odds nélkül
    error_log("get_match_details odds API hiba eventId={$eventId}: " . $e->getMessage());
}

// Jelezzük ha boosted meccs
if ($isBoostedMatch) {
    $response_data['match']['isBoosted'] = true;
}

echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
