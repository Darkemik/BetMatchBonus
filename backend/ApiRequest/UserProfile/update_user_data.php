<?php
header('Content-Type: application/json');
require_once "../connect.php";
require_once dirname(dirname(__DIR__)) . '/backend/Auth/settings_helper.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Profil frissítese
if ($action === 'update_profile') {
    $full_name = htmlspecialchars($_POST['full_name'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $country = htmlspecialchars($_POST['country'] ?? '');
    $city = htmlspecialchars($_POST['city'] ?? '');
    $postal_code = htmlspecialchars($_POST['postal_code'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');

    $query = "UPDATE Users SET full_name = ?, phone = ?, country = ?, city = ?, postal_code = ?, address = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssi", $full_name, $phone, $country, $city, $postal_code, $address, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profil sikeresen frissítve']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $stmt->error]);
    }
    $stmt->close();
}

// Jelszó módosítása
elseif ($action === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Összes mező kitöltése kötelező']);
        exit();
    }

    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['error' => 'Az új jelszavak nem egyeznek']);
        exit();
    }

    $minPwLen = get_setting_int('min_password_length', 7);
    if (strlen($new_password) < $minPwLen) {
        http_response_code(400);
        echo json_encode(['error' => 'A jelszó legalább ' . $minPwLen . ' karakter hosszú kell legyen']);
        exit();
    }

    // Jelenlegi jelszó ellenőrzése
    $query = "SELECT password_hash FROM Users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($current_password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'A jelenlegi jelszó helytelen']);
        exit();
    }

    // Új jelszó beállítása
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $update_query = "UPDATE Users SET password_hash = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_password_hash, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jelszó sikeresen megváltoztatva']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $update_stmt->error]);
    }
    $update_stmt->close();
}

// Befizetés
elseif ($action === 'create_deposit') {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A befizetési összeg nagyobb kell legyen, mint 0']);
        exit();
    }

    if (empty($payment_method)) {
        http_response_code(400);
        echo json_encode(['error' => 'Válassz fizetési módot']);
        exit();
    }

    $transaction_id = uniqid('DEP_');
    $type = 'deposit';
    $status = 'pending';

    $query = "INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdsss", $user_id, $type, $amount, $payment_method, $status, $transaction_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'transaction_id' => $transaction_id, 'message' => 'Befizetési kérelem elkészült']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $stmt->error]);
    }
    $stmt->close();
}

// Kifizetés
elseif ($action === 'create_withdrawal') {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
    $account_holder = htmlspecialchars($_POST['account_holder'] ?? '');
    $account_number = htmlspecialchars($_POST['account_number'] ?? '');

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A kifizetési összeg nagyobb kell legyen, mint 0']);
        exit();
    }

    if (empty($payment_method) || empty($account_holder) || empty($account_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tölts ki minden szükséges mezőt']);
        exit();
    }

    // Egyenleg ellenőrzése
    $balance_query = "SELECT balance FROM Users WHERE id = ?";
    $balance_stmt = $conn->prepare($balance_query);
    $balance_stmt->bind_param("i", $user_id);
    $balance_stmt->execute();
    $balance_result = $balance_stmt->get_result();
    $balance_data = $balance_result->fetch_assoc();
    $balance_stmt->close();

    if ($balance_data['balance'] < $amount) {
        http_response_code(400);
        echo json_encode(['error' => 'A kifizetési összeg nem haladhatja meg az egyenlegedet']);
        exit();
    }

    $transaction_id = uniqid('WTH_');
    $type = 'withdrawal';
    $status = 'pending';

    $query = "INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdsss", $user_id, $type, $amount, $payment_method, $status, $transaction_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'transaction_id' => $transaction_id, 'message' => 'Kifizetési kérelem benyújtva']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $stmt->error]);
    }
    $stmt->close();
}

// Ismeretlen akció
else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
?>
