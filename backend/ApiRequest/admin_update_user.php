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

function createUserNotification(mysqli $conn, int $userId, string $title, string $message, string $type = 'admin_action'): void {
    $stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

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

    createUserNotification(
        $conn,
        $userId,
        'Fiókadatok módosítva',
        'Az admin módosította a fiókod adatait. Részletek: ' . implode(' | ', $changes),
        'account_update'
    );

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
                    <p>A biztonság érdekében minden eszközről automatikusan kijelentkeztettünk.</p>
                    <p>Ha kérdésed van, kérjük vedd fel velünk a kapcsolatot.</p>
                    <br><p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
                </div>";
        }
        $mail->send();
    } catch (MailException $e) {
        error_log('Toggle active email hiba: ' . $e->getMessage());
    }

    log_audit('user_toggle', 'user', $userId, "Felhasználó {$statusText}");

    // Ha letiltjuk, automatikusan force-logoutoljuk is
    if (!$newActive) {
        $conn->query("UPDATE UserSessions SET is_active = 0 WHERE user_id = " . (int)$userId);
        $stmtF = $conn->prepare("UPDATE Users SET force_logout_at = NOW() WHERE id = ?");
        $stmtF->bind_param("i", $userId);
        $stmtF->execute();
        $stmtF->close();
    }

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

// ── 5) Admin jelszó-visszaállítás indítása ──
if ($action === 'reset_password') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM Users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    $targetName = $user['full_name'] ?: $user['username'];
    $resetToken = bin2hex(random_bytes(32));

    $stmt = $conn->prepare("UPDATE Users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR), force_logout_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $resetToken, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Nem sikerült a visszaállítási token mentése.']);
        exit;
    }
    $stmt->close();

    // Biztonság: minden aktív eszköz kijelentkeztetése.
    $stmt = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    log_activity($userId, 'password_reset', 'Admin jelszó-visszaállítás indítva.');

    $resetUrl = SITE_BASE_URL . '/frontend/Auth/reset_password.php?token=' . $resetToken;

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
        $mail->Subject = 'BetMatchBonus – Admin jelszó-visszaállítás';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#1a1a2e;color:#eee;padding:30px;border-radius:10px;'>
                <h2 style='color:#f5c518;'>Kedves " . htmlspecialchars($targetName) . "!</h2>
                <p>Az adminisztráció jelszó-visszaállítást indított a fiókodhoz.</p>
                <p>Kérjük, az alábbi gombra kattintva állíts be új jelszót:</p>
                <p><a href='" . htmlspecialchars($resetUrl) . "' style='display:inline-block;padding:12px 24px;background:#f5c518;color:#1a1a2e;text-decoration:none;border-radius:6px;font-weight:bold;'>Új jelszó beállítása</a></p>
                <p style='color:#bbb;'>A visszaállítási link 1 órán belül lejár.</p>
                <p style='color:#bbb;'>Biztonsági okból minden eszközről kijelentkeztettünk. Az új jelszó beállítása után jelentkezz be újra.</p>
                <br>
                <p style='color:#888;font-size:12px;'>Üdvözlettel,<br>BetMatchBonus csapata</p>
            </div>";

        $mail->send();
    } catch (MailException $e) {
        error_log('Admin reset password email hiba: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A token létrejött, de az email küldés nem sikerült.']);
        exit;
    }

    log_audit('admin_reset_password', 'user', $userId, 'Admin jelszó-visszaállítás indítva: ' . $user['username']);
    echo json_encode(['success' => true, 'message' => 'Jelszó-visszaállítási email elküldve: ' . htmlspecialchars($user['email'])]);
    exit;
}

// ── 6) Felhasználó force-logout (minden eszközről kijelentkeztetés) ──
if ($action === 'force_logout') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen felhasználó ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT username FROM Users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található.']);
        exit;
    }

    // 1) Összes remember-session deaktiválása
    $stmt = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // 2) Force-logout timestamp beállítása (PHP session-t is érvényteleníti)
    $stmt = $conn->prepare("UPDATE Users SET force_logout_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    createUserNotification(
        $conn,
        $userId,
        'Kényszerített kijelentkeztetés',
        'Az admin minden eszközről kijelentkeztetett. Kérjük, jelentkezz be újra.',
        'security'
    );

    log_audit('force_logout', 'user', $userId, 'Kikényszerített kijelentkezés: ' . $user['username']);
    echo json_encode(['success' => true, 'message' => htmlspecialchars($user['username']) . ' kijelentkeztetve minden eszközről.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
