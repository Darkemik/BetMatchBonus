<?php
session_start();

// Check if user is logged in
require_once '../Auth/check_session.php';
require_once '../../backend/connect.php';

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

// Validate cardholder name - only letters and spaces (including Hungarian characters)
if (!preg_match('/^[a-záéíóöőüűA-ZÁÉÍÓÖŐÜŰ\s]+$/', $cardholder_name)) {
    $_SESSION['error_message'] = '❌ A kártyatulajdonos neve csak betűket és szóközöket tartalmazhat!';
    header('Location: /BetMatchBonus/frontend/UserProfile/stripe_payment_form.php?amount=' . $amount);
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

// Validate expiry date is in the future
list($exp_month, $exp_year) = explode('/', $card_expiry);
$current_date = new DateTime();
$current_month = $current_date->format('m');
$current_year = $current_date->format('y');

$expiry_date_str = $exp_year . $exp_month;
$current_date_str = $current_year . $current_month;

if ((int)$expiry_date_str <= (int)$current_date_str) {
    $_SESSION['error_message'] = '❌ A kártya lejárati dátuma már lejárt! Válassz egy későbbi dátumot.';
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

// Függőben lévő (PENDING) befizetési bónuszok aktiválása
// Megkeressük az összes PENDING bónuszt, amelyek DEPOSIT triggerrel rendelkeznek és teljesül a min. befizetési feltétel
$pending_stmt = $conn->prepare("
    SELECT ub.id AS user_bonus_id, bc.bonus_amount, bc.wagering_multiplier, bc.activation_expire_hours,
           bc.min_deposit, bc.match_percent, bc.max_bonus_amount
    FROM UserBonuses ub
    INNER JOIN BonusCodes bc ON ub.bonus_id = bc.id
    WHERE ub.user_id = ?
      AND ub.status = 'PENDING'
      AND bc.bonus_trigger = 'DEPOSIT'
      AND (bc.valid_to IS NULL OR bc.valid_to >= NOW())
");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_bonuses = $pending_result->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

$bonus_credited_total = 0.00;

foreach ($pending_bonuses as $pb) {
    // Ellenőrizzük, hogy a befizetés eléri-e a minimumot
    if (!empty($pb['min_deposit']) && $amount < (float)$pb['min_deposit']) {
        continue;
    }

    // Bónusz összeg kiszámítása: százalékos bónusz vagy fix összeg
    if (!empty($pb['match_percent']) && (float)$pb['match_percent'] > 0) {
        $granted = $amount * ((float)$pb['match_percent'] / 100);
        if (!empty($pb['max_bonus_amount']) && $granted > (float)$pb['max_bonus_amount']) {
            $granted = (float)$pb['max_bonus_amount'];
        }
    } else {
        $granted = (float)$pb['bonus_amount'];
    }

    // Forgatási követelmény kiszámítása
    $wagering = 0.00;
    if ($granted > 0 && !empty($pb['wagering_multiplier']) && (float)$pb['wagering_multiplier'] > 0) {
        $wagering = $granted * (float)$pb['wagering_multiplier'];
    }

    // Lejárati dátum kiszámítása
    $expires_at = null;
    if (!empty($pb['activation_expire_hours']) && (int)$pb['activation_expire_hours'] > 0) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . (int)$pb['activation_expire_hours'] . ' hours'));
    }

    // UserBonuses rekord frissítése: PENDING → ACTIVE
    $activate_stmt = $conn->prepare("
        UPDATE UserBonuses
        SET status = 'ACTIVE',
            granted_amount = ?,
            bonus_money_amount = ?,
            wagering_required = ?,
            expires_at = ?
        WHERE id = ?
    ");
    $activate_stmt->bind_param("dddsi", $granted, $granted, $wagering, $expires_at, $pb['user_bonus_id']);
    $activate_stmt->execute();
    $activate_stmt->close();

    $bonus_credited_total += $granted;
}

// Ha volt aktivált bónusz, jóváírjuk a bonus_balance-t
if ($bonus_credited_total > 0) {
    $bonus_update_stmt = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
    $bonus_update_stmt->bind_param("di", $bonus_credited_total, $user_id);
    $bonus_update_stmt->execute();
    $bonus_update_stmt->close();
}

// Success - set session message and redirect
$success_msg = '✅ Befizetés sikeres! +' . number_format($amount, 0, ',', ' ') . ' FT';
if ($bonus_credited_total > 0) {
    $success_msg .= ' | 🎁 Bónusz jóváírva: +' . number_format($bonus_credited_total, 0, ',', ' ') . ' FT';
}
$_SESSION['success_message'] = $success_msg;
header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
exit;
?>