<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, username, email, password_hash, role, is_active
    FROM AdminUsers
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Hibás felhasználónév vagy jelszó.']);
    exit;
}

if ((int)$admin['is_active'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'A fiók inaktív.']);
    exit;
}

$_SESSION['admin_id']       = (int)$admin['id'];
$_SESSION['admin_username'] = $admin['username'];
$_SESSION['admin_role']     = $admin['role'];

$upd = $conn->prepare("UPDATE AdminUsers SET last_login = NOW() WHERE id = ?");
$upd->bind_param("i", $admin['id']);
$upd->execute();
$upd->close();

echo json_encode([
    'success' => true,
    'message' => 'Sikeres admin bejelentkezés!',
    'role'    => $admin['role']
]);