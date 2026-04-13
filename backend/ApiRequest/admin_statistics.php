<?php
/**
 * Admin Statistics API
 * GET ?range=7|30|90|365|all  — JSON riport adatok grafikonokhoz
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('MOD');
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/permission_helper.php';

if (!check_page_permission('statistics')) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultság']);
    exit;
}

$range = $_GET['range'] ?? '30';
$validRanges = ['7','30','90','365','all'];
if (!in_array($range, $validRanges)) $range = '30';

$dateFilter = $range === 'all' ? '' : " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $range DAY)";
$datFilterTx = $range === 'all' ? '' : " AND DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL $range DAY)";

// ─── 1) Összesített mutatók ───
$overview = [];

// Felhasználók
$r = $conn->query("SELECT COUNT(*) AS c FROM Users")->fetch_assoc();
$overview['total_users'] = (int)$r['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();
$overview['new_users_30d'] = (int)$r['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc();
$overview['new_users_7d'] = (int)$r['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE DATE(created_at) = CURDATE()")->fetch_assoc();
$overview['new_users_today'] = (int)$r['c'];

// Egyenleg összesített
$r = $conn->query("SELECT COALESCE(SUM(balance),0) AS b, COALESCE(SUM(winnings_balance),0) AS w, COALESCE(SUM(bonus_balance),0) AS bo FROM Users")->fetch_assoc();
$overview['total_balance'] = (float)$r['b'];
$overview['total_winnings'] = (float)$r['w'];
$overview['total_bonus'] = (float)$r['bo'];

// Befizetések összesítve
$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM Transactions WHERE type='deposit' AND status='completed'")->fetch_assoc();
$overview['total_deposits'] = (float)$r['s'];
$overview['total_deposit_count'] = (int)$r['c'];

// Kifizetések összesítve
$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM Transactions WHERE type='withdrawal' AND status='completed'")->fetch_assoc();
$overview['total_withdrawals'] = (float)$r['s'];
$overview['total_withdrawal_count'] = (int)$r['c'];

// Nettó bevétel
$overview['net_revenue'] = $overview['total_deposits'] - $overview['total_withdrawals'];

// Fogadások
$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(stake),0) AS s FROM Tickets")->fetch_assoc();
$overview['total_tickets'] = (int)$r['c'];
$overview['total_stake'] = (float)$r['s'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE status='WON'")->fetch_assoc();
$overview['won_tickets'] = (int)$r['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE status='LOST'")->fetch_assoc();
$overview['lost_tickets'] = (int)$r['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE status='OPEN'")->fetch_assoc();
$overview['open_tickets'] = (int)$r['c'];

// Kifizetett nyeremények
$r = $conn->query("SELECT COALESCE(SUM(potential_win),0) AS s FROM Tickets WHERE status='WON'")->fetch_assoc();
$overview['total_paid_wins'] = (float)$r['s'];

// Cashout
$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(cashout_amount),0) AS s FROM Tickets WHERE status='CASHOUT'")->fetch_assoc();
$overview['total_cashouts'] = (int)$r['c'];
$overview['total_cashout_amount'] = (float)$r['s'];

// Házrés (profit): tétek - nyeremények - cashout
$overview['house_edge'] = $overview['total_stake'] - $overview['total_paid_wins'] - $overview['total_cashout_amount'];

// ─── 2) Napi bontás (grafikonokhoz) ───
// Napi befizetések
$dailyDeposits = [];
$q = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(amount) AS total
    FROM Transactions
    WHERE type='deposit' AND status='completed' $dateFilter
    GROUP BY DATE(created_at) ORDER BY d
");
while ($row = $q->fetch_assoc()) {
    $dailyDeposits[] = ['date' => $row['d'], 'count' => (int)$row['cnt'], 'total' => (float)$row['total']];
}

// Napi kifizetések
$dailyWithdrawals = [];
$q = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(amount) AS total
    FROM Transactions
    WHERE type='withdrawal' AND status='completed' $dateFilter
    GROUP BY DATE(created_at) ORDER BY d
");
while ($row = $q->fetch_assoc()) {
    $dailyWithdrawals[] = ['date' => $row['d'], 'count' => (int)$row['cnt'], 'total' => (float)$row['total']];
}

// Napi regisztrációk
$dailyRegistrations = [];
$q = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt
    FROM Users
    WHERE 1=1 $dateFilter
    GROUP BY DATE(created_at) ORDER BY d
");
while ($row = $q->fetch_assoc()) {
    $dailyRegistrations[] = ['date' => $row['d'], 'count' => (int)$row['cnt']];
}

// Napi fogadások (tét + darab)
$dailyBets = [];
$q = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(stake) AS total
    FROM Tickets
    WHERE 1=1 $dateFilter
    GROUP BY DATE(created_at) ORDER BY d
");
while ($row = $q->fetch_assoc()) {
    $dailyBets[] = ['date' => $row['d'], 'count' => (int)$row['cnt'], 'total' => (float)$row['total']];
}

// ─── 3) Top felhasználók ───
$topDepositors = [];
$q = $conn->query("
    SELECT u.id, u.username, SUM(t.amount) AS total_dep, COUNT(*) AS cnt
    FROM Transactions t JOIN Users u ON t.user_id = u.id
    WHERE t.type='deposit' AND t.status='completed' $datFilterTx
    GROUP BY u.id ORDER BY total_dep DESC LIMIT 10
");
while ($row = $q->fetch_assoc()) {
    $topDepositors[] = ['id' => (int)$row['id'], 'username' => $row['username'], 'total' => (float)$row['total_dep'], 'count' => (int)$row['cnt']];
}

$topBettors = [];
$q = $conn->query("
    SELECT u.id, u.username, SUM(t2.stake) AS total_stake, COUNT(*) AS cnt
    FROM Tickets t2 JOIN Users u ON t2.user_id = u.id
    WHERE 1=1 " . str_replace('created_at', 't2.created_at', $dateFilter) . "
    GROUP BY u.id ORDER BY total_stake DESC LIMIT 10
");
while ($row = $q->fetch_assoc()) {
    $topBettors[] = ['id' => (int)$row['id'], 'username' => $row['username'], 'total' => (float)$row['total_stake'], 'count' => (int)$row['cnt']];
}

$topWinners = [];
$q = $conn->query("
    SELECT u.id, u.username, SUM(t2.potential_win) AS total_win, COUNT(*) AS cnt
    FROM Tickets t2 JOIN Users u ON t2.user_id = u.id
    WHERE t2.status='WON' " . str_replace('created_at', 't2.created_at', $dateFilter) . "
    GROUP BY u.id ORDER BY total_win DESC LIMIT 10
");
while ($row = $q->fetch_assoc()) {
    $topWinners[] = ['id' => (int)$row['id'], 'username' => $row['username'], 'total' => (float)$row['total_win'], 'count' => (int)$row['cnt']];
}

echo json_encode([
    'overview' => $overview,
    'daily_deposits' => $dailyDeposits,
    'daily_withdrawals' => $dailyWithdrawals,
    'daily_registrations' => $dailyRegistrations,
    'daily_bets' => $dailyBets,
    'top_depositors' => $topDepositors,
    'top_bettors' => $topBettors,
    'top_winners' => $topWinners
]);
