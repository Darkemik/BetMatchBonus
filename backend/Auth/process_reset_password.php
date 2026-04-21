<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

$token       = $_POST['token'] ?? '';
$newPassword = $_POST['new_password'] ?? '';

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen token.']);
    exit;
}

$minPwLen = get_setting_int('min_password_length', 7);
if (strlen($newPassword) < $minPwLen) {
    echo json_encode(['success' => false, 'message' => 'A jelszó legalább ' . $minPwLen . ' karakter legyen!']);
    exit;
}

// Token keresése
$stmt = $conn->prepare("SELECT id, username, email, password_hash FROM Users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'A visszaállítási link lejárt vagy érvénytelen. Kérjük, igényelj újat!']);
    exit;
}

if (password_verify($newPassword, (string)$user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Az új jelszó nem egyezhet a régivel. Kérjük, adj meg egy másik jelszót!']);
    exit;
}

// Jelszó frissítése, token törlése
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE Users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
$upd->bind_param("si", $passwordHash, $user['id']);
$upd->execute();
$upd->close();

log_activity((int)$user['id'], 'password_change', 'Jelszó módosítva jelszó-visszaállítási linkkel.');

echo json_encode([
    'success' => true,
    'message' => 'A jelszavad sikeresen megváltozott! Átirányítás a főoldalra...'
]);

$conn->close();
