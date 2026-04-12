<?php
/**
 * Törölt felhasználó visszajelzésének küldése az adminnak.
 * POST: reason=...
 * 
 * Ez a fiók törlése UTÁN hívódik, a session már nem létezik.
 */
header('Content-Type: application/json; charset=utf-8');

$reason = trim($_POST['reason'] ?? '');
$username = trim($_POST['username'] ?? 'Ismeretlen');
$email = trim($_POST['email'] ?? 'Ismeretlen');

if ($reason === '') {
    echo json_encode(['success' => true, 'message' => 'Nincs visszajelzés.']);
    exit;
}

require_once __DIR__ . '/../../mail_config.php';
require_once __DIR__ . '/../../PHPMailer/Exception.php';
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

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
    $mail->Subject = 'Fióktörlés visszajelzés – ' . $username;
    $mail->Body = '
        <h2 style="color:#f5c518;">Visszajelzés fióktörlésről</h2>
        <p>A következő felhasználó törölte a fiókját és visszajelzést hagyott:</p>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;">
            <tr><td><strong>Felhasználónév</strong></td><td>' . htmlspecialchars($username) . '</td></tr>
            <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($email) . '</td></tr>
        </table>
        <p><strong>Törlés oka:</strong></p>
        <div style="background:#f8f9fa;padding:16px;border-radius:8px;border-left:4px solid #f5c518;margin:16px 0;">
            ' . nl2br(htmlspecialchars($reason)) . '
        </div>
        <p style="color:#888;font-size:12px;">Időpont: ' . date('Y-m-d H:i:s') . '</p>
    ';

    $mail->send();
    echo json_encode(['success' => true]);
} catch (MailException $e) {
    error_log('Fióktörlés visszajelzés email hiba: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Email küldési hiba.']);
}
