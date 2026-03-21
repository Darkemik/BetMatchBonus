<?php
header('Content-Type: application/json');
require_once "../connect.php";

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Tranzakciók lekérése
if ($action === 'get_transactions') {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    $type = htmlspecialchars($_GET['type'] ?? '');

    $query = "SELECT id, type, amount, payment_method, status, transaction_id, created_at FROM Transactions WHERE user_id = ?";
    $params = [$user_id];
    $types = "i";

    if (!empty($type)) {
        $query .= " AND type = ?";
        $params[] = $type;
        $types .= "s";
    }

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $transactions]);
}

// Tranzakció státusza
elseif ($action === 'get_transaction_stats') {
    $query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM Transactions WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $stats]);
}

// Bónuszok lekérése
elseif ($action === 'get_bonuses') {
    $query = "SELECT id, bonus_type, amount, status, valid_from, valid_until, created_at FROM UserBonuses WHERE user_id = ? ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonuses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $bonuses]);
}

// Tevékenységi napló
elseif ($action === 'get_activities') {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $query = "SELECT id, activity_type, description, ip_address, created_at FROM ActivityLog WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $activities]);
}

// Felhasználó adatai
elseif ($action === 'get_user_data') {
    $query = "SELECT id, username, email, full_name, phone, country, city, postal_code, address, birth_date, balance, created_at FROM Users WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $user]);
}

// Ismeretlen akció
else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
?>
