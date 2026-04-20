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

function normalizeDailyTipText($value) {
    $text = trim((string)$value);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text;
}

function parseDailyTipGoalsLine($value) {
    $normalized = str_replace(',', '.', normalizeDailyTipText($value));
    if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $m)) {
        return (float)$m[1];
    }
    return null;
}

function isDailyTip1X2Market($marketName) {
    $marketLower = normalizeDailyTipText($marketName);
    return strpos($marketLower, '1x2') !== false
        || strpos($marketLower, 'winner') !== false
        || strpos($marketLower, 'gyoztes') !== false
        || strpos($marketLower, 'match result') !== false
        || strpos($marketLower, 'full time result') !== false
        || strpos($marketLower, 'moneyline') !== false;
}

function isDailyTipCorrectScoreMarket($marketName) {
    $marketLower = normalizeDailyTipText($marketName);
    return strpos($marketLower, 'correct score') !== false
        || strpos($marketLower, 'pontos vegeredmeny') !== false
        || strpos($marketLower, 'exact score') !== false
        || strpos($marketLower, 'vegeredmeny') !== false;
}

function analyzeDailyTipSelection(array $item): array {
    $market = normalizeDailyTipText($item['market'] ?? '');
    $pick = normalizeDailyTipText($item['pick'] ?? '');
    $home = normalizeDailyTipText($item['homeTeam'] ?? '');
    $away = normalizeDailyTipText($item['awayTeam'] ?? '');

    $is1X2 = isDailyTip1X2Market($market);
    $isCorrectScore = isDailyTipCorrectScoreMarket($market);

    $hasGoalWord = strpos($market, 'gol') !== false || strpos($market, 'goal') !== false;
    $isBtts = strpos($market, 'mindket csapat szerez golt') !== false || strpos($market, 'both teams to score') !== false || strpos($market, 'btts') !== false;
    $isTeamToScore = strpos($market, 'melyik csapat szerez golt') !== false || strpos($market, 'which team scores') !== false;
    $isHalfTimeFullTime = strpos($market, 'felido/vegeredmeny') !== false || strpos($market, '1. felido/vegeredmeny') !== false || strpos($market, 'half time/full time') !== false || strpos($market, '1st half/full time') !== false;
    $isHalfTime = (strpos($market, 'felido') !== false || strpos($market, 'half time') !== false || strpos($market, '1st half') !== false) && !$isHalfTimeFullTime;
    $isPlayerToScore = strpos($market, 'jatekos golt szerez') !== false || strpos($market, 'player to score') !== false || strpos($market, 'goalscorer') !== false || strpos($market, 'anytime scorer') !== false;
    $isFirstLastGoal = strpos($market, 'elso gol') !== false || strpos($market, '1. gol') !== false || strpos($market, 'first goal') !== false || strpos($market, 'utolso gol') !== false || strpos($market, 'last goal') !== false;
    $isWinToNil = strpos($market, 'kapott gol nelkuli gyozelem') !== false || strpos($market, 'kapott gol nelkul') !== false || strpos($market, 'win to nil') !== false || strpos($market, 'clean sheet') !== false;
    $isGeneralScoringMarket = strpos($market, 'golt szerez') !== false || strpos($market, 'to score') !== false || strpos($market, 'scores') !== false;

    $isOverPick = strpos($pick, 'felett') !== false || strpos($pick, 'over') !== false || strpos($pick, 'tobb') !== false || strpos($pick, 'more than') !== false;
    $isUnderPick = strpos($pick, 'alatt') !== false || strpos($pick, 'under') !== false || strpos($pick, 'kevesebb') !== false || strpos($pick, 'less than') !== false;
    $isYesPick = $pick === 'igen' || $pick === 'yes';
    $isNoPick = $pick === 'nem' || $pick === 'no';

    $isHomePick = $pick === '1' || $pick === 'hazai' || $pick === 'home' || ($home !== '' && strpos($pick, $home) !== false);
    $isAwayPick = $pick === '2' || $pick === 'vendeg' || $pick === 'away' || ($away !== '' && strpos($pick, $away) !== false);

    $isTeamGoalsMarket = $hasGoalWord && (
        strpos($market, 'hazai csapat') !== false ||
        strpos($market, 'vendeg csapat') !== false ||
        strpos($market, 'home team') !== false ||
        strpos($market, 'away team') !== false ||
        strpos($market, 'csapat gol') !== false ||
        strpos($market, 'team goals') !== false ||
        ($home !== '' && strpos($market, $home) !== false) ||
        ($away !== '' && strpos($market, $away) !== false)
    );

    $isTotalGoalsMarket = $hasGoalWord && (
        strpos($market, 'golok szama') !== false ||
        strpos($market, 'goals szama') !== false ||
        strpos($market, 'total goals') !== false ||
        strpos($market, 'goals number') !== false ||
        strpos($market, 'meccs tobb') !== false ||
        strpos($market, 'match over') !== false ||
        strpos($market, 'match under') !== false ||
        strpos($market, 'over/under') !== false
    ) && !$isTeamGoalsMarket;

    $isGoalRelated = $isTotalGoalsMarket || $isTeamGoalsMarket || $isBtts || $isTeamToScore || $isPlayerToScore || $isFirstLastGoal || $isWinToNil || $isGeneralScoringMarket;
    $lineValue = parseDailyTipGoalsLine(($item['pick'] ?? '') . ' ' . ($item['market'] ?? ''));

    $isTeamToScoreHomePick = $isTeamToScore && ($isHomePick || strpos($pick, 'hazai') !== false || strpos($pick, 'home') !== false);
    $isTeamWinPick = $is1X2 && ($isHomePick || $isAwayPick);

    return [
        'is1X2' => $is1X2,
        'isCorrectScore' => $isCorrectScore,
        'isGoalRelated' => $isGoalRelated,
        'isTotalGoalsMarket' => $isTotalGoalsMarket,
        'isTeamGoalsMarket' => $isTeamGoalsMarket,
        'isBtts' => $isBtts,
        'isHalfTimeFullTime' => $isHalfTimeFullTime,
        'isHalfTime' => $isHalfTime,
        'isPlayerToScore' => $isPlayerToScore,
        'isOverPick' => $isOverPick,
        'isUnderPick' => $isUnderPick,
        'isYesPick' => $isYesPick,
        'isNoPick' => $isNoPick,
        'isTeamToScoreHomePick' => $isTeamToScoreHomePick,
        'isTeamWinPick' => $isTeamWinPick,
        'lineValue' => $lineValue,
    ];
}

function getDailyTipComboConflictMessage(array $itemA, array $itemB): ?string {
    $a = analyzeDailyTipSelection($itemA);
    $b = analyzeDailyTipSelection($itemB);

    if ($a['isCorrectScore'] || $b['isCorrectScore']) {
        return 'correct_score';
    }

    if ($a['isGoalRelated'] && $b['isGoalRelated']) {
        return 'goal_related';
    }

    if (($a['is1X2'] && $b['isGoalRelated']) || ($b['is1X2'] && $a['isGoalRelated'])) {
        return '1x2_goal';
    }

    if ($a['isTotalGoalsMarket'] && $b['isTotalGoalsMarket'] && (($a['isOverPick'] && $b['isUnderPick']) || ($a['isUnderPick'] && $b['isOverPick']))) {
        return 'over_under_opposite';
    }

    if (($a['isBtts'] && $b['isTotalGoalsMarket']) || ($b['isBtts'] && $a['isTotalGoalsMarket'])) {
        $bttsSel = $a['isBtts'] ? $a : $b;
        $goalsSel = $a['isTotalGoalsMarket'] ? $a : $b;
        if (($bttsSel['isYesPick'] && $goalsSel['isOverPick']) || ($bttsSel['isNoPick'] && $goalsSel['isUnderPick'])) {
            return 'btts_total_goals_combo';
        }
    }

    if ((($a['isHalfTimeFullTime'] || $a['isHalfTime']) && $b['is1X2']) || (($b['isHalfTimeFullTime'] || $b['isHalfTime']) && $a['is1X2'])) {
        return 'halftime_1x2';
    }

    if (($a['isPlayerToScore'] && $b['isTeamWinPick']) || ($b['isPlayerToScore'] && $a['isTeamWinPick'])) {
        return 'player_teamwin';
    }

    if (($a['isTotalGoalsMarket'] && $b['isTeamGoalsMarket']) || ($b['isTotalGoalsMarket'] && $a['isTeamGoalsMarket'])) {
        return 'total_vs_team_goals';
    }

    if (($a['isBtts'] && $a['isYesPick'] && $b['isTeamToScoreHomePick']) || ($b['isBtts'] && $b['isYesPick'] && $a['isTeamToScoreHomePick'])) {
        return 'btts_yes_team_to_score_home';
    }

    return null;
}

function isDailyTipValid(array $tip): bool {
    $picks = is_array($tip['picks'] ?? null) ? $tip['picks'] : [];
    if (count($picks) < 2) return false;
    // Inject homeTeam/awayTeam from the parent tip into each pick
    // so team-name-based goal market detection works correctly.
    $home = $tip['homeTeam'] ?? '';
    $away = $tip['awayTeam'] ?? '';
    foreach ($picks as &$p) {
        if (empty($p['homeTeam'])) $p['homeTeam'] = $home;
        if (empty($p['awayTeam'])) $p['awayTeam'] = $away;
    }
    unset($p);
    return getDailyTipComboConflictMessage($picks[0], $picks[1]) === null;
}

function refreshTipOddsFromApi($tip) {
    if (!is_array($tip)) return $tip;

    $eventId = (int)($tip['eventId'] ?? 0);
    $picks = is_array($tip['picks'] ?? null) ? $tip['picks'] : [];
    if ($eventId <= 0 || empty($picks)) {
        return $tip;
    }

    try {
        $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);
    } catch (Throwable $e) {
        error_log("daily_tips odds refresh API hiba eventId={$eventId}: " . $e->getMessage());
        return $tip;
    }

    $markets = (isset($apiData['markets']) && is_array($apiData['markets'])) ? $apiData['markets'] : [];
    if (empty($markets)) {
        return $tip;
    }

    foreach ($picks as $idx => $pickData) {
        $targetMarketId = (int)($pickData['marketId'] ?? 0);
        $targetSelectionId = (int)($pickData['selectionId'] ?? 0);
        $targetMarketText = normalizeDailyTipText($pickData['market'] ?? '');
        $targetPickText = normalizeDailyTipText($pickData['pick'] ?? '');
        $foundOdd = null;

        foreach ($markets as $market) {
            $marketId = (int)($market['id'] ?? ($market['marketId'] ?? 0));
            $marketName = (string)($market['name'] ?? '');
            $marketSpecial = isset($market['specialValue']) ? trim((string)$market['specialValue']) : '';
            $marketFull = $marketName . ($marketSpecial !== '' ? ' (' . $marketSpecial . ')' : '');

            if ($targetMarketId > 0 && $marketId > 0 && $marketId !== $targetMarketId) {
                continue;
            }

            if ($targetMarketId <= 0) {
                $candidateNames = [
                    normalizeDailyTipText($marketName),
                    normalizeDailyTipText($marketFull)
                ];
                if (!in_array($targetMarketText, $candidateNames, true)) {
                    continue;
                }
            }

            $selections = is_array($market['selections'] ?? null) ? $market['selections'] : [];
            foreach ($selections as $selection) {
                $selectionId = (int)($selection['id'] ?? ($selection['selectionId'] ?? 0));
                $selectionName = normalizeDailyTipText($selection['name'] ?? '');

                if ($targetSelectionId > 0 && $selectionId > 0 && $selectionId !== $targetSelectionId) {
                    continue;
                }
                if ($targetSelectionId <= 0 && $selectionName !== $targetPickText) {
                    continue;
                }

                $selectionOdd = round((float)($selection['odd'] ?? 0), 2);
                if ($selectionOdd > 0) {
                    $foundOdd = $selectionOdd;
                }
                break 2;
            }
        }

        if ($foundOdd !== null) {
            $picks[$idx]['odds'] = $foundOdd;
        }
    }

    $comboOdds = 1.0;
    foreach ($picks as $pickData) {
        $odd = round((float)($pickData['odds'] ?? 0), 2);
        if ($odd <= 0) {
            $comboOdds = 0;
            break;
        }
        $comboOdds *= $odd;
    }

    $tip['picks'] = $picks;
    $tip['comboOdds'] = round($comboOdds, 2);
    return $tip;
}

// A napi tippek meccsei/piacai csak naponta változzanak:
// ha van mai cache, azt használjuk, csak az oddsokat frissítjük API-ból.
if (file_exists($cacheFile)) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['date'] ?? '') === $today && is_array($cached['tips'] ?? null) && !empty($cached['tips'])) {
        $tipsFromCache = array_map('refreshTipOddsFromApi', $cached['tips']);
        $tipsFromCache = array_values(array_filter($tipsFromCache, 'isDailyTipValid'));

        if (empty($tipsFromCache)) {
            // Ha a korábbi cache már tiltott kombinációkat tartalmaz, újrageneráljuk.
        } else {
            file_put_contents($cacheFile, json_encode([
                'date' => $today,
                'tips' => $tipsFromCache,
            ], JSON_UNESCAPED_UNICODE));

            echo json_encode($tipsFromCache, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

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
    m.competition_id,
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
    AND LOWER(ch.name) NOT LIKE '%serie a2%'
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
    SELECT m.api_id, m.competition_id, m.name AS match_name, m.start_time AS start_utc,
           ch.name AS championship_name, c.name AS country_name
    FROM Events m
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
            AND m.start_time > UTC_TIMESTAMP()
      AND m.status_id NOT IN (3, 5)
            AND LOWER(ch.name) NOT LIKE '%serie a2%'
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
    SELECT m.api_id, m.competition_id, m.name AS match_name, m.start_time AS start_utc,
           ch.name AS championship_name, c.name AS country_name
    FROM Events m
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
            AND m.start_time > UTC_TIMESTAMP()
      AND m.status_id NOT IN (3, 5)
        AND LOWER(ch.name) NOT LIKE '%serie a2%'
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

// Csak az első 5 bajnoki táblából (competitionből) válogatunk napi tippet.
$maxDailyTipTables = 5;
$allowedCompetitionIds = [];
$seenCompetitions = [];
foreach ($candidates as $row) {
    $competitionId = (int)($row['competition_id'] ?? 0);
    if ($competitionId <= 0 || isset($seenCompetitions[$competitionId])) {
        continue;
    }
    $seenCompetitions[$competitionId] = true;
    $allowedCompetitionIds[] = $competitionId;
    if (count($allowedCompetitionIds) >= $maxDailyTipTables) {
        break;
    }
}

if (!empty($allowedCompetitionIds)) {
    $candidates = array_values(array_filter($candidates, function ($row) use ($allowedCompetitionIds) {
        $competitionId = (int)($row['competition_id'] ?? 0);
        return in_array($competitionId, $allowedCompetitionIds, true);
    }));
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

        // Két olyan selection-t választunk ugyanarra a meccsre,
        // amelyek kombinációja NEM ütközik a kötés tiltás szabályokba.
        $pickCandidates = [];
        foreach ($validMarkets as $market) {
            $marketName = (string)($market['name'] ?? '');
            $marketId = (int)($market['id'] ?? ($market['marketId'] ?? 0));
            $marketSpecial = isset($market['specialValue']) ? trim((string)$market['specialValue']) : '';
            $marketFull = $marketName . ($marketSpecial !== '' ? ' (' . $marketSpecial . ')' : '');
            $selections = is_array($market['selections'] ?? null) ? $market['selections'] : [];

            foreach ($selections as $sel) {
                $odd = round((float)($sel['odd'] ?? 0), 2);
                if ($odd <= 1) continue;

                $pickCandidates[] = [
                    'eventId' => $eventId,
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
                    'market' => $marketFull,
                    'marketId' => $marketId,
                    'specialValue' => $marketSpecial,
                    'pick' => (string)($sel['name'] ?? ''),
                    'selectionId' => (int)($sel['id'] ?? ($sel['selectionId'] ?? 0)),
                    'odds' => $odd,
                ];
            }
        }

        if (count($pickCandidates) < 2) continue;

        $pair = null;
        $candidateCount = count($pickCandidates);
        $startIdx = abs(crc32($today . 'pairSeed' . $si)) % $candidateCount;

        for ($aOffset = 0; $aOffset < $candidateCount; $aOffset++) {
            $aIdx = ($startIdx + $aOffset) % $candidateCount;
            $aPick = $pickCandidates[$aIdx];

            for ($bOffset = 1; $bOffset < $candidateCount; $bOffset++) {
                $bIdx = ($startIdx + $aOffset + $bOffset) % $candidateCount;
                $bPick = $pickCandidates[$bIdx];

                // Két külön piacból jöjjön a tipp.
                $sameMarket = ($aPick['marketId'] > 0 && $bPick['marketId'] > 0)
                    ? ((int)$aPick['marketId'] === (int)$bPick['marketId'])
                    : (normalizeDailyTipText($aPick['market']) === normalizeDailyTipText($bPick['market']));
                if ($sameMarket) continue;

                if (getDailyTipComboConflictMessage($aPick, $bPick) !== null) {
                    continue;
                }

                $pair = [$aPick, $bPick];
                break 2;
            }
        }

        if ($pair === null) continue;

        $p1 = $pair[0];
        $p2 = $pair[1];
        $odd1 = (float)$p1['odds'];
        $odd2 = (float)$p2['odds'];

        $comboOdds = round($odd1 * $odd2, 2);

        $tips[] = [
            'eventId'   => $eventId,
            'homeTeam'  => $homeTeam,
            'awayTeam'  => $awayTeam,
            'league'    => $match['championship_name'] ?? '',
            'startTime' => $startFormatted,
            'picks'     => [
                [
                    'market' => $p1['market'],
                    'marketId' => (int)$p1['marketId'],
                    'specialValue' => $p1['specialValue'],
                    'pick' => $p1['pick'],
                    'selectionId' => (int)$p1['selectionId'],
                    'odds' => $odd1,
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
                ],
                [
                    'market' => $p2['market'],
                    'marketId' => (int)$p2['marketId'],
                    'specialValue' => $p2['specialValue'],
                    'pick' => $p2['pick'],
                    'selectionId' => (int)$p2['selectionId'],
                    'odds' => $odd2,
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
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
