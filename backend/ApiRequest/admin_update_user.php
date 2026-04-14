<?php
session_start();
require_once dirname(__DIR__) . '/Auth/admin_guard.php';
admin_guard('ADMIN');

require_once dirname(__DIR__) . '/Auth/audit_helper.php';
require_once dirname(__DIR__) . '/connect.php';
require_once dirname(__DIR__) . '/mail_config.php';
require_once dirname(__DIR__) . '/PHPMailer/Exception.php';
require_once dirname(__DIR__) . '/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__) . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── 1) Felhasználó adatainak frissítése ──
if ($action === 'update_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
        exit;
    }

    // Lekérjük a jelenlegi adatokat (összehasonlításhoz)
    $stmt = $conn->prepare("SELECT username, email, full_name, mobile_number, city, postal_code, address FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $oldUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$oldUser) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    // Módosítható mezők
    $newUsername    = trim($_POST['username'] ?? $oldUser['username']);
    $newEmail       = trim($_POST['email'] ?? $oldUser['email']);
    $newFullName    = trim($_POST['full_name'] ?? $oldUser['full_name']);
    $newPhone       = trim($_POST['mobile_number'] ?? $oldUser['mobile_number']);
    $newCity        = trim($_POST['city'] ?? $oldUser['city']);
    $newPostalCode  = trim($_POST['postal_code'] ?? $oldUser['postal_code']);
    $newAddress     = trim($_POST['address'] ?? $oldUser['address']);

    // Változások detektálása
    $changes = [];
    if ($newUsername !== $oldUser['username']) $changes[] = "Username: {$oldUser['username']} → {$newUsername}";
    if ($newEmail !== $oldUser['email']) $changes[] = "Email: {$oldUser['email']} → {$newEmail}";
    if ($newFullName !== ($oldUser['full_name'] ?? '')) $changes[] = "Teljes név: " . ($oldUser['full_name'] ?? '-') . " → {$newFullName}";
    if ($newPhone !== ($oldUser['mobile_number'] ?? '')) $changes[] = "Telefonszám: " . ($oldUser['mobile_number'] ?? '-') . " → {$newPhone}";
    if ($newCity !== ($oldUser['city'] ?? '')) $changes[] = "Város: " . ($oldUser['city'] ?? '-') . " → {$newCity}";
    if ($newPostalCode !== ($oldUser['postal_code'] ?? '')) $changes[] = "Irányítószám: " . ($oldUser['postal_code'] ?? '-') . " → {$newPostalCode}";
    if ($newAddress !== ($oldUser['address'] ?? '')) $changes[] = "Cím: " . ($oldUser['address'] ?? '-') . " → {$newAddress}";

    if (empty($changes)) {
        echo json_encode(['success' => true, 'message' => 'Nem történt változás.']);
        exit;
    }

    // UPDATE
    $upd = $conn->prepare("
        UPDATE Users 
        SET username = ?, email = ?, full_name = ?, mobile_number = ?, city = ?, postal_code = ?, address = ?
        WHERE id = ?
    ");
    $upd->bind_param("sssssssi", $newUsername, $newEmail, $newFullName, $newPhone, $newCity, $newPostalCode, $newAddress, $userId);

    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $upd->error]);
        $upd->close();
        exit;
    }
    $upd->close();

    // Email küldése a felhasználónak a változásokról
    $targetEmail = $newEmail;
    $targetName = $newFullName ?: $oldUser['username'];
    $changeList = implode('<br>', array_map('htmlspecialchars', $changes));

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
        $mail->addAddress($targetEmail, $targetName);

        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Fiókod adatai módosultak';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                <h2 style='color:#e94560;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                <p>Értesítünk, hogy az adminisztrátor módosított a fiókod adatain:</p>
                <div style='background:#16213e;padding:15px;border-radius:8px;margin:15px 0;'>
                    {$changeList}
                </div>
                <p>Ha a módosításokat nem te kezdeményezted, kérjük vedd fel velünk a kapcsolatot!</p>
                <br>
                <p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
            </div>";

        $mail->send();
    } catch (MailException $e) {
        error_log('Admin user update email hiba: ' . $e->getMessage());
    }

    log_audit('user_update', 'user', $userId, 'Felhasználó frissítve (' . count($changes) . ' módosítás): ' . implode(', ', $changes));
    echo json_encode(['success' => true, 'message' => 'Felhasználó frissítve! (' . count($changes) . ' módosítás) Email értesítés elküldve.']);
    exit;
}

// ── 2) Fiók letiltás / aktiválás ──
if ($action === 'toggle_active') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newActive = (int)($_POST['is_active'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT username, email, full_name, is_active FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE Users SET is_active = ? WHERE id = ?");
    $upd->bind_param("ii", $newActive, $userId);
    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Adatbázis hiba.']);
        $upd->close();
        exit;
    }
    $upd->close();

    $statusText = $newActive ? 'aktiválva' : 'letiltva';
    $targetName = $user['full_name'] ?: $user['username'];

    // Email értesítés
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
        $mail->addAddress($user['email'], $targetName);
        $mail->isHTML(true);

        if ($newActive) {
            $mail->Subject = 'BetMatchBonus – Fiókod újra aktív';
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                    <h2 style='color:#52b788;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                    <p>Örömmel értesítünk, hogy fiókod újra <strong style='color:#52b788;'>aktív</strong>!</p>
                    <p>Újra be tudsz jelentkezni és használni a szolgáltatásainkat.</p>
                    <br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
                </div>";
        } else {
            $mail->Subject = 'BetMatchBonus – Fiókod felfüggesztve';
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                    <h2 style='color:#e94560;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                    <p>Értesítünk, hogy fiókod <strong style='color:#e94560;'>felfüggesztésre került</strong>.</p>
                    <p>Ha kérdésed van, kérjük vedd fel velünk a kapcsolatot.</p>
                    <br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
                </div>";
        }
        $mail->send();
    } catch (MailException $e) {
        error_log('Toggle active email hiba: ' . $e->getMessage());
    }

    log_audit('user_toggle', 'user', $userId, "Felhasználó {$statusText}");
    echo json_encode(['success' => true, 'message' => "Felhasználó {$statusText}! Email értesítés elküldve."]);
    exit;
}

// ── 3) Felhasználó végleges törlése ──
if ($action === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
        exit;
    }
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Kérjük, add meg a törlés okát!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    $userEmail = $user['email'];
    $userName  = $user['username'];
    $targetName = $user['full_name'] ?: $userName;

    // Email küldés ELŐTT, mert utána már nem lesz adat
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
        $mail->addAddress($userEmail, $targetName);
        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Fiókod törölve';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                <h2 style='color:#e94560;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                <p>Értesítünk, hogy fiókod az adminisztrátor által <strong style='color:#e94560;'>véglegesen törlésre került</strong>.</p>
                <div style='background:#16213e;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #e94560;'>
                    <strong>Törlés oka:</strong><br>" . nl2br(htmlspecialchars($reason)) . "
                </div>
                <p>Ha kérdésed van, vedd fel velünk a kapcsolatot.</p>
                <br>
                <p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
            </div>";
        $mail->send();
    } catch (MailException $e) {
        error_log('User delete email hiba: ' . $e->getMessage());
    }

    // Törlés tranzakcióban
    $conn->begin_transaction();
    try {
        // Nem CASCADE-elő táblák törlése
        $tables = ['UserBonuses', 'BalanceHistory', 'Transactions'];
        foreach ($tables as $table) {
            $del = $conn->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $del->bind_param("i", $userId);
            $del->execute();
            $del->close();
        }

        // Felhasználó törlése (Wallets, WalletTransactions, Notifications, Tickets, TicketSelections CASCADE-del törlődnek)
        $del = $conn->prepare("DELETE FROM Users WHERE id = ?");
        $del->bind_param("i", $userId);
        $del->execute();
        $del->close();

        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba a törlés során: ' . $e->getMessage()]);
        exit;
    }

    // Feltöltött fájlok törlése
    $uploadDirs = [
        __DIR__ . '/../uploads/registrations/' . $userId . '/',
        __DIR__ . '/../uploads/bank_statements/',
    ];

    // Regisztrációs fájlok törlése
    $regDir = $uploadDirs[0];
    if (is_dir($regDir)) {
        $files = glob($regDir . '*');
        if ($files) foreach ($files as $f) { if (is_file($f)) unlink($f); }
        @rmdir($regDir);
    }

    // Bankszámlakivonat törlése
    $bankDir = $uploadDirs[1];
    $bankFiles = glob($bankDir . 'bank_' . $userId . '_*');
    if ($bankFiles) foreach ($bankFiles as $f) { if (is_file($f)) unlink($f); }

    log_audit('user_delete', 'user', $userId, 'Felhasználó törölve: ' . $userName . ' (' . $userEmail . ') | Ok: ' . $reason);
    echo json_encode(['success' => true, 'message' => 'Felhasználó törölve: ' . $userName . '. Email értesítés elküldve.']);
    exit;
}

// ── 4) Üzenet küldése felhasználónak ──
if ($action === 'send_message') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $messageText = trim($_POST['message'] ?? '');

    if ($userId <= 0 || empty($messageText)) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó felhasználó ID vagy üzenet.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT username, email, full_name FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    $targetName = $user['full_name'] ?: $user['username'];

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
        $mail->addAddress($user['email'], $targetName);

        $mail->isHTML(true);
        $mail->Subject = 'BetMatchBonus – Üzenet az adminisztrációtól';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                <h2 style='color:#e94560;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                <p>Üzenetet kaptál a BetMatchBonus adminisztrációjától:</p>
                <div style='background:#16213e;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #e94560;'>
                    " . nl2br(htmlspecialchars($messageText)) . "
                </div>
                <p>Ha kérdésed van, válaszolhatsz erre az emailre.</p>
                <br>
                <p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
            </div>";

        $mail->send();
        log_audit('user_message', 'user', $userId, 'Üzenet küldve: ' . $user['email']);
        echo json_encode(['success' => true, 'message' => 'Üzenet sikeresen elküldve: ' . htmlspecialchars($user['email'])]);
    } catch (MailException $e) {
        echo json_encode(['success' => false, 'message' => 'Email küldési hiba: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
