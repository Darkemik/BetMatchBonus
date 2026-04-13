<?php
require_once __DIR__ . "/../Auth/check_session.php";
require_once __DIR__ . "/../connect.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nincs bejelentkezve']);
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_type = $_GET['payment_type'] ?? '';
$allowed_types = ['visa', 'mastercard', 'paypal', 'bank_transfer'];

if (!in_array($payment_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen fizetési mód']);
    exit;
}

$stmt = $conn->prepare("SELECT card_number, card_expiry, paypal_email, account_number FROM UserPaymentMethods WHERE user_id = ? AND payment_type = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $payment_type);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => true, 'saved' => false]);
    exit;
}

$data = ['success' => true, 'saved' => true];

if ($payment_type === 'visa' || $payment_type === 'mastercard') {
    $data['card_number'] = $row['card_number'] ?? '';
    $data['card_expiry'] = $row['card_expiry'] ?? '';
    // Maszkolás megjelenítéshez (utolsó 4 szám)
    $cn = $row['card_number'] ?? '';
    $data['card_number_masked'] = str_repeat('•', max(0, strlen($cn) - 4)) . substr($cn, -4);
} elseif ($payment_type === 'paypal') {
    $data['paypal_email'] = $row['paypal_email'] ?? '';
} elseif ($payment_type === 'bank_transfer') {
    $data['account_number'] = $row['account_number'] ?? '';
}

echo json_encode($data);
