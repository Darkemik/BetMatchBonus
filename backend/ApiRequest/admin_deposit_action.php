<?php
/**
 * Admin befizetési műveletek: manual_deposit / refund
 * POST JSON
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs admin bejelentkezés.']);
    exit;
}
admin_guard('ADMIN');

require_once __DIR__ . '/../Auth/audit_helper.php';
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

header('Content-Type: application/json');

function getConfiguredMailer() {
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

function sendDepositEmail($toEmail, $toName, $subject, $bodyHtml) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->send();
    } catch (\Exception $e) {
        // Email hiba nem blokkolja a műveletet
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

/* ━━━━━ MANUAL DEPOSIT (Manuális jóváírás) ━━━━━━━━━━━━━━━━━━━ */
if ($action === 'manual_deposit') {
    $userId = (int)($input['user_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $note   = trim($input['note'] ?? '');

    if ($userId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó felhasználó vagy összeg.']);
        exit;
    }

    // Felhasználó lekérdezése
    $uStmt = $conn->prepare("SELECT id, username, email, balance FROM Users WHERE id = ? AND is_active = 1 AND is_verified = 1");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $user = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Tranzakció létrehozása
        $txId = 'ADMDEP_' . uniqid();
        $desc = $note !== '' ? 'Admin jóváírás: ' . $note : 'Admin manuális jóváírás';
        $ins = $conn->prepare("INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, description, created_at) VALUES (?, 'deposit', ?, 'admin', 'completed', ?, ?, NOW())");
        $ins->bind_param("idss", $userId, $amount, $txId, $desc);
        $ins->execute();
        $ins->close();

        // Egyenleg növelése
        $prevBal = (float)$user['balance'];
        $upd = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
        $upd->bind_param("di", $amount, $userId);
        $upd->execute();
        $upd->close();

        // BalanceHistory
        require_once __DIR__ . '/../Auth/audit_helper.php';
        log_balance_change($userId, $prevBal, $prevBal + $amount, $amount, 'Admin befizetés: ' . ($note !== '' ? $note : 'manuális jóváírás'));

        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba történt: ' . $e->getMessage()]);
        exit;
    }

    $amountFmt = number_format($amount, 0, ',', ' ');
    $noteHtml = $note !== '' ? '<tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Megjegyzés</td><td style="padding:10px 20px;color:#eee;font-size:14px;">' . htmlspecialchars($note) . '</td></tr>' : '';
    $newBalance = number_format((float)$user['balance'] + $amount, 0, ',', ' ');
    $date = date('Y.m.d H:i');

    sendDepositEmail($user['email'], $user['username'],
        'BetMatchBonus – Egyenleg jóváírás',
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#1a1a2e;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#1a1a2e;padding:40px 0;">
        <tr><td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#16213e;border-radius:12px;overflow:hidden;">
                <!-- Header -->
                <tr><td style="background:#28a745;padding:24px 30px;text-align:center;">
                    <h1 style="margin:0;color:#fff;font-size:22px;">✅ Egyenleg jóváírás</h1>
                </td></tr>
                <!-- Body -->
                <tr><td style="padding:30px;">
                    <p style="color:#eee;font-size:16px;margin:0 0 20px;">Kedves <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                    <p style="color:#ccc;font-size:14px;margin:0 0 24px;">Jóváírás érkezett a fiókodra. Az összeg azonnal elérhető az egyenlegeden.</p>
                    <!-- Amount box -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1b30;border-radius:8px;border-left:4px solid #28a745;margin-bottom:20px;">
                        <tr><td style="padding:20px 24px;text-align:center;">
                            <div style="color:#52b788;font-size:28px;font-weight:700;">+' . $amountFmt . ' Ft</div>
                            <div style="color:#aaa;font-size:13px;margin-top:4px;">jóváírt összeg</div>
                        </td></tr>
                    </table>
                    <!-- Details -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0a1225;border-radius:8px;margin-bottom:20px;">
                        <tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Új egyenleg</td><td style="padding:10px 20px;color:#52b788;font-size:14px;font-weight:700;">' . $newBalance . ' Ft</td></tr>
                        <tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Dátum</td><td style="padding:10px 20px;color:#eee;font-size:14px;">' . $date . '</td></tr>
                        ' . $noteHtml . '
                    </table>
                    <p style="color:#888;font-size:13px;margin:0;">Ha kérdésed van, keresd az ügyfélszolgálatot.</p>
                </td></tr>
                <!-- Footer -->
                <tr><td style="background:#0f1b30;padding:16px 30px;text-align:center;">
                    <p style="color:#666;font-size:12px;margin:0;">© ' . date('Y') . ' BetMatchBonus — Automatikus értesítés</p>
                </td></tr>
            </table>
        </td></tr></table>
        </body></html>'
    );

    log_audit('deposit_manual', 'user', $userId, "Manuális jóváírás: {$amountFmt} Ft – {$user['username']}");
    echo json_encode(['success' => true, 'message' => "Jóváírva: {$amountFmt} Ft ({$user['username']})"]);
    exit;
}

/* ━━━━━ REFUND (Visszatérítés) ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'refund') {
    $transactionId = (int)($input['transaction_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if ($transactionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó tranzakció azonosító.']);
        exit;
    }

    // Tranzakció lekérdezése
    $tStmt = $conn->prepare("
        SELECT t.id, t.user_id, t.amount, t.transaction_id, t.status, t.payment_method,
               u.username, u.email, u.balance
        FROM Transactions t
        JOIN Users u ON t.user_id = u.id
        WHERE t.id = ? AND t.type = 'deposit'
    ");
    $tStmt->bind_param("i", $transactionId);
    $tStmt->execute();
    $tx = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    if (!$tx) {
        echo json_encode(['success' => false, 'message' => 'Befizetés nem található.']);
        exit;
    }
    if ($tx['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Csak teljesített befizetés téríthető vissza.']);
        exit;
    }

    // Ellenőrzés: van-e elég egyenleg a visszatérítéshez
    if ((float)$tx['balance'] < (float)$tx['amount']) {
        echo json_encode(['success' => false, 'message' => 'A felhasználó egyenlege nem elegendő a visszatérítéshez! Egyenleg: ' . number_format((float)$tx['balance'], 0, ',', ' ') . ' Ft']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Tranzakció státusz frissítése
        $reasonDb = $reason !== '' ? $reason : 'Admin visszatérítés';
        $upd = $conn->prepare("UPDATE Transactions SET status = 'failed', rejection_reason = ? WHERE id = ?");
        $upd->bind_param("si", $reasonDb, $transactionId);
        $upd->execute();
        $upd->close();

        // Egyenleg levonása
        $bal = $conn->prepare("UPDATE Users SET balance = balance - ? WHERE id = ?");
        $bal->bind_param("di", $tx['amount'], $tx['user_id']);
        $bal->execute();
        $bal->close();

        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba történt: ' . $e->getMessage()]);
        exit;
    }

    $amountFmt = number_format((float)$tx['amount'], 0, ',', ' ');
    $reasonHtml = $reason !== '' ? '<tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Indoklás</td><td style="padding:10px 20px;color:#ff6b6b;font-size:14px;">' . htmlspecialchars($reason) . '</td></tr>' : '';
    $methodLabel = strtoupper($tx['payment_method'] ?? '');
    $date = date('Y.m.d H:i');

    sendDepositEmail($tx['email'], $tx['username'],
        'BetMatchBonus – Befizetés visszatérítve',
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#1a1a2e;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#1a1a2e;padding:40px 0;">
        <tr><td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#16213e;border-radius:12px;overflow:hidden;">
                <!-- Header -->
                <tr><td style="background:#e94560;padding:24px 30px;text-align:center;">
                    <h1 style="margin:0;color:#fff;font-size:22px;">⚠️ Befizetés visszatérítve</h1>
                </td></tr>
                <!-- Body -->
                <tr><td style="padding:30px;">
                    <p style="color:#eee;font-size:16px;margin:0 0 20px;">Kedves <strong>' . htmlspecialchars($tx['username']) . '</strong>,</p>
                    <p style="color:#ccc;font-size:14px;margin:0 0 24px;">Egy korábbi befizetésed visszatérítésre került. Az összeg levonásra került az egyenlegedből.</p>
                    <!-- Amount box -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1b30;border-radius:8px;border-left:4px solid #e94560;margin-bottom:20px;">
                        <tr><td style="padding:20px 24px;text-align:center;">
                            <div style="color:#e94560;font-size:28px;font-weight:700;text-decoration:line-through;">' . $amountFmt . ' Ft</div>
                            <div style="color:#aaa;font-size:13px;margin-top:4px;">visszatérített összeg</div>
                        </td></tr>
                    </table>
                    <!-- Details -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0a1225;border-radius:8px;margin-bottom:20px;">
                        <tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Tranzakció ID</td><td style="padding:10px 20px;color:#eee;font-size:14px;font-family:monospace;">' . htmlspecialchars($tx['transaction_id']) . '</td></tr>
                        <tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Fizetési mód</td><td style="padding:10px 20px;color:#eee;font-size:14px;">' . $methodLabel . '</td></tr>
                        <tr><td style="padding:10px 20px;color:#aaa;font-size:14px;">Dátum</td><td style="padding:10px 20px;color:#eee;font-size:14px;">' . $date . '</td></tr>
                        ' . $reasonHtml . '
                    </table>
                    <p style="color:#888;font-size:13px;margin:0;">Ha kérdésed van, keresd az ügyfélszolgálatot.</p>
                </td></tr>
                <!-- Footer -->
                <tr><td style="background:#0f1b30;padding:16px 30px;text-align:center;">
                    <p style="color:#666;font-size:12px;margin:0;">© ' . date('Y') . ' BetMatchBonus — Automatikus értesítés</p>
                </td></tr>
            </table>
        </td></tr></table>
        </body></html>'
    );

    log_audit('deposit_refund', 'transaction', $txId, "Visszatérítés: {$amountFmt} Ft");
    echo json_encode(['success' => true, 'message' => "Visszatérítve: {$amountFmt} Ft"]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet: ' . $action]);
