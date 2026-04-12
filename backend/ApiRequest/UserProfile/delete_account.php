<?php
/**
 * Felhasználó fiók törlése
 * POST: delete_confirmed=1, reason=... (opcionális)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nem vagy bejelentkezve.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

if (empty($_POST['delete_confirmed'])) {
    echo json_encode(['success' => false, 'message' => 'A törlés nincs megerősítve.']);
    exit;
}

$password = $_POST['password'] ?? '';
if ($password === '') {
    echo json_encode(['success' => false, 'message' => 'A jelszó megadása kötelező.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Felhasználó adatainak lekérése a törlés előtt (jelszó ellenőrzés + emailhez)
$stmt = $conn->prepare("SELECT username, email, full_name, password_hash, balance, winnings_balance, bonus_balance FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
    exit;
}

// Jelszó ellenőrzés
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Hibás jelszó! Próbáld újra.']);
    exit;
}

// Fiók törlése tranzakcióban
$conn->begin_transaction();
try {
    // Wallet törlése
    $del = $conn->prepare("DELETE FROM Wallets WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    $del->close();

    // Bónuszok törlése
    $del = $conn->prepare("DELETE FROM UserBonuses WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    $del->close();

    // Felhasználó törlése (CASCADE törli a többi kapcsolódó adatot is)
    $del = $conn->prepare("DELETE FROM Users WHERE id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    $del->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Hiba történt a törlés során.']);
    exit;
}

// Feltöltött fájlok törlése
$uploadDir = __DIR__ . '/../../uploads/registrations/' . $user_id . '/';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($uploadDir);
}

// Bankszámlakivonat törlése
$bankDir = __DIR__ . '/../../uploads/bank_statements/';
$bankFiles = glob($bankDir . 'bank_' . $user_id . '_*');
foreach ($bankFiles as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

// Session törlése
session_unset();
session_destroy();
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

// Email küldése az adminnak
require_once __DIR__ . '/../../mail_config.php';
require_once __DIR__ . '/../../PHPMailer/Exception.php';
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$balanceFormatted = number_format((float)($user['balance'] ?? 0), 0, ',', ' ');
$reason = trim($_POST['reason'] ?? '');
$reasonHtml = $reason !== ''
    ? '<tr><td><strong>Törlés oka</strong></td><td>' . nl2br(htmlspecialchars($reason)) . '</td></tr>'
    : '<tr><td><strong>Törlés oka</strong></td><td style="color:#999;">Nem adott meg okot</td></tr>';

try {
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
    $mail->addAddress('bmbugyfelszolgalat@gmail.com', 'BetMatchBonus Admin');

    $mail->isHTML(true);
    $mail->Subject = 'Fiók törölve – ' . $user['username'];
    $mail->Body = '
        <h2 style="color:#dc3545;">Felhasználó törölte a fiókját</h2>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
            <tr><td><strong>Felhasználónév</strong></td><td>' . htmlspecialchars($user['username']) . '</td></tr>
            <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>
            <tr><td><strong>Teljes név</strong></td><td>' . htmlspecialchars($user['full_name'] ?? '-') . '</td></tr>
            <tr><td><strong>Egyenleg törléskor</strong></td><td>' . $balanceFormatted . ' Ft</td></tr>
            ' . $reasonHtml . '
            <tr><td><strong>Törlés ideje</strong></td><td>' . date('Y-m-d H:i:s') . '</td></tr>
        </table>
    ';

    $mail->send();
} catch (MailException $e) {
    error_log('Fióktörlés admin értesítő email hiba: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'A fiókod sikeresen törlésre került.', 'username' => $user['username'], 'email' => $user['email']]);
