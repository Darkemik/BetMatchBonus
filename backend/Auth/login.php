<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

$login    = trim($_POST['login'] ?? ''); // username vagy email
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, username, email, password_hash, full_name, birth_date, is_active
    FROM Users
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Hibás felhasználónév/email vagy jelszó.']);
    exit;
}

if ((int)$user['is_active'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'A fiók inaktív.']);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Hibás felhasználónév/email vagy jelszó.']);
    exit;
}

// Session beállítás
$_SESSION['user_id']  = (int)$user['id'];
$_SESSION['username'] = $user['username'];

echo json_encode([
    'success' => true,
    'message' => 'Sikeres bejelentkezés!',
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'birth_date' => $user['birth_date'],
    ]
]);