<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

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
$birthplace_city_id  = (int)($_POST['birthplace_city_id'] ?? 0);

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
if ($birthdate === '' || $family_name === '' || $sure_name === '' || $phone === '' || $birthplace_city_id <= 0) {
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
if ($birthplace_city_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Születési hely kiválasztása kötelező!']);
    exit;
}

// Jelszó hashelés
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// INSERT a Users táblába
$stmt = $conn->prepare(
    "INSERT INTO Users (username, email, password_hash, full_name, birth_date, mobile_number, birthplace) 
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssssi", $username, $email, $password_hash, $full_name, $birthdate, $phone, $birthplace_city_id);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    
    // Wallet létrehozása 50000 Ft alapegyenleggel
    $initialBalance = 50000;
    $walletStmt = $conn->prepare(
        "INSERT INTO Wallets (user_id, balance) VALUES (?, ?)"
    );
    $walletStmt->bind_param("id", $userId, $initialBalance);
    $walletStmt->execute();
    $walletStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Sikeres regisztráció!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba: ' . $stmt->error]);
}

$stmt->close();
$conn->close();