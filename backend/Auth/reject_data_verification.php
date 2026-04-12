<?php
/**
 * Személyes adatok ellenőrzésének ELUTASÍTÁSA – az emailben kapott link hívja meg.
 * GET ?token=XXXXX
 * POST token=XXXXX&reason=...
 */
require_once __DIR__ . '/../connect.php';

$token = $_REQUEST['token'] ?? '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    showPage('Érvénytelen link', 'Az elutasító link érvénytelen vagy hiányzik.', false);
    exit;
}

// Token keresése
$stmt = $conn->prepare("SELECT id, username, email, data_verified FROM Users WHERE data_verification_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    showPage('Nem található', 'Ez a link már felhasználásra került, vagy nem létezik.', false);
    exit;
}

if ((int)$user['data_verified'] === 1) {
    showPage('Már jóváhagyva', 'A felhasználó <b>' . htmlspecialchars($user['username']) . '</b> adatai már korábban jóváhagyásra kerültek.', true);
    exit;
}

// Ha GET: elutasítás űrlap megjelenítése (ok megadása)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    showRejectForm($token, htmlspecialchars($user['username']));
    exit;
}

// POST: elutasítás végrehajtása
$reason = trim($_POST['reason'] ?? '');
if ($reason === '') {
    showRejectForm($token, htmlspecialchars($user['username']), 'Kérjük, add meg az elutasítás okát!');
    exit;
}

// Elutasítás: data_rejected_at + reason mentése, token törlése
$upd = $conn->prepare("UPDATE Users SET data_rejected_at = NOW(), data_rejection_reason = ?, data_verification_token = NULL WHERE id = ?");
$upd->bind_param("si", $reason, $user['id']);
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
    $mail->Subject = 'BetMatchBonus – Adatellenőrzés elutasítva';
    $mail->Body    = "<div style='font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;color:#222;padding:24px;'>"
                   . "<h2 style='color:#1a1a2e;'>Kedves " . htmlspecialchars($user['username']) . "!</h2>"
                   . "<p style='color:#333;font-size:15px;'>Sajnálattal értesítünk, hogy a személyes adataid ellenőrzése <strong style='color:#dc3545;'>elutasításra került</strong>.</p>"
                   . "<div style='background:#fff3f3;border-left:4px solid #dc3545;padding:12px 16px;margin:16px 0;border-radius:4px;color:#333;'>"
                   . "<strong>Elutasítás oka:</strong><br>" . nl2br(htmlspecialchars($reason))
                   . "</div>"
                   . "<p style='color:#333;font-size:15px;'>Az elutasítás után <strong>15 percen belül</strong> újra beküldheted az adataidat javítva.</p>"
                   . "<p><a href='" . htmlspecialchars(SITE_BASE_URL) . "/frontend/UserProfile/personal_data.php' "
                   . "style='display:inline-block;padding:12px 24px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;'>Adatok Javítása</a></p>"
                   . "<br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>"
                   . "</div>";

    $mail->send();
} catch (MailException $e) {
    error_log('Adatellenőrzés elutasítás email hiba: ' . $e->getMessage());
}

showPage(
    'Adatok elutasítva',
    'A felhasználó <b>' . htmlspecialchars($user['username']) . '</b> (' . htmlspecialchars($user['email']) . ') adatellenőrzése elutasítva.<br>A felhasználó értesítést kapott emailben az okkal.',
    false
);

$conn->close();

// ---- Elutasítás űrlap ----
function showRejectForm(string $token, string $username, string $error = ''): void {
    $errorHtml = $error ? "<p style='color:#dc3545;font-weight:bold;'>⚠️ {$error}</p>" : '';
    echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Adatok Elutasítása – BetMatchBonus</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #16213e; padding: 40px; border-radius: 12px; max-width: 520px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .card h1 { color: #dc3545; margin-bottom: 8px; }
        .card p { color: #ccc; }
        textarea { width: 100%; min-height: 100px; background: #1a1a2e; color: #eee; border: 1px solid #444; border-radius: 6px; padding: 10px; font-size: 14px; resize: vertical; }
        textarea:focus { outline: none; border-color: #e94560; }
        .btn-reject { display: inline-block; padding: 12px 24px; background: #dc3545; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 12px; }
        .btn-reject:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='card'>
        <h1>❌ Adatok Elutasítása</h1>
        <p>Felhasználó: <strong>{$username}</strong></p>
        {$errorHtml}
        <form method='POST'>
            <input type='hidden' name='token' value='{$token}'>
            <label style='display:block;margin-bottom:6px;color:#aaa;'>Elutasítás oka:</label>
            <textarea name='reason' placeholder='Pl.: Hiányzó bankszámlakivonat, nem egyezik a név...' required></textarea>
            <button type='submit' class='btn-reject'>❌ Elutasítás Küldése</button>
        </form>
    </div>
</body>
</html>";
    exit;
}

// ---- Eredmény oldal ----
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
