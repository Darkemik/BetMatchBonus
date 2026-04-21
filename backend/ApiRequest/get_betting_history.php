<?php
/**
 * GET_BETTING_HISTORY.PHP - Bejelentkezett felhasználó Ticketjeinek lekérése
 * Automatikusan kiértékeli a nyitott szelvényeket is!
 */

session_start();
require_once dirname(__DIR__) . "/connect.php";
require_once __DIR__ . "/check_bets.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Nem vagy bejelentkezve!']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ELŐSZÖR kiértékeljük a nyitott szelvényeket
evaluateOpenTickets($conn, $userId);

// Ticketek lekérése az utolsó 50-ből
$stmtTickets = $conn->prepare("
    SELECT t.id, t.stake, t.total_odds, t.potential_win, t.status, t.cashout_amount, t.cashout_at, t.created_at, t.updated_at,
           t.bonus_stake, COALESCE(bc.bet_reward_type, '') AS ticket_bet_reward_type
    FROM Tickets t
    LEFT JOIN UserBonuses ub ON ub.id = t.user_bonus_id
    LEFT JOIN BonusCodes bc ON bc.id = ub.bonus_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 50
");
$stmtTickets->bind_param("i", $userId);
$stmtTickets->execute();
$ticketsResult = $stmtTickets->get_result();

$tickets = [];
while ($ticket = $ticketsResult->fetch_assoc()) {
    $ticketId = (int)$ticket['id'];
    
    // Selections lekérése ebből a ticketből
    $stmtSelections = $conn->prepare("
        SELECT ts.id, ts.odds_at_pick, ts.status, 
               ts.home_team, ts.away_team, ts.pick_label, ts.market_name,
               ts.event_id, ts.match_id, e.api_id AS event_api_id,
               s.api_id AS sport_api_id, s.name AS sport_name,
               o.label, e.home_team_name, e.away_team_name, 
               em.name as em_market_name
        FROM TicketSelections ts
        LEFT JOIN OddsOutcomes o ON ts.outcome_id = o.id
        LEFT JOIN EventMarkets em ON o.event_market_id = em.id
        LEFT JOIN Events e ON ts.event_id = e.id
        LEFT JOIN Sports s ON e.sport_id = s.id
        WHERE ts.ticket_id = ?
    ");
    $stmtSelections->bind_param("i", $ticketId);
    $stmtSelections->execute();
    $selectionsResult = $stmtSelections->get_result();
    
    $selections = [];
    while ($sel = $selectionsResult->fetch_assoc()) {
        $rawHomeTeam = $sel['home_team'] ?: ($sel['home_team_name'] ?? '');
        $rawAwayTeam = $sel['away_team'] ?: ($sel['away_team_name'] ?? '');
        $eventApiId = $sel['event_api_id'] ? (int)$sel['event_api_id'] : null;
        $matchId = $sel['match_id'] ? (int)$sel['match_id'] : ($eventApiId ?: null);
        if (!$matchId && $rawHomeTeam && $rawAwayTeam) {
            $matchId = resolve_match_api_id_by_teams($conn, $rawHomeTeam, $rawAwayTeam);
        }

        $selections[] = [
            'homeTeam' => $rawHomeTeam,
            'awayTeam' => $rawAwayTeam,
            'pick' => $sel['pick_label'] ?: ($sel['label'] ?? ''),
            'market' => $sel['market_name'] ?: ($sel['em_market_name'] ?? ''),
            'odds' => (float)$sel['odds_at_pick'],
            'status' => $sel['status'],
            'event_id' => $eventApiId,
            'sport_api_id' => isset($sel['sport_api_id']) ? (int)$sel['sport_api_id'] : null,
            'sport_name' => $sel['sport_name'] ?? null,
            'match_id' => $matchId
        ];
    }
    $stmtSelections->close();
    
    // Ticket statusz meghatározása
    $status = $ticket['status'];
    if ($status === 'CASHOUT') {
        // Keep as-is
    } elseif ($status === 'OPEN') {
        $allFinished = true;
        $anyLost = false;
        $allVoid = true;
        
        foreach ($selections as $sel) {
            if ($sel['status'] === 'OPEN') {
                $allFinished = false;
                $allVoid = false;
            } elseif ($sel['status'] === 'LOST') {
                $anyLost = true;
                $allVoid = false;
            } elseif ($sel['status'] !== 'VOID') {
                $allVoid = false;
            }
        }
        
        if ($allVoid && count($selections) > 0) {
            $status = 'VOID';
        } elseif ($anyLost) {
            $status = 'LOST';
        } elseif ($allFinished && count($selections) > 0) {
            $status = 'WON';
        }
    }
    
    $tickets[] = [
        'id' => $ticketId,
        'stake' => (float)$ticket['stake'],
        'total_odds' => (float)$ticket['total_odds'],
        'potential_win' => (float)$ticket['potential_win'],
        'status' => $status,
        'bonus_bet' => ((float)($ticket['bonus_stake'] ?? 0)) > 0,
        'free_bet_used' => strtoupper((string)($ticket['ticket_bet_reward_type'] ?? '')) === 'FREE_BET',
        'cashout_amount' => $ticket['cashout_amount'] !== null ? (float)$ticket['cashout_amount'] : null,
        'cashout_at' => $ticket['cashout_at'],
        'created_at' => $ticket['created_at'],
        'items' => $selections
    ];
}
$stmtTickets->close();

echo json_encode([
    'status' => 'ok',
    'history' => $tickets
]);

function resolve_match_api_id_by_teams($conn, $homeTeam, $awayTeam) {
    static $cache = [];

    $homeKey = mb_strtolower(trim((string)$homeTeam));
    $awayKey = mb_strtolower(trim((string)$awayTeam));
    $cacheKey = $homeKey . '||' . $awayKey;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if ($homeKey === '' || $awayKey === '') {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt = $conn->prepare("\n        SELECT api_id\n        FROM Events\n        WHERE api_id IS NOT NULL\n          AND (\n            (LOWER(TRIM(home_team_name)) = ? AND LOWER(TRIM(away_team_name)) = ?)\n            OR (LOWER(TRIM(home_team_name)) = ? AND LOWER(TRIM(away_team_name)) = ?)\n            OR LOWER(name) LIKE ?\n          )\n        ORDER BY is_live DESC, start_time DESC\n        LIMIT 1\n    ");
    $nameLike = '%' . $homeKey . '%vs%' . $awayKey . '%';
    $stmt->bind_param('sssss', $homeKey, $awayKey, $awayKey, $homeKey, $nameLike);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $resolved = $row ? (int)$row['api_id'] : null;
    $cache[$cacheKey] = $resolved;
    return $resolved;
}

?>
