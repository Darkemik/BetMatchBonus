<?php
session_start();
date_default_timezone_set('Europe/Budapest');

// Check if user is logged in
require_once '../Auth/check_session.php';
require_once '../../backend/connect.php';
require_once '../../backend/mail_config.php';
require_once '../../backend/PHPMailer/Exception.php';
require_once '../../backend/PHPMailer/PHPMailer.php';
require_once '../../backend/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = '❌ Érvénytelen kérés';
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

// Get POST data
$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
$posted_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'visa';

// Validate amount
if ($amount < 3000 || $amount > 600000) {
    $_SESSION['error_message'] = '❌ Az összeg 3000-600000 FT között kell, hogy legyen';
    header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
    exit;
}

$redirect_back = '/BetMatchBonus/frontend/UserProfile/stripe_payment_form.php?amount=' . $amount . '&method=' . urlencode($posted_method);

if ($posted_method === 'paypal') {
    // === PayPal validáció ===
    $paypal_email = isset($_POST['paypal_email']) ? trim($_POST['paypal_email']) : '';
    $paypal_password = isset($_POST['paypal_password']) ? $_POST['paypal_password'] : '';

    if (empty($paypal_email) || !filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = '❌ Kérjük adjon meg egy érvényes PayPal email címet';
        header('Location: ' . $redirect_back);
        exit;
    }

    if (empty($paypal_password) || strlen($paypal_password) < 6) {
        $_SESSION['error_message'] = '❌ Kérjük adja meg a PayPal jelszavát (minimum 6 karakter)';
        header('Location: ' . $redirect_back);
        exit;
    }

    $payment_method_label = 'paypal';

} else {
    // === Kártya validáció (Visa / Mastercard) ===
    if (!in_array($posted_method, ['visa', 'mastercard'])) {
        $posted_method = 'visa';
    }

    $cardholder_name = isset($_POST['cardholder_name']) ? htmlspecialchars(trim($_POST['cardholder_name'])) : '';
    $card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', $_POST['card_number']) : '';
    $card_expiry = isset($_POST['card_expiry']) ? htmlspecialchars(trim($_POST['card_expiry'])) : '';
    $card_cvc = isset($_POST['card_cvc']) ? preg_replace('/\D/', '', $_POST['card_cvc']) : '';

    // Validate cardholder name
    if (empty($cardholder_name) || strlen($cardholder_name) < 3) {
        $_SESSION['error_message'] = '❌ Kérjük adja meg a kártyatulajdonos nevét';
        header('Location: ' . $redirect_back);
        exit;
    }

    // Validate cardholder name - only letters and spaces (including Hungarian characters)
    if (!preg_match('/^[a-záéíóöőüűA-ZÁÉÍÓÖŐÜŰ\s]+$/', $cardholder_name)) {
        $_SESSION['error_message'] = '❌ A kártyatulajdonos neve csak betűket és szóközöket tartalmazhat!';
        header('Location: ' . $redirect_back);
        exit;
    }

    // Kártyatulajdonos neve meg kell egyezzen a regisztrált teljes névvel
    $nameCheckStmt = $conn->prepare("SELECT full_name FROM Users WHERE id = ?");
    $nameCheckStmt->bind_param("i", $user_id);
    $nameCheckStmt->execute();
    $nameCheckRow = $nameCheckStmt->get_result()->fetch_assoc();
    $nameCheckStmt->close();
    $registered_name = $nameCheckRow['full_name'] ?? '';
    $normalize = function($n) { return mb_strtolower(preg_replace('/\s+/u', ' ', trim($n)), 'UTF-8'); };
    if ($normalize($cardholder_name) !== $normalize($registered_name)) {
        $_SESSION['error_message'] = '❌ A kártyatulajdonos neve nem egyezik a regisztrációkor megadott névvel!';
        header('Location: ' . $redirect_back);
        exit;
    }

    // Validate card number (must be exactly 16 digits)
    if (strlen($card_number) !== 16 || !ctype_digit($card_number)) {
        $_SESSION['error_message'] = '❌ A kártyaszám 16 számjegyből kell, hogy álljon';
        header('Location: ' . $redirect_back);
        exit;
    }

    // Validate expiry date
    if (empty($card_expiry) || !preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
        $_SESSION['error_message'] = '❌ Hibás lejárati dátum (MM/YY formátum szükséges)';
        header('Location: ' . $redirect_back);
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
        header('Location: ' . $redirect_back);
        exit;
    }

    // Validate CVC (3-4 digits)
    if (strlen($card_cvc) < 3 || strlen($card_cvc) > 4 || !ctype_digit($card_cvc)) {
        $_SESSION['error_message'] = '❌ A CVC 3-4 számjegyből kell, hogy álljon';
        header('Location: ' . $redirect_back);
        exit;
    }

    $payment_method_label = $posted_method; // visa or mastercard
}

// Simulate payment processing delay for demo mode
sleep(1);

// Generate transaction IDs for demo mode
$transaction_id = 'TRN_' . uniqid();

// Prepare values
$amount_str = number_format($amount, 2, '.', '');
$type = 'deposit';
$payment_method = $payment_method_label;
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
    SELECT ub.id AS user_bonus_id, ub.created_at,
        bc.bonus_amount, bc.wagering_multiplier, bc.activation_expire_hours,
            bc.min_deposit, bc.match_percent, bc.max_bonus_amount, bc.valid_weekdays_only,
            bc.bet_reward_type, bc.daily_start_time, bc.admin_force_active
    FROM UserBonuses ub
    INNER JOIN BonusCodes bc ON ub.bonus_id = bc.id
    WHERE ub.user_id = ?
    AND (
        (ub.status = 'PENDING' AND ub.used = 0)
       OR (ub.status = 'ACTIVE' AND ub.used = 0 AND COALESCE(ub.granted_amount, 0) = 0)
    )
      AND bc.bonus_trigger = 'DEPOSIT'
      AND (bc.valid_to IS NULL OR bc.valid_to >= NOW())
            AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_bonuses = $pending_result->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

$bonus_credited_total = 0.00;
$bonus_credit_to_balance = 0.00;
$bonus_credit_to_bonus_balance = 0.00;
$isWeekday = ((int)date('N') <= 5);

foreach ($pending_bonuses as $pb) {
    // Hétköznapi napi bónusz: daily_start_time-től aktiválható, admin_force_active átugorja
    $adminForce = !empty($pb['admin_force_active']);
    if (!empty($pb['valid_weekdays_only']) && !$adminForce) {
        $dailyStart = $pb['daily_start_time'] ?? '00:01:00';
        $isAfterDailyStart = (date('H:i:s') >= $dailyStart);
        $isWeekdayWindow = ($isWeekday && $isAfterDailyStart);
        if (!$isWeekdayWindow) {
            continue;
        }

        $todayStart = new DateTime(date('Y-m-d') . ' ' . $dailyStart);
        $tomorrowStart = (clone $todayStart)->modify('+1 day');
        $claimedAt = new DateTime($pb['created_at']);
        if ($claimedAt < $todayStart || $claimedAt >= $tomorrowStart) {
            continue;
        }
    } elseif (!empty($pb['valid_weekdays_only']) && $adminForce) {
        // admin force: nem ellenőrízzük a hétköznap/idő feltételt
    }

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
    $expireHours = (int)($pb['activation_expire_hours'] ?? 0);
    if (!empty($pb['valid_weekdays_only'])) {
        $daysUntilFriday = max(0, 5 - (int)date('N'));
        $expires_at = date('Y-m-d 23:59:00', strtotime('+' . $daysUntilFriday . ' day'));
    } elseif ($expireHours > 0) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $expireHours . ' hours'));
    }

    // UserBonuses aktiválása: jóváírás után ACTIVE állapot, forgatás követhető marad
    $isFreeBetReward = (strtoupper((string)($pb['bet_reward_type'] ?? '')) === 'FREE_BET');
    $bonusMoneyAmount = $isFreeBetReward ? 0.00 : $granted;
    $freeBetAmount = $isFreeBetReward ? $granted : 0.00;

    $activate_stmt = $conn->prepare(" 
        UPDATE UserBonuses
        SET status = 'ACTIVE',
            granted_amount = ?,
            bonus_money_amount = ?,
            free_bet_amount = ?,
            wagering_required = ?,
            expires_at = ?
        WHERE id = ?
    ");
    $activate_stmt->bind_param("ddddsi", $granted, $bonusMoneyAmount, $freeBetAmount, $wagering, $expires_at, $pb['user_bonus_id']);
    $activate_stmt->execute();
    $activate_stmt->close();

    $bonus_credited_total += $granted;
    // Minden BONUS_MONEY típusú bónusz a bonus_balance-ra kerül (forgatási kötelezettséggel).
    // FREE_BET típusnál a free_bet_amount mező kezeli, nem kerül egyenlegre.
    if (!$isFreeBetReward) {
        $bonus_credit_to_bonus_balance += $granted;
    }
}

// Bónusz jóváírás a bonus_balance-ra (forgatási kötelezettséggel)
if ($bonus_credit_to_bonus_balance > 0) {
    $bonus_update_stmt = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
    $bonus_update_stmt->bind_param("di", $bonus_credit_to_bonus_balance, $user_id);
    $bonus_update_stmt->execute();
    $bonus_update_stmt->close();
}

// Success - set session message and redirect
$success_msg = '✅ Befizetés sikeres! +' . number_format($amount, 0, ',', ' ') . ' FT';
if ($bonus_credited_total > 0) {
    $success_msg .= ' | 🎁 Bónusz jóváírva: +' . number_format($bonus_credited_total, 0, ',', ' ') . ' FT';
}
$_SESSION['success_message'] = $success_msg;

// Email küldése a felhasználónak a sikeres befizetésről
try {
    $userStmt = $conn->prepare("SELECT username, email, full_name FROM Users WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if ($userData && !empty($userData['email'])) {
        $methodLabels = [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'paypal' => 'PayPal',
        ];
        $methodLabel = $methodLabels[$payment_method_label] ?? $payment_method_label;
        $bonusRow = '';
        if ($bonus_credited_total > 0) {
            $bonusRow = '<tr><td style="padding:8px 0;font-weight:bold;color:#555;">Bónusz jóváírva:</td><td style="padding:8px 0;color:#28a745;font-weight:bold;">+' . number_format($bonus_credited_total, 0, ',', ' ') . ' FT</td></tr>';
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_SMTP_USERNAME;
        $mail->Password   = MAIL_SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($userData['email'], $userData['full_name'] ?? $userData['username']);
        $mail->isHTML(true);
        $mail->Subject = 'Sikeres befizetés - ' . number_format($amount, 0, ',', ' ') . ' FT | BetMatchBonus';
        $mail->Body = '
        <html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;">
        <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            <div style="background:#28a745;color:#fff;padding:20px 30px;">
                <h2 style="margin:0;">✅ Sikeres befizetés</h2>
            </div>
            <div style="padding:25px 30px;">
                <p style="color:#333;font-size:15px;">Kedves <strong>' . htmlspecialchars($userData['username']) . '</strong>,</p>
                <p style="color:#555;">A befizetésed sikeresen feldolgozásra került. Az összeg jóváírva az egyenlegeden.</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Tranzakció ID:</td><td style="padding:8px 0;font-family:monospace;">' . htmlspecialchars($transaction_id) . '</td></tr>
                    <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Összeg:</td><td style="padding:8px 0;font-size:1.2rem;font-weight:bold;color:#28a745;">+' . number_format($amount, 0, ',', ' ') . ' FT</td></tr>
                    <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Fizetési mód:</td><td style="padding:8px 0;">' . htmlspecialchars($methodLabel) . '</td></tr>
                    <tr><td style="padding:8px 0;font-weight:bold;color:#555;">Dátum:</td><td style="padding:8px 0;">' . date('Y.m.d H:i') . '</td></tr>
                    ' . $bonusRow . '
                </table>
                <p style="color:#888;font-size:13px;">Az igazolás letölthető a Tranzakciótörténet menüpontból.</p>
            </div>
            <div style="background:#f8f9fa;padding:15px 30px;text-align:center;color:#aaa;font-size:12px;">
                BetMatchBonus &copy; ' . date('Y') . ' | Ez egy automatikus értesítés.
            </div>
        </div>
        </body></html>';
        $mail->send();
    }
} catch (MailException $e) {
    // Email hiba nem blokkolja a sikeres befizetést
    error_log('Befizetési email küldés hiba: ' . $e->getMessage());
}

header('Location: /BetMatchBonus/frontend/UserProfile/deposit.php');
exit;
?>