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
               o.label, e.home_team_name, e.away_team_name, 
               em.name as em_market_name
        FROM TicketSelections ts
        LEFT JOIN OddsOutcomes o ON ts.outcome_id = o.id
        LEFT JOIN EventMarkets em ON o.event_market_id = em.id
        LEFT JOIN Events e ON ts.event_id = e.id
        WHERE ts.ticket_id = ?
    ");
    $stmtSelections->bind_param("i", $ticketId);
    $stmtSelections->execute();
    $selectionsResult = $stmtSelections->get_result();
    
    $selections = [];
    while ($sel = $selectionsResult->fetch_assoc()) {
        $selections[] = [
            'homeTeam' => $sel['home_team'] ?: ($sel['home_team_name'] ?? ''),
            'awayTeam' => $sel['away_team'] ?: ($sel['away_team_name'] ?? ''),
            'pick' => $sel['pick_label'] ?: ($sel['label'] ?? ''),
            'market' => $sel['market_name'] ?: ($sel['em_market_name'] ?? ''),
            'odds' => (float)$sel['odds_at_pick'],
            'status' => $sel['status'],
            'event_id' => $sel['event_api_id'] ? (int)$sel['event_api_id'] : null
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
        
        foreach ($selections as $sel) {
            if ($sel['status'] === 'OPEN') {
                $allFinished = false;
            } elseif ($sel['status'] === 'LOST') {
                $anyLost = true;
            }
        }
        
        if ($anyLost) {
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

?>
