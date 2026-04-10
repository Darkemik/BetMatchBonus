<?php
/**
 * GET_FINISHED_MATCH_DETAILS.PHP — Lejátszott meccs részletek + záró piacok + user fogadások
 * 
 * Query: ?eventId=12345
 * Output: JSON { match: {...}, markets: [...], userBets: [...] }
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Hiányzó vagy érvénytelen eventId']);
    exit;
}

// ── 1) MECCS ALAPADATOK ──────────────────
$stmt = $conn->prepare("
    SELECT 
        e.api_id, e.name, e.start_time, e.is_live, e.live_time, e.live_status,
        e.home_score, e.away_score, e.status_id,
        e.home_team_name, e.away_team_name,
        comp.name AS league_name,
        c.name   AS country_name,
        s.api_id AS sport_api_id
    FROM Events e
    LEFT JOIN Competitions comp ON e.competition_id = comp.id
    LEFT JOIN Countries c ON comp.country_id = c.id
    LEFT JOIN Sports s ON e.sport_id = s.id
    WHERE e.api_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$dbRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dbRow) {
    http_response_code(404);
    echo json_encode(['error' => 'Meccs nem található']);
    exit;
}

// Csapatnevek
$homeTeam = $dbRow['home_team_name'] ?? '';
$awayTeam = $dbRow['away_team_name'] ?? '';

// Fallback: meccs névből bontás
if (empty($homeTeam) || empty($awayTeam)) {
    $matchName = $dbRow['name'] ?? '';
    $separators = [' vs. ', ' vs ', ' - ', ' – ', ' v '];
    foreach ($separators as $sep) {
        if (strpos($matchName, $sep) !== false) {
            $parts = explode($sep, $matchName, 2);
            $homeTeam = trim($parts[0]);
            $awayTeam = trim($parts[1]);
            break;
        }
    }
}

// Score
$homeScore = $dbRow['home_score'];
$awayScore = $dbRow['away_score'];
$score = ($homeScore !== null && $awayScore !== null)
    ? (int)$homeScore . ' - ' . (int)$awayScore
    : '- - -';

// Start time (DB-ben UTC van tárolva, Budapest-re konvertáljuk megjelenítéshez)
$startFormatted = null;
if (!empty($dbRow['start_time'])) {
    $dt = new DateTime($dbRow['start_time'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startFormatted = $dt->format('Y. m. d. H:i');
}

$response = [
    'match' => [
        'id'           => $eventId,
        'name'         => $dbRow['name'] ?? '',
        'homeTeam'     => $homeTeam,
        'awayTeam'     => $awayTeam,
        'homeScore'    => $homeScore !== null ? (int)$homeScore : null,
        'awayScore'    => $awayScore !== null ? (int)$awayScore : null,
        'score'        => $score,
        'startTime'    => $startFormatted,
        'country'      => $dbRow['country_name'] ?: 'Nemzetközi',
        'championship' => $dbRow['league_name'] ?? 'Ismeretlen',
        'statusId'     => (int)$dbRow['status_id'],
        'liveStatus'   => $dbRow['live_status'] ?? null,
        'sportApiId'   => (int)($dbRow['sport_api_id'] ?? 0),
    ],
    'markets' => [],
    'userBets' => []
];

// ── 2) ZÁRÓ PIACOK / ODDS (EventMarkets + OddsOutcomes) ──────
$eventInternalId = null;
$stmtId = $conn->prepare("SELECT id FROM Events WHERE api_id = ? LIMIT 1");
$stmtId->bind_param("i", $eventId);
$stmtId->execute();
$idRow = $stmtId->get_result()->fetch_assoc();
$stmtId->close();

if ($idRow) {
    $eventInternalId = (int)$idRow['id'];

    $mStmt = $conn->prepare("
        SELECT 
            em.name AS market_name,
            em.special_value,
            em.status AS market_status,
            oo.label,
            oo.odds,
            oo.status AS outcome_status
        FROM EventMarkets em
        JOIN OddsOutcomes oo ON oo.event_market_id = em.id
        WHERE em.event_id = ?
        ORDER BY em.name, oo.role, oo.label
    ");
    $mStmt->bind_param("i", $eventInternalId);
    $mStmt->execute();
    $mRes = $mStmt->get_result();

    $marketsMap = [];
    while ($mr = $mRes->fetch_assoc()) {
        $key = $mr['market_name'] . '||' . ($mr['special_value'] ?? '');
        if (!isset($marketsMap[$key])) {
            $marketsMap[$key] = [
                'name' => $mr['market_name'],
                'specialValue' => $mr['special_value'],
                'status' => $mr['market_status'],
                'outcomes' => []
            ];
        }
        $marketsMap[$key]['outcomes'][] = [
            'label'  => $mr['label'],
            'odds'   => (float)$mr['odds'],
            'status' => $mr['outcome_status']
        ];
    }
    $mStmt->close();
    $response['markets'] = array_values($marketsMap);
}

// ── 3) USER FOGADÁSAI ERRE A MECCSRE (ha be van jelentkezve) ──
session_start();
$userId = $_SESSION['user_id'] ?? null;

if ($userId && $eventInternalId) {
    $bStmt = $conn->prepare("
        SELECT 
            ts.pick_label,
            ts.market_name,
            ts.odds_at_pick,
            ts.status AS bet_status,
            t.stake,
            t.total_odds,
            t.potential_win,
            t.status AS ticket_status
        FROM TicketSelections ts
        JOIN Tickets t ON ts.ticket_id = t.id
        WHERE t.user_id = ?
          AND (ts.event_id = ? OR ts.match_id = ?)
        ORDER BY t.created_at DESC
    ");
    $bStmt->bind_param("iii", $userId, $eventInternalId, $eventId);
    $bStmt->execute();
    $bRes = $bStmt->get_result();

    while ($br = $bRes->fetch_assoc()) {
        $response['userBets'][] = [
            'pick'         => $br['pick_label'],
            'market'       => $br['market_name'],
            'oddsAtPick'   => (float)$br['odds_at_pick'],
            'betStatus'    => $br['bet_status'],
            'stake'        => (float)$br['stake'],
            'totalOdds'    => (float)$br['total_odds'],
            'potentialWin' => (float)$br['potential_win'],
            'ticketStatus' => $br['ticket_status'],
        ];
    }
    $bStmt->close();
}

// ── 4) EGYMÁS ELLENI ELŐZMÉNYEK (H2H) ──────
$response['h2h'] = [];
if (!empty($homeTeam) && !empty($awayTeam)) {
    $h2hStmt = $conn->prepare("
        SELECT 
            e.name, e.start_time, e.home_score, e.away_score,
            e.home_team_name, e.away_team_name,
            comp.name AS league_name
        FROM Events e
        LEFT JOIN Competitions comp ON e.competition_id = comp.id
        WHERE e.status_id = 3
          AND e.api_id != ?
          AND (
              (e.home_team_name = ? AND e.away_team_name = ?)
              OR (e.home_team_name = ? AND e.away_team_name = ?)
          )
        ORDER BY e.start_time DESC
        LIMIT 10
    ");
    $h2hStmt->bind_param("issss", $eventId, $homeTeam, $awayTeam, $awayTeam, $homeTeam);
    $h2hStmt->execute();
    $h2hRes = $h2hStmt->get_result();
    while ($h = $h2hRes->fetch_assoc()) {
        $dtH2h = new DateTime($h['start_time'], new DateTimeZone('UTC'));
        $dtH2h->setTimezone(new DateTimeZone('Europe/Budapest'));
        $response['h2h'][] = [
            'name'       => $h['name'],
            'date'       => $dtH2h->format('Y.m.d'),
            'homeTeam'   => $h['home_team_name'],
            'awayTeam'   => $h['away_team_name'],
            'homeScore'  => $h['home_score'] !== null ? (int)$h['home_score'] : null,
            'awayScore'  => $h['away_score'] !== null ? (int)$h['away_score'] : null,
            'league'     => $h['league_name'] ?? '',
        ];
    }
    $h2hStmt->close();
}

// ── 5) BAJNOKSÁG TÖBBI MECCSE AZNAP ──────
$response['sameCompetition'] = [];
if (!empty($dbRow['start_time'])) {
    $dtMatchDay = new DateTime($dbRow['start_time'], new DateTimeZone('UTC'));
    $matchDay = $dtMatchDay->format('Y-m-d');
    $dayStart = $matchDay . ' 00:00:00';
    $dayEnd   = $matchDay . ' 23:59:59';

    $scStmt = $conn->prepare("
        SELECT 
            e.api_id, e.name, e.start_time,
            e.home_score, e.away_score, e.status_id
        FROM Events e
        WHERE e.competition_id = (SELECT competition_id FROM Events WHERE api_id = ? LIMIT 1)
          AND e.api_id != ?
          AND e.start_time BETWEEN ? AND ?
          AND e.name IS NOT NULL AND TRIM(e.name) != ''
        ORDER BY e.start_time ASC
        LIMIT 10
    ");
    $scStmt->bind_param("iiss", $eventId, $eventId, $dayStart, $dayEnd);
    $scStmt->execute();
    $scRes = $scStmt->get_result();
    while ($sc = $scRes->fetch_assoc()) {
        $scScore = ($sc['home_score'] !== null && $sc['away_score'] !== null)
            ? (int)$sc['home_score'] . ' - ' . (int)$sc['away_score']
            : '-';
        $dtSc = new DateTime($sc['start_time'], new DateTimeZone('UTC'));
        $dtSc->setTimezone(new DateTimeZone('Europe/Budapest'));
        $response['sameCompetition'][] = [
            'apiId'    => (int)$sc['api_id'],
            'name'     => $sc['name'],
            'time'     => $dtSc->format('H:i'),
            'score'    => $scScore,
            'finished' => ((int)$sc['status_id'] === 3),
        ];
    }
    $scStmt->close();
}

// ── 6) FOGADÁSI STATISZTIKA (összes user) ──────
$response['bettingStats'] = [
    'totalBets' => 0,
    'uniqueUsers' => 0,
    'wonCount' => 0,
    'lostCount' => 0,
    'topPick' => null,
    'topPickCount' => 0,
];
if ($eventInternalId) {
    $bsStmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_bets,
            COUNT(DISTINCT t.user_id) AS unique_users,
            SUM(CASE WHEN ts.status = 'WON' THEN 1 ELSE 0 END) AS won_count,
            SUM(CASE WHEN ts.status = 'LOST' THEN 1 ELSE 0 END) AS lost_count
        FROM TicketSelections ts
        JOIN Tickets t ON ts.ticket_id = t.id
        WHERE ts.event_id = ? OR ts.match_id = ?
    ");
    $bsStmt->bind_param("ii", $eventInternalId, $eventId);
    $bsStmt->execute();
    $bsRow = $bsStmt->get_result()->fetch_assoc();
    $bsStmt->close();

    if ($bsRow) {
        $response['bettingStats']['totalBets']   = (int)$bsRow['total_bets'];
        $response['bettingStats']['uniqueUsers'] = (int)$bsRow['unique_users'];
        $response['bettingStats']['wonCount']    = (int)$bsRow['won_count'];
        $response['bettingStats']['lostCount']   = (int)$bsRow['lost_count'];
    }

    // Legnépszerűbb tipp
    $tpStmt = $conn->prepare("
        SELECT ts.pick_label, COUNT(*) AS cnt
        FROM TicketSelections ts
        WHERE ts.event_id = ? OR ts.match_id = ?
        GROUP BY ts.pick_label
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $tpStmt->bind_param("ii", $eventInternalId, $eventId);
    $tpStmt->execute();
    $tpRow = $tpStmt->get_result()->fetch_assoc();
    $tpStmt->close();

    if ($tpRow) {
        $response['bettingStats']['topPick']      = $tpRow['pick_label'];
        $response['bettingStats']['topPickCount'] = (int)$tpRow['cnt'];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
