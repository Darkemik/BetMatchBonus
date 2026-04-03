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
$_SESSION['session_bet_total'] = 0.0;
$_SESSION['login_started_at'] = time();

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

// Automatikus bónusz hozzárendelés regisztráció után:
// - auto_assign = 1 bónuszok
// - Üdvözlő 1. lépés (kód nélküli, step 1, WELCOME típus)
$assignStmt = $conn->prepare(" 
    INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount, wagering_required, expires_at)
    SELECT
        ?,
        bc.id,
        CASE
            WHEN UPPER(COALESCE(bc.bonus_trigger, '')) = 'DEPOSIT' THEN 'PENDING'
            ELSE 'ACTIVE'
        END AS status,
        CASE
            WHEN UPPER(COALESCE(bc.bonus_trigger, '')) = 'DEPOSIT' THEN 0.00
            WHEN COALESCE(bc.max_bonus_amount, 0) > 0 THEN bc.max_bonus_amount
            ELSE COALESCE(bc.bonus_amount, 0)
        END AS granted_amount,
        CASE
            WHEN UPPER(COALESCE(bc.bonus_trigger, '')) = 'DEPOSIT' THEN 0.00
            WHEN COALESCE(bc.wagering_multiplier, 0) > 0 THEN
                (
                    CASE
                        WHEN COALESCE(bc.max_bonus_amount, 0) > 0 THEN bc.max_bonus_amount
                        ELSE COALESCE(bc.bonus_amount, 0)
                    END
                ) * bc.wagering_multiplier
            ELSE 0.00
        END AS wagering_required,
        CASE
            WHEN UPPER(COALESCE(bc.bonus_trigger, '')) = 'DEPOSIT' THEN NULL
            WHEN COALESCE(bc.activation_expire_hours, 0) > 0 THEN DATE_ADD(NOW(), INTERVAL bc.activation_expire_hours HOUR)
            ELSE NULL
        END AS expires_at
    FROM BonusCodes bc
    WHERE bc.is_active = 1
      AND (bc.valid_from IS NULL OR bc.valid_from <= NOW())
      AND (bc.valid_to IS NULL OR bc.valid_to >= NOW())
      AND (
          bc.auto_assign = 1
          OR (bc.bonus_type_id = 1 AND bc.is_step_bonus = 1 AND bc.step_number = 1 AND bc.code IS NULL)
      )
      AND NOT EXISTS (
          SELECT 1
          FROM UserBonuses ub
          WHERE ub.user_id = ?
            AND ub.bonus_id = bc.id
      )
");

if ($assignStmt) {
    $assignStmt->bind_param("ii", $userId, $userId);
    $assignStmt->execute();
    $assignStmt->close();
}

echo json_encode(['success' => true, 'message' => 'Sikeres regisztráció!']);

$stmt->close();
$conn->close();