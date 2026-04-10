<?php
/**
 * Regisztráció jóváhagyása – az emailben kapott link hívja meg.
 * GET ?token=XXXXX
 */
require_once __DIR__ . '/../connect.php';

$token = $_GET['token'] ?? '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    showPage('Érvénytelen link', 'A jóváhagyó link érvénytelen vagy hiányzik.', false);
    exit;
}

// Token keresése
$stmt = $conn->prepare("SELECT id, username, email, is_active FROM Users WHERE approval_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    showPage('Nem található', 'Ez a jóváhagyó link már felhasználásra került, vagy nem létezik.', false);
    exit;
}

if ((int)$user['is_active'] === 1) {
    showPage('Már jóváhagyva', 'A felhasználó <b>' . htmlspecialchars($user['username']) . '</b> fiókja már korábban aktiválva lett.', true);
    exit;
}

// Jóváhagyás: is_active = 1, token törlése
$upd = $conn->prepare("UPDATE Users SET is_active = 1, is_verified = 1, approval_token = NULL WHERE id = ?");
$upd->bind_param("i", $user['id']);
$upd->execute();
$upd->close();

// Értesítő email küldése a felhasználónak
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

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
    $mail->addAddress($user['email'], $user['username']);

    $mail->isHTML(true);
    $mail->Subject = 'BetMatchBonus – Fiókod aktiválva!';
    $mail->Body    = "<h2>Kedves " . htmlspecialchars($user['username']) . "!</h2>"
                   . "<p>Örömmel értesítünk, hogy a regisztrációdat jóváhagytuk. Most már bejelentkezhetsz a fiókodba!</p>"
                   . "<p><a href='" . htmlspecialchars(SITE_BASE_URL) . "/frontend/MainMenu/MainMenu.php' "
                   . "style='display:inline-block;padding:12px 24px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;'>Belépés</a></p>"
                   . "<br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>";

    $mail->send();
} catch (MailException $e) {
    error_log('Jóváhagyás értesítő email hiba: ' . $e->getMessage());
}

showPage(
    'Regisztráció jóváhagyva!',
    'A felhasználó <b>' . htmlspecialchars($user['username']) . '</b> (' . htmlspecialchars($user['email']) . ') fiókja sikeresen aktiválva lett.<br>A felhasználó értesítést kapott emailben.',
    true
);

$conn->close();

// ---- HTML oldal megjelenítése ----
function showPage(string $title, string $message, bool $success): void {
    $color = $success ? '#28a745' : '#dc3545';
    $icon  = $success ? '✅' : '❌';
    echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title} – BetMatchBonus</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #16213e; padding: 40px; border-radius: 12px; text-align: center; max-width: 500px; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .card h1 { color: {$color}; margin-bottom: 16px; }
        .card .icon { font-size: 48px; margin-bottom: 12px; }
        .card p { font-size: 16px; line-height: 1.6; color: #ccc; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>{$icon}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
    </div>
</body>
</html>";
}
