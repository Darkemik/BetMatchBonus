<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

// Adatok beolvasása
$username            = trim($_POST['username'] ?? '');
$email               = trim($_POST['email'] ?? '');
$password            = $_POST['password'] ?? '';
$phone               = trim($_POST['phone'] ?? '');
$birthdate           = $_POST['birthdate'] ?? '';
$birthplace          = trim($_POST['birthplace'] ?? '');

// 2. lépés – teljes név összeállítása
$family_name = trim($_POST['family_name'] ?? '');
$sure_name   = trim($_POST['sure_name'] ?? '');
$pre_name    = trim($_POST['pre_name'] ?? '');
$full_name   = trim($pre_name . ' ' . $family_name . ' ' . $sure_name);
$mother_full_name = trim($_POST['mother_full_name'] ?? '');

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
if ($birthdate === '' || $family_name === '' || $sure_name === '' || $phone === '' || $birthplace === '') {
    echo json_encode(['success' => false, 'message' => 'A személyes adatok kitöltése kötelező!']);
    exit;
}
if (strlen($phone) < 11) {
    echo json_encode(['success' => false, 'message' => 'A telefonszám legalább 11 számjegy legyen!']);
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

// Város ellenőrzés
if ($birthplace === '') {
    echo json_encode(['success' => false, 'message' => 'Születési hely megadása kötelező!']);
    exit;
}

// Jelszó hashelés
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// birth_date konvertálása DATE formátumra
$birthdate_formatted = date('Y-m-d', strtotime($birthdate));

// INSERT a Users táblába (kezdő egyenleg: 0 Ft)
$stmt = $conn->prepare(
    "INSERT INTO Users (username, email, password_hash, full_name, pre_name, family_name, sure_name, mother_full_name, birthplace, birth_date, mobile_number, balance) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00)"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssssssssss", $username, $email, $password_hash, $full_name, $pre_name, $family_name, $sure_name, $mother_full_name, $birthplace, $birthdate_formatted, $phone);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Regisztrációs hiba: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$userId = $stmt->insert_id;
$_SESSION['user_id']  = $userId;
$_SESSION['username'] = $username;

// Wallet létrehozása 0 Ft alapegyenleggel
$initialBalance = 0.00;
$walletStmt = $conn->prepare(
    "INSERT INTO Wallets (user_id, balance) VALUES (?, ?)"
);

if (!$walletStmt) {
    echo json_encode(['success' => false, 'message' => 'Wallet hiba: ' . $conn->error]);
    $stmt->close();
    exit;
}

$walletStmt->bind_param("id", $userId, $initialBalance);

if (!$walletStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Wallet létrehozási hiba: ' . $walletStmt->error]);
    $walletStmt->close();
    $stmt->close();
    exit;
}

$walletStmt->close();

echo json_encode(['success' => true, 'message' => 'Sikeres regisztráció!']);

$stmt->close();
$conn->close();