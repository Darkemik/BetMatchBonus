<?php
// Simulate admin session and test the statistics API
$_SESSION = ['admin_id' => 1, 'admin_role' => 'SUPERADMIN', 'admin_username' => 'test'];
session_id('test_session_' . time());

ob_start();
$_GET['range'] = '30';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Override session_start to avoid conflict
function mock_admin_guard($min = 'MOD') {}

require_once __DIR__ . '/../connect.php';

// Run the same queries as admin_statistics.php
$range = '30';
$dateFilter = " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $range DAY)";
$datFilterTx = " AND DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL $range DAY)";

$errors = [];

// Test each query individually
$queries = [
    'total_users' => "SELECT COUNT(*) AS c FROM Users",
    'new_users_30d' => "SELECT COUNT(*) AS c FROM Users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'balances' => "SELECT COALESCE(SUM(balance),0) AS b, COALESCE(SUM(winnings_balance),0) AS w, COALESCE(SUM(bonus_balance),0) AS bo FROM Users",
    'deposits' => "SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM Transactions WHERE type='deposit' AND status='completed'",
    'withdrawals' => "SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM Transactions WHERE type='withdrawal' AND status='completed'",
    'tickets' => "SELECT COUNT(*) AS c, COALESCE(SUM(stake),0) AS s FROM Tickets",
    'won_tickets' => "SELECT COUNT(*) AS c FROM Tickets WHERE status='WON'",
    'lost_tickets' => "SELECT COUNT(*) AS c FROM Tickets WHERE status='LOST'",
    'open_tickets' => "SELECT COUNT(*) AS c FROM Tickets WHERE status='OPEN'",
    'paid_wins' => "SELECT COALESCE(SUM(potential_win),0) AS s FROM Tickets WHERE status='WON'",
    'cashouts' => "SELECT COUNT(*) AS c, COALESCE(SUM(cashout_amount),0) AS s FROM Tickets WHERE status='CASHOUT'",
    'daily_deposits' => "SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(amount) AS total FROM Transactions WHERE type='deposit' AND status='completed' $dateFilter GROUP BY DATE(created_at) ORDER BY d",
    'daily_withdrawals' => "SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(amount) AS total FROM Transactions WHERE type='withdrawal' AND status='completed' $dateFilter GROUP BY DATE(created_at) ORDER BY d",
    'daily_regs' => "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM Users WHERE 1=1 $dateFilter GROUP BY DATE(created_at) ORDER BY d",
    'daily_bets' => "SELECT DATE(created_at) AS d, COUNT(*) AS cnt, SUM(stake) AS total FROM Tickets WHERE 1=1 $dateFilter GROUP BY DATE(created_at) ORDER BY d",
    'top_depositors' => "SELECT u.id, u.username, SUM(t.amount) AS total_dep, COUNT(*) AS cnt FROM Transactions t JOIN Users u ON t.user_id = u.id WHERE t.type='deposit' AND t.status='completed' $datFilterTx GROUP BY u.id ORDER BY total_dep DESC LIMIT 10",
    'top_bettors' => "SELECT u.id, u.username, SUM(t2.stake) AS total_stake, COUNT(*) AS cnt FROM Tickets t2 JOIN Users u ON t2.user_id = u.id WHERE 1=1 " . str_replace('created_at', 't2.created_at', $dateFilter) . " GROUP BY u.id ORDER BY total_stake DESC LIMIT 10",
    'top_winners' => "SELECT u.id, u.username, SUM(t2.potential_win) AS total_win, COUNT(*) AS cnt FROM Tickets t2 JOIN Users u ON t2.user_id = u.id WHERE t2.status='WON' " . str_replace('created_at', 't2.created_at', $dateFilter) . " GROUP BY u.id ORDER BY total_win DESC LIMIT 10",
];

foreach ($queries as $name => $sql) {
    $r = $conn->query($sql);
    if ($r === false) {
        $errors[] = "$name: ERROR - " . $conn->error;
        echo "❌ $name: " . $conn->error . "\n";
    } else {
        echo "✅ $name: OK (" . $r->num_rows . " rows)\n";
    }
}

if (empty($errors)) {
    echo "\n✅ ALL QUERIES PASSED - no SQL errors\n";
} else {
    echo "\n❌ " . count($errors) . " QUERIES FAILED\n";
}
