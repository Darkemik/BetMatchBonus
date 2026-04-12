<?php
require_once __DIR__ . '/../../connect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, transaction_id, amount, created_at FROM Transactions WHERE user_id = ? AND type = 'withdrawal' AND status = 'pending' ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$pending = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Egyenleg adatok
$balStmt = $conn->prepare("SELECT balance, winnings_balance, bonus_balance FROM Users WHERE id = ?");
$balStmt->bind_param("i", $user_id);
$balStmt->execute();
$balData = $balStmt->get_result()->fetch_assoc();
$balStmt->close();

$balance = (float)($balData['balance'] ?? 0);
$winnings = (float)($balData['winnings_balance'] ?? 0);
$bonus = (float)($balData['bonus_balance'] ?? 0);
$deposited = max(0, $balance - $winnings);
$total = $deposited + $winnings;

echo json_encode([
    'success' => true,
    'pending' => $pending,
    'balance' => $balance,
    'winnings_balance' => $winnings,
    'bonus_balance' => $bonus,
    'deposited_balance' => $deposited,
    'total_deposit_and_winnings' => $total
]);
