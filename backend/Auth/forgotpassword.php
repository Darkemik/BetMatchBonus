<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');

if ($email === '' || $username === '') {
  echo json_encode(['success' => false, 'message' => 'E-mail cím és felhasználónév megadása kötelező!']);
  exit;
}

// Felhasználó keresése e-mail és felhasználónév alapján
$stmt = $conn->prepare("SELECT id, email, username FROM Users WHERE email = ? AND username = ? LIMIT 1");
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'Az e-mail cím és felhasználónév kombinációja nem található.']);
  exit;
}

// Óránkénti limit ellenőrzése (max 3 kérés / óra)
$limitStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM UserLogs WHERE user_id = ? AND action = 'password_reset_request' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$limitStmt->bind_param("i", $user['id']);
$limitStmt->execute();
$limitRes = $limitStmt->get_result()->fetch_assoc();
$limitStmt->close();

if ((int)($limitRes['cnt'] ?? 0) >= 3) {
    echo json_encode(['success' => false, 'message' => 'Túl sok jelszó-visszaállítási kérés! Legfeljebb 3 kérés engedélyezett óránként. Kérjük, próbáld újra később.']);
    exit;
}

// Jelszó reset token generálása
$resetToken = bin2hex(random_bytes(32));

// Token mentése az adatbázisba
$stmt = $conn->prepare("UPDATE Users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
$stmt->bind_param("si", $resetToken, $user['id']);
$stmt->execute();
$stmt->close();

// Kérés naplózása a rate limit-hez
$logStmt = $conn->prepare("INSERT INTO UserLogs (user_id, action, details, created_at) VALUES (?, 'password_reset_request', 'Jelszó-visszaállítási email kérés', NOW())");
$logStmt->bind_param("i", $user['id']);
$logStmt->execute();
$logStmt->close();

// Email küldése a jelszó-visszaállító linkkel
$resetUrl = SITE_BASE_URL . '/frontend/Auth/reset_password.php?token=' . $resetToken;

$emailBody  = "<h2>Jelszó visszaállítás – BetMatchBonus</h2>";
$emailBody .= "<p>Kedves <b>" . htmlspecialchars($user['username']) . "</b>!</p>";
$emailBody .= "<p>Jelszó-visszaállítási kérelmet kaptunk a fiókodhoz. Az alábbi gombra kattintva beállíthatsz egy új jelszót:</p>";
$emailBody .= "<p><a href='" . htmlspecialchars($resetUrl) . "' style='display:inline-block;padding:12px 24px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px;'>Új jelszó beállítása</a></p>";
$emailBody .= "<p style='color:#888;font-size:13px;'>A link 1 órán belül lejár. Ha nem te kérted a jelszó-visszaállítást, hagyd figyelmen kívül ezt az e-mailt.</p>";
$emailBody .= "<br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>";

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
    $mail->addAddress($user['email'], $user['username']);

    $mail->isHTML(true);
    $mail->Subject = 'Jelszó visszaállítás – BetMatchBonus';
    $mail->Body    = $emailBody;

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'E-mail sikeresen elküldve! Kérjük, ellenőrizd a postafiókod és kövesd a kapott linket az új jelszó beállításához.'
    ]);
} catch (MailException $e) {
    error_log('Jelszó-visszaállítási email hiba: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt az e-mail küldése során. Kérjük, próbáld újra később.'
    ]);
}
?>
