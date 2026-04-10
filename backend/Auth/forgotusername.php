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
$birthdate = trim($_POST['birthdate'] ?? '');

if ($email === '' || $birthdate === '') {
  echo json_encode(['success' => false, 'message' => 'E-mail cím és születési dátum megadása kötelező!']);
  exit;
}

// Felhasználó keresése e-mail és születési dátum alapján
$stmt = $conn->prepare("SELECT id, email, username FROM Users WHERE email = ? AND DATE(birth_date) = ? LIMIT 1");
$stmt->bind_param("ss", $email, $birthdate);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'Az e-mail cím és születési dátum kombinációja nem található.']);
  exit;
}

// Email küldése a felhasználónévvel
$emailBody  = "<h2>Felhasználónév emlékeztető – BetMatchBonus</h2>";
$emailBody .= "<p>Kedves felhasználónk!</p>";
$emailBody .= "<p>A fiókodhoz tartozó felhasználónév:</p>";
$emailBody .= "<p style='font-size:24px;font-weight:bold;color:#f5c518;background:#1a1a2e;padding:16px 24px;border-radius:8px;display:inline-block;'>" . htmlspecialchars($user['username']) . "</p>";
$emailBody .= "<p>Ezzel a felhasználónévvel tudsz bejelentkezni a BetMatchBonus oldalra.</p>";
$emailBody .= "<p><a href='" . htmlspecialchars(SITE_BASE_URL) . "/frontend/MainMenu/MainMenu.php' style='display:inline-block;padding:12px 24px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;'>Belépés</a></p>";
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
    $mail->addAddress($user['email']);

    $mail->isHTML(true);
    $mail->Subject = 'Felhasználónév emlékeztető – BetMatchBonus';
    $mail->Body    = $emailBody;

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'E-mail sikeresen elküldve! Kérjük, ellenőrizd a postafiókod – ott megtalálod a felhasználónevedet.'
    ]);
} catch (MailException $e) {
    error_log('Felhasználónév emlékeztető email hiba: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt az e-mail küldése során. Kérjük, próbáld újra később.'
    ]);
}
?>
