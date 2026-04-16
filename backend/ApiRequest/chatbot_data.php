<?php
/**
 * CHATBOT_DATA.PHP — BMB Asszisztens backend
 * 
 * Bejelentkezett felhasználó adatait adja vissza a chatbot számára.
 * GET ?action=summary  → egyenleg, aktív bónuszok, utolsó szelvények
 * GET ?action=balance  → csak egyenleg
 * GET ?action=bonuses  → aktív bónuszok listája
 * GET ?action=history  → utolsó 5 szelvény
 * GET ?action=live     → élő meccsek száma sportáganként
 */
session_start();
require_once dirname(__DIR__) . '/connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$action = isset($_GET['action']) ? trim($_GET['action']) : 'summary';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$response = ['loggedIn' => $userId > 0];

// === EGYENLEG ===
if ($userId > 0 && in_array($action, ['summary', 'balance'])) {
    $stmt = $conn->prepare("SELECT balance, bonus_balance FROM Users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($u) {
        $response['balance'] = (float)$u['balance'];
        $response['bonusBalance'] = (float)$u['bonus_balance'];
    }
}

// === AKTÍV BÓNUSZOK ===
if ($userId > 0 && in_array($action, ['summary', 'bonuses'])) {
    $stmt = $conn->prepare("
        SELECT ub.id, bc.name, ub.status, ub.bonus_balance, ub.wagering_required, ub.wagering_progress, ub.expires_at
        FROM UserBonuses ub
        JOIN BonusCodes bc ON ub.bonus_id = bc.id
        WHERE ub.user_id = ? AND ub.status IN ('ACTIVE','PENDING')
        ORDER BY ub.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result();
    $bonuses = [];
    while ($row = $r->fetch_assoc()) {
        $bonuses[] = [
            'name' => $row['name'],
            'status' => $row['status'],
            'balance' => (float)$row['bonus_balance'],
            'wageringRequired' => (float)$row['wagering_required'],
            'wageringProgress' => (float)$row['wagering_progress'],
            'expiresAt' => $row['expires_at'],
        ];
    }
    $stmt->close();
    $response['activeBonuses'] = $bonuses;
}

// === UTOLSÓ SZELVÉNYEK ===
if ($userId > 0 && in_array($action, ['summary', 'history'])) {
    $stmt = $conn->prepare("
        SELECT id, status, stake, total_odds, potential_win, cashout_amount, created_at,
               (SELECT COUNT(*) FROM TicketSelections WHERE ticket_id = t.id) AS selections
        FROM Tickets t
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result();
    $tickets = [];
    while ($row = $r->fetch_assoc()) {
        $tickets[] = [
            'id' => (int)$row['id'],
            'status' => $row['status'],
            'stake' => (float)$row['stake'],
            'odds' => (float)$row['total_odds'],
            'potentialWin' => (float)$row['potential_win'],
            'cashout' => $row['cashout_amount'] !== null ? (float)$row['cashout_amount'] : null,
            'selections' => (int)$row['selections'],
            'date' => $row['created_at'],
        ];
    }
    $stmt->close();
    $response['recentTickets'] = $tickets;

    // Összesített statisztikák
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='WON' THEN 1 ELSE 0 END) as won,
            SUM(CASE WHEN status='LOST' THEN 1 ELSE 0 END) as lost,
            SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END) as open_count
        FROM Tickets WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $response['ticketStats'] = [
        'total' => (int)$stats['total'],
        'won' => (int)$stats['won'],
        'lost' => (int)$stats['lost'],
        'open' => (int)$stats['open_count'],
    ];
}

// === ÉLŐ MECCSEK ===
if (in_array($action, ['summary', 'live'])) {
    $r = $conn->query("
        SELECT s.name AS sport_name, COUNT(*) AS cnt
        FROM Events e
        JOIN Sports s ON e.sport_id = s.id
        WHERE e.is_live = 1
        GROUP BY s.id
        ORDER BY cnt DESC
    ");
    $live = [];
    $totalLive = 0;
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $live[] = ['sport' => $row['sport_name'], 'count' => (int)$row['cnt']];
            $totalLive += (int)$row['cnt'];
        }
    }
    $response['liveMatches'] = $live;
    $response['totalLive'] = $totalLive;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
