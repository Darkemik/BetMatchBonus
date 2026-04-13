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

// Adatok validálása típus szerint
$card_number = null;
$card_expiry = null;
$paypal_email = null;
$account_number = null;

if ($payment_type === 'visa' || $payment_type === 'mastercard') {
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');

    if (strlen($card_number) !== 16) {
        echo json_encode(['success' => false, 'error' => 'A kártyaszámnak 16 számjegyből kell állnia']);
        exit;
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
        echo json_encode(['success' => false, 'error' => 'Hibás lejárati dátum']);
        exit;
    }

} elseif ($payment_type === 'paypal') {
    $paypal_email = filter_var(trim($_POST['paypal_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$paypal_email) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen PayPal email']);
        exit;
    }

} elseif ($payment_type === 'bank_transfer') {
    $account_number = preg_replace('/\s/', '', $_POST['account_number'] ?? '');
    if (!preg_match('/^\d{8}-?\d{8}(-?\d{8})?$/', $account_number)) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen bankszámlaszám']);
        exit;
    }
}

// Ellenőrizzük, létezik-e már mentett adat ehhez a fizetési módhoz
$checkStmt = $conn->prepare("SELECT id FROM UserPaymentMethods WHERE user_id = ? AND payment_type = ? AND is_active = 1 LIMIT 1");
$checkStmt->bind_param("is", $user_id, $payment_type);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    // Frissítés
    $updStmt = $conn->prepare("UPDATE UserPaymentMethods SET card_number = ?, card_expiry = ?, paypal_email = ?, account_number = ?, updated_at = NOW() WHERE id = ?");
    $updStmt->bind_param("ssssi", $card_number, $card_expiry, $paypal_email, $account_number, $existing['id']);
    $updStmt->execute();
    $updStmt->close();
} else {
    // Új mentés
    $insStmt = $conn->prepare("INSERT INTO UserPaymentMethods (user_id, payment_type, card_number, card_expiry, paypal_email, account_number, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $insStmt->bind_param("isssss", $user_id, $payment_type, $card_number, $card_expiry, $paypal_email, $account_number);
    $insStmt->execute();
    $insStmt->close();
}

echo json_encode(['success' => true, 'message' => 'Fizetési adatok elmentve']);
