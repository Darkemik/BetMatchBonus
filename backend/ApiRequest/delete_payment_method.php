<?php
require_once __DIR__ . "/../Auth/check_session.php";
require_once __DIR__ . "/../connect.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nincs bejelentkezve']);
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_type = $_POST['payment_type'] ?? '';
$allowed_types = ['visa', 'mastercard', 'paypal', 'bank_transfer'];

if (!in_array($payment_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen fizetési mód']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM UserPaymentMethods WHERE user_id = ? AND payment_type = ?");
$stmt->bind_param("is", $user_id, $payment_type);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Mentett fizetési adatok törölve']);
