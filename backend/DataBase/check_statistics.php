<?php
require_once __DIR__ . '/../connect.php';

$tables = ['Users', 'Transactions', 'Tickets', 'TicketSelections', 'Wallets', 'WalletTransactions'];
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    echo "$t: " . ($r->num_rows ? "EXISTS" : "NOT FOUND") . "\n";
}

echo "\n=== TEST QUERIES ===\n";

// Test balance columns
$r = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME IN ('balance','winnings_balance','bonus_balance')");
echo "Users balance columns: ";
$cols = [];
while ($row = $r->fetch_assoc()) $cols[] = $row['COLUMN_NAME'];
echo implode(', ', $cols) . "\n";

// Test Wallets
$r = $conn->query("DESCRIBE Wallets");
if ($r) {
    echo "\nWallets columns: ";
    $cols = [];
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    echo implode(', ', $cols) . "\n";
}

// Test Transactions
$r = $conn->query("SHOW TABLES LIKE 'Transactions'");
if ($r->num_rows) {
    $r2 = $conn->query("DESCRIBE Transactions");
    echo "\nTransactions columns: ";
    $cols = [];
    while ($row = $r2->fetch_assoc()) $cols[] = $row['Field'];
    echo implode(', ', $cols) . "\n";
} else {
    echo "\nTransactions table NOT FOUND - checking alternatives...\n";
    foreach (['BalanceHistory', 'Deposits', 'Withdrawals'] as $alt) {
        $r3 = $conn->query("SHOW TABLES LIKE '$alt'");
        echo "  $alt: " . ($r3->num_rows ? "EXISTS" : "NOT FOUND") . "\n";
    }
}

// Test Tickets
$r = $conn->query("SHOW TABLES LIKE 'Tickets'");
if ($r->num_rows) {
    $r2 = $conn->query("DESCRIBE Tickets");
    echo "\nTickets columns: ";
    $cols = [];
    while ($row = $r2->fetch_assoc()) $cols[] = $row['Field'];
    echo implode(', ', $cols) . "\n";
}

// Try running a simplified version of admin_statistics queries
echo "\n=== TEST STAT QUERIES ===\n";

try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM Users");
    echo "Users count: " . $r->fetch_assoc()['c'] . "\n";
} catch (Exception $e) {
    echo "Users count ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT COALESCE(SUM(balance),0) AS b, COALESCE(SUM(winnings_balance),0) AS w, COALESCE(SUM(bonus_balance),0) AS bo FROM Users");
    $row = $r->fetch_assoc();
    echo "Balances: bal={$row['b']}, win={$row['w']}, bonus={$row['bo']}\n";
} catch (Exception $e) {
    echo "Balances ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM Transactions WHERE type='deposit' AND status='completed'");
    $row = $r->fetch_assoc();
    echo "Deposits: count={$row['c']}, sum={$row['s']}\n";
} catch (Exception $e) {
    echo "Deposits ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(stake),0) AS s FROM Tickets");
    $row = $r->fetch_assoc();
    echo "Tickets: count={$row['c']}, sum={$row['s']}\n";
} catch (Exception $e) {
    echo "Tickets ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(cashout_amount),0) AS s FROM Tickets WHERE status='CASHOUT'");
    $row = $r->fetch_assoc();
    echo "Cashouts: count={$row['c']}, sum={$row['s']}\n";
} catch (Exception $e) {
    echo "Cashouts ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT u.id, u.username, SUM(t.amount) AS total_dep, COUNT(*) AS cnt FROM Transactions t JOIN Users u ON t.user_id = u.id WHERE t.type='deposit' AND t.status='completed' GROUP BY u.id ORDER BY total_dep DESC LIMIT 10");
    echo "Top depositors query: OK (" . $r->num_rows . " rows)\n";
} catch (Exception $e) {
    echo "Top depositors ERROR: " . $e->getMessage() . "\n";
}

try {
    $r = $conn->query("SELECT u.id, u.username, SUM(t2.stake) AS total_stake, COUNT(*) AS cnt FROM Tickets t2 JOIN Users u ON t2.user_id = u.id WHERE 1=1 GROUP BY u.id ORDER BY total_stake DESC LIMIT 10");
    echo "Top bettors query: OK (" . $r->num_rows . " rows)\n";
} catch (Exception $e) {
    echo "Top bettors ERROR: " . $e->getMessage() . "\n";
}
