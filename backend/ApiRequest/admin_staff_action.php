<?php
/**
 * Admin staff műveletek: create / update / toggle_active / delete / reset_password
 * POST JSON / PATCH JSON (szerepkör gyorsváltás)
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
admin_guard('SUPERADMIN');

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

function sendStaffEmail($toEmail, $toName, $subject, $bodyHtml) {
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
$currentAdminId = (int)$_SESSION['admin_id'];

/* ━━━━━ PATCH — Szerepkör gyorsváltás ━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $adminId = (int)($input['admin_id'] ?? 0);
    $roleId  = (int)($input['role_id'] ?? 0);

    if ($adminId <= 0 || !in_array($roleId, [1, 2, 3])) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen adatok!']);
        exit;
    }
    if ($adminId === $currentAdminId) {
        echo json_encode(['success' => false, 'message' => 'Saját szerepkörödet nem változtathatod meg!']);
        exit;
    }

    // SUPERADMIN nem degradálható
    $chk = $conn->prepare("SELECT role_id, username FROM AdminUsers WHERE id = ?");
    $chk->bind_param("i", $adminId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Admin nem található!']);
        exit;
    }
    if ((int)$row['role_id'] === 3) {
        echo json_encode(['success' => false, 'message' => 'SUPERADMIN szerepköre nem módosítható!']);
        exit;
    }

    $upd = $conn->prepare("UPDATE AdminUsers SET role_id = ? WHERE id = ?");
    $upd->bind_param("ii", $roleId, $adminId);
    $upd->execute();
    $upd->close();

    $rStmt = $conn->prepare("SELECT name FROM Roles WHERE id = ?");
    $rStmt->bind_param("i", $roleId);
    $rStmt->execute();
    $roleName = $rStmt->get_result()->fetch_assoc()['name'] ?? 'N/A';
    $rStmt->close();

    log_audit('staff_role_patch', 'admin', $adminId,
        'Szerepkör módosítva: ' . $row['username'] . ' → ' . $roleName);
    echo json_encode(['success' => true, 'message' => $row['username'] . ' szerepköre: ' . $roleName]);
    exit;
}

/* ━━━━━ CREATE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'create') {
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $roleId   = (int)($input['role_id'] ?? 1);

    if ($username === '' || $email === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'A jelszó legalább 6 karakter legyen!']);
        exit;
    }
    if (!in_array($roleId, [1, 2, 3])) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen szerepkör!']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen email cím!']);
        exit;
    }

    // Duplikáció ellenőrzés
    $chk = $conn->prepare("SELECT id FROM AdminUsers WHERE username = ? OR email = ?");
    $chk->bind_param("ss", $username, $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználónév vagy email már foglalt!']);
        exit;
    }
    $chk->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO AdminUsers (username, email, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $ins->bind_param("sssi", $username, $email, $hash, $roleId);
    $ins->execute();
    $newId = $conn->insert_id;
    $ins->close();

    // Szerepkör neve
    $rStmt = $conn->prepare("SELECT name FROM Roles WHERE id = ?");
    $rStmt->bind_param("i", $roleId);
    $rStmt->execute();
    $roleName = $rStmt->get_result()->fetch_assoc()['name'] ?? 'N/A';
    $rStmt->close();

    sendStaffEmail($email, $username,
        'BetMatchBonus – Admin fiók létrehozva',
        "<h2>Üdvözlünk, {$username}!</h2>
        <p>Admin fiókod létrejött a BetMatchBonus rendszerben.</p>
        <p><strong>Felhasználónév:</strong> {$username}<br>
        <strong>Szerepkör:</strong> {$roleName}</p>
        <p>A belépéshez használd az admin bejelentkezési oldalt.</p>"
    );

    log_audit('staff_create', 'admin', $newId, 'Admin létrehozva: ' . $username . ' (' . $roleName . ')');
    echo json_encode(['success' => true, 'message' => 'Admin létrehozva: ' . htmlspecialchars($username)]);
    exit;
}

/* ━━━━━ UPDATE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'update') {
    $adminId  = (int)($input['admin_id'] ?? 0);
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email'] ?? '');
    $roleId   = (int)($input['role_id'] ?? 0);

    if ($adminId <= 0 || $username === '' || $email === '' || !in_array($roleId, [1, 2, 3])) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó vagy érvénytelen adatok!']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen email cím!']);
        exit;
    }

    // Saját magad nem degradálhatod
    if ($adminId === $currentAdminId && $roleId < 3) {
        echo json_encode(['success' => false, 'message' => 'Saját szerepkörödet nem változtathatod meg!']);
        exit;
    }

    // Duplikáció ellenőrzés (más admin-nál)
    $chk = $conn->prepare("SELECT id FROM AdminUsers WHERE (username = ? OR email = ?) AND id != ?");
    $chk->bind_param("ssi", $username, $email, $adminId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'Ez a felhasználónév vagy email már foglalt!']);
        exit;
    }
    $chk->close();

    $upd = $conn->prepare("UPDATE AdminUsers SET username = ?, email = ?, role_id = ? WHERE id = ?");
    $upd->bind_param("ssii", $username, $email, $roleId, $adminId);
    $upd->execute();
    $upd->close();

    // Szerepkör neve
    $rStmt = $conn->prepare("SELECT name FROM Roles WHERE id = ?");
    $rStmt->bind_param("i", $roleId);
    $rStmt->execute();
    $roleName = $rStmt->get_result()->fetch_assoc()['name'] ?? 'N/A';
    $rStmt->close();

    sendStaffEmail($email, $username,
        'BetMatchBonus – Admin fiók módosítva',
        "<h2>Kedves {$username}!</h2>
        <p>Admin fiókod adatai módosultak.</p>
        <p><strong>Felhasználónév:</strong> {$username}<br>
        <strong>Email:</strong> {$email}<br>
        <strong>Szerepkör:</strong> {$roleName}</p>"
    );

    log_audit('staff_update', 'admin', $adminId, 'Admin frissítve: ' . $username);
    echo json_encode(['success' => true, 'message' => 'Admin frissítve: ' . htmlspecialchars($username)]);
    exit;
}

/* ━━━━━ TOGGLE ACTIVE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'toggle_active') {
    $adminId = (int)($input['admin_id'] ?? 0);
    $reason  = trim($input['reason'] ?? '');

    if ($adminId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó admin azonosító!']);
        exit;
    }
    if ($adminId === $currentAdminId) {
        echo json_encode(['success' => false, 'message' => 'Saját fiókodat nem tilthatod le!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT username, email, is_active FROM AdminUsers WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Admin nem található!']);
        exit;
    }

    $newStatus = $row['is_active'] ? 0 : 1;
    $upd = $conn->prepare("UPDATE AdminUsers SET is_active = ? WHERE id = ?");
    $upd->bind_param("ii", $newStatus, $adminId);
    $upd->execute();
    $upd->close();

    if ($newStatus) {
        sendStaffEmail($row['email'], $row['username'],
            'BetMatchBonus – Admin fiók aktiválva',
            "<h2>Kedves {$row['username']}!</h2>
            <p>Admin fiókod újra <strong>aktív</strong>. Ismét beléphetsz a rendszerbe.</p>"
        );
    } else {
        $reasonHtml = $reason !== '' ? "<p><strong>Indoklás:</strong> " . htmlspecialchars($reason) . "</p>" : '';
        sendStaffEmail($row['email'], $row['username'],
            'BetMatchBonus – Admin fiók letiltva',
            "<h2>Kedves {$row['username']}!</h2>
            <p>Admin fiókod <strong>letiltásra került</strong>.</p>
            {$reasonHtml}
            <p>Ha kérdésed van, keresd a rendszergazdát.</p>"
        );
    }

    $msg = $newStatus ? 'Admin fiók aktiválva' : 'Admin fiók letiltva';
    log_audit('staff_toggle', 'admin', $adminId, $msg . ': ' . $row['username']);
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

/* ━━━━━ RESET PASSWORD ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'reset_password') {
    $adminId     = (int)($input['admin_id'] ?? 0);
    $newPassword = $input['new_password'] ?? '';

    if ($adminId <= 0 || strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Jelszó legalább 6 karakter legyen!']);
        exit;
    }

    // Admin adatok lekérdezése
    $aStmt = $conn->prepare("SELECT username, email FROM AdminUsers WHERE id = ?");
    $aStmt->bind_param("i", $adminId);
    $aStmt->execute();
    $adminRow = $aStmt->get_result()->fetch_assoc();
    $aStmt->close();

    if (!$adminRow) {
        echo json_encode(['success' => false, 'message' => 'Admin nem található!']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE AdminUsers SET password_hash = ? WHERE id = ?");
    $upd->bind_param("si", $hash, $adminId);
    $upd->execute();
    $upd->close();

    sendStaffEmail($adminRow['email'], $adminRow['username'],
        'BetMatchBonus – Jelszó visszaállítva',
        "<h2>Kedves {$adminRow['username']}!</h2>
        <p>Admin fiókod jelszava visszaállításra került egy rendszergazda által.</p>
        <p>Kérjük, a következő bejelentkezéskor használd az új jelszavad.</p>"
    );

    log_audit('staff_reset_pw', 'admin', $adminId, 'Jelszó visszaállítva: ' . $adminRow['username']);
    echo json_encode(['success' => true, 'message' => 'Jelszó sikeresen megváltoztatva!']);
    exit;
}

/* ━━━━━ DELETE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'delete') {
    $adminId = (int)($input['admin_id'] ?? 0);
    $reason  = trim($input['reason'] ?? '');

    if ($adminId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó admin azonosító!']);
        exit;
    }
    if ($adminId === $currentAdminId) {
        echo json_encode(['success' => false, 'message' => 'Saját fiókodat nem törölheted!']);
        exit;
    }

    // Admin adatok lekérdezése email küldéshez (törlés előtt)
    $aStmt = $conn->prepare("SELECT username, email FROM AdminUsers WHERE id = ?");
    $aStmt->bind_param("i", $adminId);
    $aStmt->execute();
    $adminRow = $aStmt->get_result()->fetch_assoc();
    $aStmt->close();

    if (!$adminRow) {
        echo json_encode(['success' => false, 'message' => 'Admin nem található!']);
        exit;
    }

    $del = $conn->prepare("DELETE FROM AdminUsers WHERE id = ?");
    $del->bind_param("i", $adminId);
    $del->execute();
    $del->close();

    $reasonHtml = $reason !== '' ? "<p><strong>Indoklás:</strong> " . htmlspecialchars($reason) . "</p>" : '';
    sendStaffEmail($adminRow['email'], $adminRow['username'],
        'BetMatchBonus – Admin fiók törölve',
        "<h2>Kedves {$adminRow['username']}!</h2>
        <p>Admin fiókod <strong>törölve lett</strong> a BetMatchBonus rendszerből.</p>
        {$reasonHtml}
        <p>Ha kérdésed van, keresd a rendszergazdát.</p>"
    );

    log_audit('staff_delete', 'admin', $adminId, 'Admin törölve: ' . $adminRow['username']);
    echo json_encode(['success' => true, 'message' => 'Admin törölve!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet: ' . $action]);
