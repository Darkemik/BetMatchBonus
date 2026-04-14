<?php
/**
 * Admin személyes adatok ellenőrzése API
 * POST actions: approve, reject
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('MOD');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/audit_helper.php';
require_once __DIR__ . '/../Auth/permission_helper.php';
page_permission_guard('data_verification');

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

// Felhasználó lekérése
$stmt = $conn->prepare("
    SELECT id, username, email, full_name, data_verified, data_verification_token,
           country, city, postal_code, address, bank_statement_file
    FROM Users WHERE id = ?
");
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
    if ((int)$user['data_verified'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználó adatai már jóváhagyásra kerültek.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE Users SET data_verified = 1, data_verification_token = NULL, data_rejected_at = NULL, data_rejection_reason = NULL WHERE id = ?");
    $upd->bind_param("i", $userId);
    $upd->execute();
    $upd->close();

    // Email küldés
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($user['email'], $user['username']);
        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Adataid ellenőrizve!';
        $mail->Body = '
        <div style="max-width:520px;margin:0 auto;background:#1a1a2e;border-radius:12px;overflow:hidden;font-family:Segoe UI,sans-serif;">
            <div style="background:#28a745;padding:24px;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:6px;">✅</div>
                <h2 style="color:#fff;margin:0;font-size:1.3rem;">Személyes adataid jóváhagyva!</h2>
            </div>
            <div style="padding:24px;color:#ddd;text-align:center;">
                <p style="font-size:1rem;">Kedves <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                <p>Örömmel értesítünk, hogy a személyes adataidat ellenőriztük és jóváhagytuk.</p>
                <p>Mostantól kifizetést is kezdeményezhetsz a fiókodból!</p>
                <a href="' . htmlspecialchars(SITE_BASE_URL) . '/frontend/UserProfile/withdrawal.php"
                   style="display:inline-block;padding:12px 28px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;margin:16px 0;">
                    Kifizetés
                </a>
            </div>
            <div style="padding:12px;text-align:center;color:#666;font-size:0.75rem;border-top:1px solid #2a2a4a;">
                BetMatchBonus csapata
            </div>
        </div>';
        $mail->send();
    } catch (MailException $e) {
        error_log('Adatellenőrzés jóváhagyás email hiba: ' . $e->getMessage());
    }

    log_audit('data_verify_approve', 'user', $userId, 'Személyes adatok jóváhagyva: ' . $user['username']);
    echo json_encode(['success' => true, 'message' => 'Adatok jóváhagyva: ' . $user['username']]);
    exit;
}

/* ━━━━━ REJECT ━━━━━ */
if ($action === 'reject') {
    $reason = trim($input['reason'] ?? '');
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Kérjük, add meg az elutasítás okát!']);
        exit;
    }

    if ((int)$user['data_verified'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználó adatai már jóváhagyásra kerültek, nem utasítható el.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE Users SET data_rejected_at = NOW(), data_rejection_reason = ?, data_verification_token = NULL WHERE id = ?");
    $upd->bind_param("si", $reason, $userId);
    $upd->execute();
    $upd->close();

    // Email küldés
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($user['email'], $user['username']);
        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Adatellenőrzés elutasítva';
        $mail->Body = '
        <div style="max-width:520px;margin:0 auto;background:#1a1a2e;border-radius:12px;overflow:hidden;font-family:Segoe UI,sans-serif;">
            <div style="background:#dc3545;padding:24px;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:6px;">❌</div>
                <h2 style="color:#fff;margin:0;font-size:1.3rem;">Adatellenőrzés elutasítva</h2>
            </div>
            <div style="padding:24px;color:#ddd;">
                <p style="font-size:1rem;">Kedves <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                <p>Sajnálattal értesítünk, hogy a személyes adataid ellenőrzése elutasításra került.</p>
                <div style="background:#2a1a1a;border-left:4px solid #dc3545;padding:12px 16px;margin:16px 0;border-radius:4px;">
                    <strong>Elutasítás oka:</strong><br>' . nl2br(htmlspecialchars($reason)) . '
                </div>
                <p>Az elutasítás után <strong>15 percen belül</strong> újra beküldheted az adataidat javítva.</p>
                <div style="text-align:center;">
                    <a href="' . htmlspecialchars(SITE_BASE_URL) . '/frontend/UserProfile/personal_data.php"
                       style="display:inline-block;padding:12px 28px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;margin:16px 0;">
                        Adatok javítása
                    </a>
                </div>
            </div>
            <div style="padding:12px;text-align:center;color:#666;font-size:0.75rem;border-top:1px solid #2a2a4a;">
                BetMatchBonus csapata
            </div>
        </div>';
        $mail->send();
    } catch (MailException $e) {
        error_log('Adatellenőrzés elutasítás email hiba: ' . $e->getMessage());
    }

    log_audit('data_verify_reject', 'user', $userId, 'Személyes adatok elutasítva: ' . $user['username'] . ' | Ok: ' . $reason);
    echo json_encode(['success' => true, 'message' => 'Adatellenőrzés elutasítva: ' . $user['username']]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
