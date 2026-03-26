<?php
session_start();

// Check if user is logged in
require_once '../Auth/check_session.php';
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = '❌ Érvénytelen kérés';
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

// Get POST data
$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
$cardholder_name = isset($_POST['cardholder_name']) ? htmlspecialchars(trim($_POST['cardholder_name'])) : '';
$card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', $_POST['card_number']) : '';
$card_expiry = isset($_POST['card_expiry']) ? htmlspecialchars(trim($_POST['card_expiry'])) : '';
$card_cvc = isset($_POST['card_cvc']) ? preg_replace('/\D/', '', $_POST['card_cvc']) : '';

// Validate amount
if ($amount < 3000 || $amount > 600000) {
    $_SESSION['error_message'] = '❌ Az összeg 3000-600000 FT között kell, hogy legyen';
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

// Validate cardholder name
if (empty($cardholder_name) || strlen($cardholder_name) < 3) {
    $_SESSION['error_message'] = '❌ Kérjük adja meg a kártyatulajdonos nevét';
    header('Location: /BetMatchBonus/frontend/UserProfile/deposits.php?amount=' . $amount);
    exit;
}

// Validate card number (must be exactly 16 digits)
if (strlen($card_number) !== 16 || !ctype_digit($card_number)) {
    $_SESSION['error_message'] = '❌ A kártyaszám 16 számjegyből kell, hogy álljon';
    header('Location: /BetMatchBonus/frontend/UserProfile/stripe_payment_form.php?amount=' . $amount);
    exit;
}

// Validate expiry date
if (empty($card_expiry) || !preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
    $_SESSION['error_message'] = '❌ Hibás lejárati dátum (MM/YY formátum szükséges)';
    header('Location: /BetMatchBonus/frontend/UserProfile/stripe_payment_form.php?amount=' . $amount);
    exit;
}

// Validate CVC (3-4 digits)
if (strlen($card_cvc) < 3 || strlen($card_cvc) > 4 || !ctype_digit($card_cvc)) {
    $_SESSION['error_message'] = '❌ A CVC 3-4 számjegyből kell, hogy álljon';
    header('Location: /BetMatchBonus/frontend/UserProfile/stripe_payment_form.php?amount=' . $amount);
    exit;
}

// Simulate payment processing delay for demo mode
sleep(1);

// Generate transaction IDs for demo mode
$transaction_id = 'TRN_' . uniqid();

// Prepare values
$amount_str = number_format($amount, 2, '.', '');
$type = 'deposit';
$payment_method = 'card_demo';
$status = 'completed';

// Prepare INSERT statement
// Columns: user_id (i), type (s), amount (s for DECIMAL), payment_method (s), status (s), transaction_id (s)
$insert_query = "
    INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id)
    VALUES (?, ?, ?, ?, ?, ?)
";

$insert_stmt = $conn->prepare($insert_query);

if (!$insert_stmt) {
    $_SESSION['error_message'] = '❌ Adatbázis hiba: ' . $conn->error;
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

// Bind parameters with CORRECT types: i=int, s=string (1 int + 5 strings = 6 total)
$insert_stmt->bind_param('isssss', $user_id, $type, $amount_str, $payment_method, $status, $transaction_id);

// Execute INSERT
if (!$insert_stmt->execute()) {
    $_SESSION['error_message'] = '❌ Tranzakció rögzítési hiba: ' . $insert_stmt->error;
    $insert_stmt->close();
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

$insert_stmt->close();

// Update user balance
$update_query = "UPDATE Users SET balance = balance + ? WHERE id = ?";
$update_stmt = $conn->prepare($update_query);

if (!$update_stmt) {
    $_SESSION['error_message'] = '❌ Egyenleg frissítési hiba: ' . $conn->error;
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

// Bind parameters: s = amount string, i = user_id integer
$update_stmt->bind_param('si', $amount_str, $user_id);

if (!$update_stmt->execute()) {
    $_SESSION['error_message'] = '❌ Egyenleg frissítése nem sikerült: ' . $update_stmt->error;
    $update_stmt->close();
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

$update_stmt->close();

// Success - set session message and redirect
$_SESSION['success_message'] = '✅ Befizetés sikeres! +' . number_format($amount, 0, ',', ' ') . ' FT';
header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
exit;
?>