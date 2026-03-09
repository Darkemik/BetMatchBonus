<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

// Adatok beolvasása
$username   = trim($_POST['username'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$birthdate  = $_POST['birthdate'] ?? '';

// 2. lépés – teljes név összeállítása
$family_name = trim($_POST['family_name'] ?? '');
$sure_name   = trim($_POST['sure_name'] ?? '');
$pre_name    = trim($_POST['pre_name'] ?? '');
$full_name   = trim($pre_name . ' ' . $family_name . ' ' . $sure_name);

// Validáció
if ($username === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen email cím!']);
    exit;
}
if (strlen($password) < 7) {
    echo json_encode(['success' => false, 'message' => 'A jelszó legalább 7 karakter legyen!']);
    exit;
}
if ($birthdate === '' || $family_name === '' || $sure_name === '') {
    echo json_encode(['success' => false, 'message' => 'A személyes adatok kitöltése kötelező!']);
    exit;
}

// 18+ ellenőrzés
$birth = new DateTime($birthdate);
$now   = new DateTime();
$age   = $now->diff($birth)->y;
if ($age < 18) {
    echo json_encode(['success' => false, 'message' => '18 éves kor alatt nem lehet regisztrálni!']);
    exit;
}

// Duplikáció ellenőrzés
$stmt = $conn->prepare("SELECT id FROM Users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Ez a felhasználónév vagy email már foglalt!']);
    $stmt->close();
    exit;
}
$stmt->close();

// Jelszó hashelés
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// INSERT a Users táblába
$stmt = $conn->prepare(
    "INSERT INTO Users (username, email, password_hash, full_name, birth_date) 
    VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssss", $username, $email, $password_hash, $full_name, $birthdate);

if ($stmt->execute()) {
    $_SESSION['user_id']  = $stmt->insert_id;
    $_SESSION['username'] = $username;
    echo json_encode(['success' => true, 'message' => 'Sikeres regisztráció!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba: ' . $stmt->error]);
}

$stmt->close();
$conn->close();