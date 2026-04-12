<?php
/**
 * Kifizetési kérelem jóváhagyása/elutasítása – az emailben kapott link hívja meg.
 * GET ?token=XXXXX&action=approve|reject
 */
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    showPage('Érvénytelen link', 'A jóváhagyó link érvénytelen vagy hiányzik.', false);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    showPage('Érvénytelen művelet', 'A művelet csak "approve" vagy "reject" lehet.', false);
    exit;
}

// Token keresése a Transactions táblában
$stmt = $conn->prepare("
    SELECT t.id, t.user_id, t.amount, t.status, t.transaction_id, t.account_holder, t.account_number,
           u.username, u.email, u.balance, u.winnings_balance
    FROM Transactions t
    INNER JOIN Users u ON u.id = t.user_id
    WHERE t.approval_token = ? AND t.type = 'withdrawal'
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$tx = $result->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    showPage('Nem található', 'Ez a kifizetési kérelem nem létezik, vagy a link már felhasználásra került.', false);
    exit;
}

if ($tx['status'] !== 'pending') {
    $statusText = $tx['status'] === 'completed' ? 'jóváhagyva' : ($tx['status'] === 'rejected' ? 'elutasítva' : $tx['status']);
    showPage('Már feldolgozva', 'Ez a kifizetési kérelem már korábban <b>' . $statusText . '</b> lett. (' . htmlspecialchars($tx['transaction_id']) . ')', true);
    exit;
}

$amountFormatted = number_format((float)$tx['amount'], 0, ',', ' ');

if ($action === 'approve') {
    // Jóváhagyás: státusz frissítése, token törlése
    $upd = $conn->prepare("UPDATE Transactions SET status = 'completed', approval_token = NULL, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("i", $tx['id']);
    $upd->execute();
    $upd->close();

    // Értesítő email a felhasználónak
    sendUserEmail(
        $tx['email'],
        $tx['username'],
        '✅ Kifizetésed jóváhagyva! - ' . $amountFormatted . ' FT',
        '<div style="text-align:center;padding:20px;">
            <div style="font-size:3rem;margin-bottom:10px;">✅</div>
            <h2 style="color:#28a745;margin:0 0 10px;">Kifizetésed jóváhagyva!</h2>
            <p style="color:#555;font-size:1rem;">Kedves <strong>' . htmlspecialchars($tx['username']) . '</strong>,</p>
            <p style="color:#333;font-size:1.2rem;font-weight:bold;">' . $amountFormatted . ' FT</p>
            <p style="color:#555;">Az összeg hamarosan megérkezik a bankszámládra:<br><strong style="font-family:monospace;">' . htmlspecialchars($tx['account_number'] ?? '') . '</strong></p>
            <p style="color:#999;font-size:0.85rem;">Tranzakció azonosító: ' . htmlspecialchars($tx['transaction_id']) . '</p>
        </div>'
    );

    showPage('Kifizetés jóváhagyva', '
        <b>' . htmlspecialchars($tx['username']) . '</b> kifizetési kérelme jóváhagyva!<br><br>
        Összeg: <b>' . $amountFormatted . ' FT</b><br>
        Bankszámlaszám: <b>' . htmlspecialchars($tx['account_number'] ?? '') . '</b><br>
        Tranzakció: <b>' . htmlspecialchars($tx['transaction_id']) . '</b><br><br>
        A felhasználó emailben értesítve lett.
    ', true);

} else {
    // Elutasítás: ha GET, akkor űrlapot mutatunk az ok megadásához
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        showRejectForm($token, htmlspecialchars($tx['username']), $amountFormatted);
        exit;
    }

    // POST: elutasítás végrehajtása
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        showRejectForm($token, htmlspecialchars($tx['username']), $amountFormatted, 'Kérjük, add meg az elutasítás okát!');
        exit;
    }

    // Egyenleg visszaírása, státusz frissítése, ok mentése, token törlése
    $conn->begin_transaction();
    try {
        $upd = $conn->prepare("UPDATE Transactions SET status = 'rejected', approval_token = NULL, rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param("si", $reason, $tx['id']);
        $upd->execute();
        $upd->close();

        // Egyenleg visszaírása
        $hasWinnings = ($tx['winnings_balance'] !== null);
        if ($hasWinnings) {
            $balUpd = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
            $balUpd->bind_param("ddi", $tx['amount'], $tx['amount'], $tx['user_id']);
        } else {
            $balUpd = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
            $balUpd->bind_param("di", $tx['amount'], $tx['user_id']);
        }
        $balUpd->execute();
        $balUpd->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        showPage('Hiba', 'Hiba történt az elutasítás során: ' . htmlspecialchars($e->getMessage()), false);
        exit;
    }

    // Értesítő email a felhasználónak
    sendUserEmail(
        $tx['email'],
        $tx['username'],
        '❌ Kifizetésed elutasítva - ' . $amountFormatted . ' FT',
        '<div style="text-align:center;padding:20px;">
            <div style="font-size:3rem;margin-bottom:10px;">❌</div>
            <h2 style="color:#ff6b6b;margin:0 0 10px;">Kifizetésed elutasítva</h2>
            <p style="color:#ccc;font-size:1rem;">Kedves <strong style="color:#f5c518;">' . htmlspecialchars($tx['username']) . '</strong>,</p>
            <p style="color:#ddd;font-size:1.1rem;">A kifizetési kérelmed (<strong style="color:#f5c518;">' . $amountFormatted . ' FT</strong>) elutasításra került.</p>
            <div style="background:#16213e;border-left:4px solid #dc3545;padding:12px 16px;margin:16px 0;border-radius:4px;color:#eee;text-align:left;">
                <strong style="color:#ff6b6b;">Elutasítás oka:</strong><br>' . nl2br(htmlspecialchars($reason)) . '
            </div>
            <p style="color:#ccc;">Az összeg visszakerült az egyenlegedre. Ha kérdésed van, keresd ügyfélszolgálatunkat.</p>
            <p style="color:#888;font-size:0.85rem;">Tranzakció azonosító: ' . htmlspecialchars($tx['transaction_id']) . '</p>
        </div>',
        true
    );

    showPage('Kifizetés elutasítva', '
        <b>' . htmlspecialchars($tx['username']) . '</b> kifizetési kérelme elutasítva.<br><br>
        Összeg: <b>' . $amountFormatted . ' FT</b> — visszaírva az egyenlegre.<br>
        Tranzakció: <b>' . htmlspecialchars($tx['transaction_id']) . '</b><br>
        Ok: <b>' . htmlspecialchars($reason) . '</b><br><br>
        A felhasználó emailben értesítve lett.
    ', true);
}

// ════════════════════════════════════════
// SEGÉDFÜGGVÉNYEK
// ════════════════════════════════════════
function sendUserEmail($toEmail, $toName, $subject, $bodyContent, $dark = false) {
    $bgOuter = $dark ? '#0d1117' : '#f4f4f4';
    $bgCard  = $dark ? '#1a1a2e' : '#fff';
    $bgHeader = $dark ? '#16213e' : '#007bff';
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
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = '
        <html><body style="font-family:Arial,sans-serif;background:' . $bgOuter . ';padding:20px;">
        <div style="max-width:500px;margin:0 auto;background:' . $bgCard . ';border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.3);">
            <div style="background:' . $bgHeader . ';color:#fff;padding:15px 25px;text-align:center;">
                <h3 style="margin:0;">BetMatchBonus</h3>
            </div>
            <div style="padding:20px 25px;">' . $bodyContent . '</div>
        </div>
        </body></html>';
        $mail->send();
    } catch (MailException $e) {
        error_log('Withdrawal notification email error: ' . $e->getMessage());
    }
}

function showRejectForm($token, $username, $amount, $error = '') {
    $errorHtml = $error ? '<div style="background:rgba(220,53,69,0.15);color:#ff6b6b;padding:10px;border-radius:6px;margin-bottom:15px;font-weight:600;">' . htmlspecialchars($error) . '</div>' : '';
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Kifizetés elutasítása | BetMatchBonus</title>
    <style>body{font-family:Arial,sans-serif;background:#0d1117;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;}
    .card{background:#1a1a2e;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.4);max-width:540px;width:90%;padding:40px;text-align:center;}
    .icon{font-size:3rem;margin-bottom:10px;} h2{color:#ff6b6b;margin:0 0 10px;} .info{color:#ccc;margin-bottom:20px;} .info strong{color:#f5c518;}
    textarea{width:100%;min-height:100px;padding:10px;border:2px solid #2a2a4a;border-radius:8px;font-size:1rem;font-family:inherit;resize:vertical;box-sizing:border-box;background:#16213e;color:#eee;}
    textarea:focus{border-color:#f5c518;outline:none;}
    .btn{display:inline-block;padding:12px 30px;background:#dc3545;color:#fff;border:none;border-radius:8px;font-size:1rem;cursor:pointer;margin-top:15px;font-weight:600;}
    .btn:hover{background:#c82333;}</style>
    </head><body><div class="card">
    <div class="icon">⚠️</div>
    <h2>Kifizetés elutasítása</h2>
    <p class="info">Felhasználó: <strong>' . $username . '</strong><br>Összeg: <strong>' . $amount . ' FT</strong></p>
    ' . $errorHtml . '
    <form method="POST" action="?token=' . htmlspecialchars(urlencode($token)) . '&action=reject">
        <textarea name="reason" placeholder="Add meg az elutasítás okát..." required>' . htmlspecialchars($_POST['reason'] ?? '') . '</textarea>
        <br><button type="submit" class="btn">Elutasítás véglegesítése</button>
    </form>
    </div></body></html>';
}

function showPage($title, $message, $success) {
    $color = $success ? '#28a745' : '#dc3545';
    $icon = $success ? '✅' : '❌';
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' | BetMatchBonus</title>
    <style>body{font-family:Arial,sans-serif;background:#f4f4f4;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;}
    .card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);max-width:500px;width:90%;padding:40px;text-align:center;}
    .icon{font-size:3rem;margin-bottom:15px;} h2{color:' . $color . ';margin:0 0 15px;} .msg{color:#555;line-height:1.6;}</style>
    </head><body><div class="card"><div class="icon">' . $icon . '</div><h2>' . htmlspecialchars($title) . '</h2><div class="msg">' . $message . '</div></div></body></html>';
}
