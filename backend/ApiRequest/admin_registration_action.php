<?php
/**
 * Admin regisztráció kezelés API
 * POST actions: approve, reject
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/audit_helper.php';
require_once __DIR__ . '/../Auth/permission_helper.php';
page_permission_guard('registrations');

require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$userId = (int)($input['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
    exit;
}

// Fetch pending user
$stmt = $conn->prepare("SELECT id, username, email, full_name, is_active, is_verified, approval_token FROM Users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'A felhasználó nem található.']);
    exit;
}

function getConfiguredMailer(): PHPMailer {
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
    return $mail;
}

/* ━━━━━ APPROVE ━━━━━ */
if ($action === 'approve') {
    if ((int)$user['is_active'] === 1 && (int)$user['is_verified'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználó már jóváhagyásra került.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE Users SET is_active = 1, is_verified = 1, approval_token = NULL WHERE id = ?");
    $upd->bind_param("i", $userId);
    $upd->execute();
    $upd->close();

    // Email küldés
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($user['email'], $user['username']);
        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Fiókod aktiválva!';
        $mail->Body = '
        <div style="max-width:520px;margin:0 auto;background:#1a1a2e;border-radius:12px;overflow:hidden;font-family:Segoe UI,sans-serif;">
            <div style="background:#28a745;padding:24px;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:6px;">✅</div>
                <h2 style="color:#fff;margin:0;font-size:1.3rem;">Regisztrációd jóváhagyva!</h2>
            </div>
            <div style="padding:24px;color:#ddd;text-align:center;">
                <p style="font-size:1rem;">Kedves <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                <p>Örömmel értesítünk, hogy a regisztrációdat jóváhagytuk. Most már bejelentkezhetsz a fiókodba!</p>
                <a href="' . htmlspecialchars(SITE_BASE_URL) . '/frontend/MainMenu/MainMenu.php"
                   style="display:inline-block;padding:12px 28px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;margin:16px 0;">
                    Belépés
                </a>
            </div>
            <div style="padding:12px;text-align:center;color:#666;font-size:0.75rem;border-top:1px solid #2a2a4a;">
                BetMatchBonus csapata
            </div>
        </div>';
        $mail->send();
    } catch (MailException $e) {
        error_log('Regisztráció jóváhagyás email hiba: ' . $e->getMessage());
    }

    log_audit('reg_approve', 'user', $userId, 'Regisztráció jóváhagyva: ' . $user['username']);
    echo json_encode(['success' => true, 'message' => 'Felhasználó jóváhagyva: ' . $user['username']]);
    exit;
}

/* ━━━━━ REJECT ━━━━━ */
if ($action === 'reject') {
    $reason = trim($input['reason'] ?? '');
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Kérjük, add meg az elutasítás okát!']);
        exit;
    }

    if ((int)$user['is_active'] === 1 && (int)$user['is_verified'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználó már jóváhagyásra került, nem utasítható el.']);
        exit;
    }

    $userEmail = $user['email'];
    $userName  = $user['username'];

    // Törlés tranzakcióban
    $conn->begin_transaction();
    try {
        $del = $conn->prepare("DELETE FROM Wallets WHERE user_id = ?");
        $del->bind_param("i", $userId);
        $del->execute();
        $del->close();

        $del2 = $conn->prepare("DELETE FROM UserBonuses WHERE user_id = ?");
        $del2->bind_param("i", $userId);
        $del2->execute();
        $del2->close();

        $del3 = $conn->prepare("DELETE FROM Users WHERE id = ?");
        $del3->bind_param("i", $userId);
        $del3->execute();
        $del3->close();

        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba a törlés során: ' . $e->getMessage()]);
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

    // Email küldés
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Regisztrációd elutasítva';
        $mail->Body = '
        <div style="max-width:520px;margin:0 auto;background:#1a1a2e;border-radius:12px;overflow:hidden;font-family:Segoe UI,sans-serif;">
            <div style="background:#dc3545;padding:24px;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:6px;">❌</div>
                <h2 style="color:#fff;margin:0;font-size:1.3rem;">Regisztrációd elutasítva</h2>
            </div>
            <div style="padding:24px;color:#ddd;">
                <p style="text-align:center;font-size:1rem;">Kedves <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                <p style="text-align:center;">Sajnálattal értesítünk, hogy a regisztrációs kérelmed elutasításra került.</p>
                <div style="background:#2a1a1a;padding:14px 18px;border-radius:8px;border-left:4px solid #dc3545;margin:16px 0;">
                    <strong style="color:#e94560;">Elutasítás oka:</strong><br>
                    <span style="color:#ccc;">' . nl2br(htmlspecialchars($reason)) . '</span>
                </div>
                <p style="text-align:center;color:#888;">Amennyiben úgy gondolod, hogy tévedés történt, kérjük vedd fel velünk a kapcsolatot.</p>
            </div>
            <div style="padding:12px;text-align:center;color:#666;font-size:0.75rem;border-top:1px solid #2a2a4a;">
                BetMatchBonus csapata
            </div>
        </div>';
        $mail->send();
    } catch (MailException $e) {
        error_log('Regisztráció elutasítás email hiba: ' . $e->getMessage());
    }

    log_audit('reg_reject', 'user', $userId, 'Regisztráció elutasítva: ' . $userName . ' – Ok: ' . $reason);
    echo json_encode(['success' => true, 'message' => 'Felhasználó elutasítva és törölve: ' . $userName]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet: ' . $action]);
