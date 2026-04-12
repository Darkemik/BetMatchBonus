<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../recaptcha_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

// reCAPTCHA v3 ellenőrzés
$recaptchaToken = $_POST['recaptcha_token'] ?? '';
$recaptchaResult = verifyRecaptcha($recaptchaToken, 'register');
if (!$recaptchaResult['success']) {
    echo json_encode(['success' => false, 'message' => $recaptchaResult['error']]);
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

// Jóváhagyási token generálás
$approvalToken = bin2hex(random_bytes(32));

// INSERT a Users táblába – is_active=0 (jóváhagyásig nem léphet be)
$stmt = $conn->prepare(
    "INSERT INTO Users (username, email, password_hash, full_name, pre_name, family_name, sure_name, mother_full_name, birthplace, birth_date, mobile_number, balance, is_active, approval_token) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0, ?)"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ssssssssssss", $username, $email, $password_hash, $full_name, $pre_name, $family_name, $sure_name, $mother_full_name, $birthplace, $birthdate_formatted, $phone, $approvalToken);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Regisztrációs hiba: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$userId = $stmt->insert_id;

// --- Képek mentése ---
$uploadDir = __DIR__ . '/../uploads/registrations/' . $userId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$savedFiles = [];
$imageFields = ['id_image_first', 'id_image_second', 'address_image'];
$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

foreach ($imageFields as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES[$field]['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            continue;
        }

        $ext = match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/gif'  => '.gif',
            'image/webp' => '.webp',
            default       => '.jpg',
        };
        $safeFilename = $field . '_' . $userId . $ext;
        $destPath = $uploadDir . $safeFilename;

        if (move_uploaded_file($_FILES[$field]['tmp_name'], $destPath)) {
            $savedFiles[$field] = $destPath;
            $relativePath = 'uploads/registrations/' . $userId . '/' . $safeFilename;
            $updImg = $conn->prepare("UPDATE Users SET $field = ? WHERE id = ?");
            $updImg->bind_param("si", $relativePath, $userId);
            $updImg->execute();
            $updImg->close();
        }
    }
}

// NEM léptetjük be a felhasználót – meg kell várnia a jóváhagyást
// $_SESSION['user_id'] = ... NEM KELL

// Wallet létrehozása 0 Ft alapegyenleggel
$initialBalance = 0.00;
$walletStmt = $conn->prepare(
    "INSERT INTO Wallets (user_id, balance) VALUES (?, ?)"
);

if ($walletStmt) {
    $walletStmt->bind_param("id", $userId, $initialBalance);
    $walletStmt->execute();
    $walletStmt->close();
}

// Automatikus bónusz hozzárendelés regisztráció után:
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

// --- Email küldés az adminnak ---
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$approveUrl = SITE_BASE_URL . '/backend/Auth/approve_registration.php?token=' . $approvalToken;
$rejectUrl  = SITE_BASE_URL . '/backend/Auth/reject_registration.php?token=' . $approvalToken;

$emailBody  = "<h2>Új regisztráció érkezett – BetMatchBonus</h2>";
$emailBody .= "<table style='border-collapse:collapse;' cellpadding='6'>";
$emailBody .= "<tr><td><b>Felhasználónév:</b></td><td>" . htmlspecialchars($username) . "</td></tr>";
$emailBody .= "<tr><td><b>Email:</b></td><td>" . htmlspecialchars($email) . "</td></tr>";
$emailBody .= "<tr><td><b>Teljes név:</b></td><td>" . htmlspecialchars($full_name) . "</td></tr>";
$emailBody .= "<tr><td><b>Születési hely:</b></td><td>" . htmlspecialchars($birthplace) . "</td></tr>";
$emailBody .= "<tr><td><b>Születési dátum:</b></td><td>" . htmlspecialchars($birthdate_formatted) . "</td></tr>";
$emailBody .= "<tr><td><b>Telefonszám:</b></td><td>" . htmlspecialchars($phone) . "</td></tr>";
$emailBody .= "<tr><td><b>Anyja neve:</b></td><td>" . htmlspecialchars($mother_full_name) . "</td></tr>";
$emailBody .= "</table>";
$emailBody .= "<br><p style='display:flex;gap:12px;justify-content:center;'>";
$emailBody .= "<a href='" . htmlspecialchars($approveUrl) . "' style='display:inline-block;padding:12px 24px;background:#28a745;color:#fff;text-decoration:none;border-radius:6px;font-size:16px;font-weight:bold;'>✅ Jóváhagyás</a>";
$emailBody .= "<a href='" . htmlspecialchars($rejectUrl) . "' style='display:inline-block;padding:12px 24px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px;font-size:16px;font-weight:bold;'>❌ Elutasítás</a>";
$emailBody .= "</p>";
$emailBody .= "<br><p style='color:#888;font-size:12px;'>Ha elutasítod, a felhasználó fiókja törlésre kerül és értesítést kap emailben.</p>";

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
    $mail->addAddress('bmbugyfelszolgalat@gmail.com', 'BetMatchBonus Admin');

    $mail->isHTML(true);
    $mail->Subject = 'Új regisztráció jóváhagyásra vár – ' . $username;
    $mail->Body    = $emailBody;

    // Képek csatolása
    foreach ($savedFiles as $fieldName => $filePath) {
        if (file_exists($filePath)) {
            $mail->addAttachment($filePath);
        }
    }

    $mail->send();
} catch (MailException $e) {
    // Email hiba nem akadályozza a regisztrációt – az admin a DB-ből is jóváhagyhatja
    error_log('Regisztrációs email küldési hiba: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'pending_approval' => true,
    'message' => 'Sikeres regisztráció! A fiókod jóváhagyásra vár. Az adminisztrátorok ellenőrzik az adataidat, és emailben értesítünk, amint a fiókod aktiválva lett. Addig kérjük, légy türelemmel!'
]);

$stmt->close();
$conn->close();