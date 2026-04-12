<?php
/**
 * Regisztráció elutasítása – az emailben kapott link hívja meg.
 * GET  ?token=XXXXX           → űrlap az ok megadásához
 * POST token=XXXXX&reason=... → elutasítás végrehajtása
 */
require_once __DIR__ . '/../connect.php';

$token = $_REQUEST['token'] ?? '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    showPage('Érvénytelen link', 'Az elutasító link érvénytelen vagy hiányzik.', false);
    exit;
}

// Token keresése
$stmt = $conn->prepare("SELECT id, username, email, full_name, is_active, approval_token FROM Users WHERE approval_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    showPage('Nem található', 'Ez a link már felhasználásra került, vagy a felhasználó nem létezik.', false);
    exit;
}

if ((int)$user['is_active'] === 1) {
    showPage('Már jóváhagyva', 'A felhasználó <b>' . htmlspecialchars($user['username']) . '</b> fiókja már korábban aktiválva lett, ezért nem utasítható el.', true);
    exit;
}

// GET → űrlap megjelenítése az elutasítás okának megadásához
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    showRejectForm($token, htmlspecialchars($user['username']), htmlspecialchars($user['email']));
    exit;
}

// POST → elutasítás végrehajtása
$reason = trim($_POST['reason'] ?? '');
if ($reason === '') {
    showRejectForm($token, htmlspecialchars($user['username']), htmlspecialchars($user['email']), 'Kérjük, add meg az elutasítás okát!');
    exit;
}

$userEmail    = $user['email'];
$userName     = $user['username'];
$userId       = (int)$user['id'];

// Kapcsolódó adatok törlése (wallet, bónuszok, feltöltött fájlok)
$conn->begin_transaction();
try {
    // Wallet törlése
    $delWallet = $conn->prepare("DELETE FROM Wallets WHERE user_id = ?");
    $delWallet->bind_param("i", $userId);
    $delWallet->execute();
    $delWallet->close();

    // Bónuszok törlése
    $delBonuses = $conn->prepare("DELETE FROM UserBonuses WHERE user_id = ?");
    $delBonuses->bind_param("i", $userId);
    $delBonuses->execute();
    $delBonuses->close();

    // Felhasználó törlése
    $delUser = $conn->prepare("DELETE FROM Users WHERE id = ?");
    $delUser->bind_param("i", $userId);
    $delUser->execute();
    $delUser->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    showPage('Hiba', 'Hiba történt az elutasítás során: ' . htmlspecialchars($e->getMessage()), false);
    exit;
}

// Feltöltött fájlok törlése
$uploadDir = __DIR__ . '/../uploads/registrations/' . $userId . '/';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($uploadDir);
}

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
    $mail->addAddress($userEmail, $userName);

    $mail->isHTML(true);
    $mail->Subject = 'BetMatchBonus – Regisztrációd elutasítva';
    $mail->Body    = "<div style='text-align:center;padding:20px;'>
        <div style='font-size:3rem;margin-bottom:10px;'>❌</div>
        <h2 style='color:#dc3545;margin:0 0 10px;'>Regisztrációd elutasítva</h2>
        <p style='color:#555;font-size:1rem;'>Kedves <strong>" . htmlspecialchars($userName) . "</strong>,</p>
        <p style='color:#555;'>Sajnálattal értesítünk, hogy a regisztrációs kérelmed elutasításra került.</p>
        <div style='background:#f8d7da;padding:12px 16px;border-radius:8px;border-left:4px solid #dc3545;color:#721c24;margin:16px auto;max-width:400px;text-align:left;'>
            <strong>Elutasítás oka:</strong><br>" . nl2br(htmlspecialchars($reason)) . "
        </div>
        <p style='color:#555;'>Amennyiben úgy gondolod, hogy tévedés történt, kérjük vedd fel velünk a kapcsolatot.</p>
        <br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
    </div>";

    $mail->send();
} catch (MailException $e) {
    error_log('Regisztráció elutasítás értesítő email hiba: ' . $e->getMessage());
}

showPage(
    'Regisztráció elutasítva',
    'A felhasználó <b>' . htmlspecialchars($userName) . '</b> (' . htmlspecialchars($userEmail) . ') regisztrációja elutasításra került.<br><br>'
    . '<b>Elutasítás oka:</b><br>' . nl2br(htmlspecialchars($reason)) . '<br><br>'
    . 'A felhasználó fiókja és adatai törlésre kerültek. A felhasználó értesítést kapott emailben.',
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

// ---- Elutasítás ok megadásának űrlapja ----
function showRejectForm(string $token, string $username, string $email, string $error = ''): void {
    $errorHtml = $error !== '' ? "<div style='background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;margin-bottom:16px;'>{$error}</div>" : '';
    echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Regisztráció elutasítása – BetMatchBonus</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #16213e; padding: 40px; border-radius: 12px; text-align: center; max-width: 500px; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .card h1 { color: #dc3545; margin-bottom: 16px; }
        .card .icon { font-size: 48px; margin-bottom: 12px; }
        .card p { font-size: 16px; line-height: 1.6; color: #ccc; }
        textarea { width: 100%; min-height: 100px; border-radius: 8px; border: 1px solid #2a2a3e; background: #1a1a2e; color: #f5c518; padding: 12px; font-size: 14px; resize: vertical; }
        textarea:focus { outline: none; border-color: #f5c518; }
        .btn-reject { display: inline-block; padding: 12px 24px; background: #dc3545; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 12px; }
        .btn-reject:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>❌</div>
        <h1>Regisztráció elutasítása</h1>
        <p>Felhasználó: <b>{$username}</b><br>Email: <b>{$email}</b></p>
        {$errorHtml}
        <form method='POST' action='reject_registration.php?token=" . htmlspecialchars($token) . "'>
            <input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>
            <textarea name='reason' placeholder='Add meg az elutasítás okát...' required></textarea>
            <br>
            <button type='submit' class='btn-reject'>❌ Regisztráció elutasítása</button>
        </form>
    </div>
</body>
</html>";
    exit;
}
