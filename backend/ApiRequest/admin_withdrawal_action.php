<?php
/**
 * Admin kifizetési műveletek: approve / reject / manual_withdraw / revoke
 * POST JSON: { action: "...", transaction_id?: INT, reason?: STRING, user_id?: INT, amount?: FLOAT }
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';

// JSON API - ne redirect-eljen ha nincs session
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs admin bejelentkezés.']);
    exit;
}
admin_guard('ADMIN');

require_once __DIR__ . '/../Auth/audit_helper.php';
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json');

$sendFinancialEmails = false;

function createUserNotification(mysqli $conn, int $userId, string $title, string $message, string $type = 'balance'): void {
    $stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

// Helper: konfigurált PHPMailer példány
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

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

/* ━━━━━ MANUAL WITHDRAW ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'manual_withdraw') {
    $userId = (int)($input['user_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $note   = trim($input['note'] ?? '');

    if ($userId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó felhasználó vagy összeg.']);
        exit;
    }

    // Felhasználó lekérdezése
    $uStmt = $conn->prepare("SELECT id, username, email, balance, winnings_balance FROM Users WHERE id = ? AND is_active = 1 AND is_verified = 1");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $user = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    if ($amount > (float)$user['balance']) {
        echo json_encode(['success' => false, 'message' => 'Nincs elegendő egyenleg! Elérhető: ' . number_format((float)$user['balance'], 0, ',', ' ') . ' Ft']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $transactionId = 'ADMWTH_' . uniqid();
        $description = 'Admin manuális kifizetés' . ($note !== '' ? ': ' . $note : '');

        // Tranzakció létrehozása (egyből completed)
        $ins = $conn->prepare("INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, description, created_at, updated_at) VALUES (?, 'withdrawal', ?, 'admin_manual', 'completed', ?, ?, NOW(), NOW())");
        $ins->bind_param("idss", $userId, $amount, $transactionId, $description);
        $ins->execute();
        $ins->close();

        // Egyenleg levonás
        $prevBal = (float)$user['balance'];
        $balUpd = $conn->prepare("UPDATE Users SET balance = balance - ?, winnings_balance = winnings_balance - ? WHERE id = ?");
        $balUpd->bind_param("ddi", $amount, $amount, $userId);
        $balUpd->execute();
        $balUpd->close();

        // BalanceHistory
        log_balance_change($userId, $prevBal, $prevBal - $amount, -$amount, 'Admin kifizetés: ' . ($note !== '' ? $note : 'manuális'));

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
        exit;
    }

    $amountFormatted = number_format($amount, 0, ',', ' ');

    // Email a felhasználónak
    if ($sendFinancialEmails) {
        try {
            $mail = getConfiguredMailer();
            $mail->addAddress($user['email'], $user['username']);
            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?' . base64_encode('💸 Manuális kifizetés - ' . $amountFormatted . ' Ft') . '?=';
            $mail->Body = '<html><body></body></html>';
            $mail->send();
        } catch (MailException $e) { /* silent */ }
    }

    createUserNotification(
        $conn,
        $userId,
        'Admin manuális kifizetés',
        'Az admin manuális kifizetést rögzített: ' . $amountFormatted . ' Ft. Tranzakció: ' . $transactionId . ($note !== '' ? ' | Megjegyzés: ' . $note : ''),
        'balance'
    );

    log_audit('withdrawal_manual', 'user', $userId, 'Manuális kifizetés: ' . $amountFormatted . ' Ft (' . $user['username'] . ')');
    echo json_encode(['success' => true, 'message' => 'Manuális kifizetés létrehozva: ' . $amountFormatted . ' Ft (' . htmlspecialchars($user['username']) . ')']);
    exit;
}

/* ━━━━━ REVOKE (completed → failed, balance visszaadás) ━━━━━━━ */
if ($action === 'revoke') {
    $txId = (int)($input['transaction_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if ($txId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó tranzakció azonosító.']);
        exit;
    }
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Add meg a visszavonás okát!']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT t.id, t.user_id, t.amount, t.status, t.transaction_id,
               u.username, u.email
        FROM Transactions t
        INNER JOIN Users u ON u.id = t.user_id
        WHERE t.id = ? AND t.type = 'withdrawal'
        LIMIT 1
    ");
    $stmt->bind_param("i", $txId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) {
        echo json_encode(['success' => false, 'message' => 'A tranzakció nem található.']);
        exit;
    }
    if ($tx['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Csak jóváhagyott kifizetés vonható vissza (jelenlegi: ' . $tx['status'] . ').']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Státusz → failed + ok mentése
        $upd = $conn->prepare("UPDATE Transactions SET status = 'failed', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param("si", $reason, $tx['id']);
        $upd->execute();
        $upd->close();

        // Egyenleg visszaállítás
        $balUpd = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
        $balUpd->bind_param("ddi", $tx['amount'], $tx['amount'], $tx['user_id']);
        $balUpd->execute();
        $balUpd->close();

        // BalanceHistory
        $txUserBal = $conn->query("SELECT balance FROM Users WHERE id = " . (int)$tx['user_id'])->fetch_assoc();
        $newBal = (float)($txUserBal['balance'] ?? 0);
        $txAmt = (float)$tx['amount'];
        log_balance_change((int)$tx['user_id'], $newBal - $txAmt, $newBal, $txAmt, 'Kifizetés visszavonva: ' . $tx['transaction_id']);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
        exit;
    }

    $amountFormatted = number_format((float)$tx['amount'], 0, ',', ' ');

    // Email a felhasználónak
    if ($sendFinancialEmails) {
        try {
            $mail = getConfiguredMailer();
            $mail->addAddress($tx['email'], $tx['username']);
            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?' . base64_encode('⚠️ Kifizetés visszavonva - ' . $amountFormatted . ' Ft') . '?=';
            $mail->Body = '<html><body></body></html>';
            $mail->send();
        } catch (MailException $e) { /* silent */ }
    }

    createUserNotification(
        $conn,
        (int)$tx['user_id'],
        'Kifizetés visszavonva',
        'A(z) ' . $tx['transaction_id'] . ' azonosítójú kifizetésed visszavonásra került. Összeg: ' . $amountFormatted . '. Ok: ' . $reason,
        'balance'
    );

    log_audit('withdrawal_revoke', 'transaction', $txId, 'Kifizetés visszavonva: ' . $amountFormatted . ' Ft');
    echo json_encode(['success' => true, 'message' => 'Kifizetés visszavonva, egyenleg visszaállítva: ' . $amountFormatted . ' Ft']);
    exit;
}

/* ── Approve / Reject közös validáció ─────────────────────────── */
$txId = (int)($input['transaction_id'] ?? 0);

if ($txId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó tranzakció azonosító.']);
    exit;
}

/* ── Tranzakció lekérdezése ───────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT t.id, t.user_id, t.amount, t.status, t.transaction_id, t.account_holder, t.account_number,
           u.username, u.email, u.balance, u.winnings_balance
    FROM Transactions t
    INNER JOIN Users u ON u.id = t.user_id
    WHERE t.id = ? AND t.type = 'withdrawal'
    LIMIT 1
");
$stmt->bind_param("i", $txId);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    echo json_encode(['success' => false, 'message' => 'A tranzakció nem található.']);
    exit;
}

if ($tx['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Ez a kifizetés már feldolgozásra került (' . $tx['status'] . ').']);
    exit;
}

$amountFormatted = number_format((float)$tx['amount'], 0, ',', ' ');

/* ━━━━━ APPROVE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'approve') {
    $upd = $conn->prepare("UPDATE Transactions SET status = 'completed', approval_token = NULL, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("i", $tx['id']);
    $upd->execute();
    $upd->close();

    // Email a felhasználónak
    if ($sendFinancialEmails) {
        try {
            $mail = getConfiguredMailer();
            $mail->addAddress($tx['email'], $tx['username']);
            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?' . base64_encode('✅ Kifizetésed jóváhagyva! - ' . $amountFormatted . ' Ft') . '?=';
            $mail->Body = '<html><body></body></html>';
            $mail->send();
        } catch (MailException $e) {
            // Email hiba nem akadályozza a jóváhagyást
        }
    }

    createUserNotification(
        $conn,
        (int)$tx['user_id'],
        'Kifizetés jóváhagyva',
        'A(z) ' . $tx['transaction_id'] . ' azonosítójú kifizetésed jóváhagyásra került. Összeg: ' . $amountFormatted . '.',
        'balance'
    );

    log_audit('withdrawal_approve', 'transaction', $txId, 'Kifizetés jóváhagyva: ' . $amountFormatted . ' Ft');
    echo json_encode(['success' => true, 'message' => 'Kifizetés jóváhagyva: ' . $amountFormatted . ' Ft']);
    exit;
}

/* ━━━━━ REJECT ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'reject') {
    $reason = trim($input['reason'] ?? '');
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Add meg az elutasítás okát!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Tranzakció státusz frissítés
        $upd = $conn->prepare("UPDATE Transactions SET status = 'rejected', approval_token = NULL, rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param("si", $reason, $tx['id']);
        $upd->execute();
        $upd->close();

        // Egyenleg visszaállítás (balance + winnings_balance)
        $balUpd = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
        $balUpd->bind_param("ddi", $tx['amount'], $tx['amount'], $tx['user_id']);
        $balUpd->execute();
        $balUpd->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba történt: ' . $e->getMessage()]);
        exit;
    }

    // Email a felhasználónak
    if ($sendFinancialEmails) {
        try {
            $mail = getConfiguredMailer();
            $mail->addAddress($tx['email'], $tx['username']);
            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?' . base64_encode('❌ Kifizetésed elutasítva - ' . $amountFormatted . ' Ft') . '?=';
            $mail->Body = '<html><body></body></html>';
            $mail->send();
        } catch (MailException $e) {
            // Email hiba nem akadályozza az elutasítást
        }
    }

    createUserNotification(
        $conn,
        (int)$tx['user_id'],
        'Kifizetés elutasítva',
        'A(z) ' . $tx['transaction_id'] . ' azonosítójú kifizetésed elutasításra került. Összeg: ' . $amountFormatted . '. Ok: ' . $reason,
        'balance'
    );

    log_audit('withdrawal_reject', 'transaction', $txId, 'Kifizetés elutasítva: ' . $amountFormatted . ' Ft');
    echo json_encode(['success' => true, 'message' => 'Kifizetés elutasítva, egyenleg visszaállítva: ' . $amountFormatted . ' Ft']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet: ' . $action]);
