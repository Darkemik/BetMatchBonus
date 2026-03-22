<?php
session_start();
require_once "connect.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$code = isset($_POST['bonus_code']) ? trim($_POST['bonus_code']) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Kérjük, adjon meg egy bónuszkódot!']);
    exit();
}

// 1. Megkeressük a kódot a BonusCodes táblában, ellenőrizzük hogy aktív-e
$stmt = $conn->prepare("SELECT * FROM BonusCodes WHERE code = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$bonus = $result->fetch_assoc();
$stmt->close();

if (!$bonus) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen vagy inaktív bónuszkód!']);
    exit();
}

// 2. Leellenőrizzük, hogy a user használta-e már ezt a bónuszt
$check_stmt = $conn->prepare("SELECT id FROM UserBonuses WHERE user_id = ? AND bonus_id = ?");
$check_stmt->bind_param("ii", $user_id, $bonus['id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$already_claimed = $check_result->num_rows > 0;
$check_stmt->close();

if ($already_claimed) {
    echo json_encode(['success' => false, 'message' => 'Ezt a bónuszt már beváltotta!']);
    exit();
}

// 3. Beállítjuk az induló státuszt
// Ha 'DEPOSIT' (befizetéshez kötött), akkor 'PENDING' lesz, ha 'BET' vagy 'MANUAL', lehet egyből 'ACTIVE' (vagy ahogy a logikád megkívánja)
$status = ($bonus['bonus_trigger'] === 'DEPOSIT') ? 'PENDING' : 'ACTIVE';

// Mivel még nem feltétlenül teljesítette a feltételeket, 0 granted_amount-tal rögzítjük (vagy fix összeggel, ha nincs feltétel)
$granted_amount = 0.00;
if ($bonus['bonus_trigger'] === 'MANUAL' || $bonus['bonus_trigger'] === 'AUTO') {
    $granted_amount = $bonus['bonus_amount'];
}

// Ha a státusz ACTIVE, számítsuk ki a lejárati dátumot
$expires_at = null;
if ($status === 'ACTIVE' && $bonus['activation_expire_hours'] > 0) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$bonus['activation_expire_hours']} hours"));
}

// Szükséges forgatás (wagering_required) kiszámítása, ha már megkapja az összeget
$wagering_required = 0.00;
if ($granted_amount > 0 && $bonus['wagering_multiplier'] > 0) {
    $wagering_required = $granted_amount * $bonus['wagering_multiplier'];
}

// 4. Bónusz beszúrása a userhez
$insert_stmt = $conn->prepare("
    INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount, wagering_required, expires_at) 
    VALUES (?, ?, ?, ?, ?, ?)
");
$insert_stmt->bind_param("iisdds", $user_id, $bonus['id'], $status, $granted_amount, $wagering_required, $expires_at);

if ($insert_stmt->execute()) {
    $msg = $status === 'PENDING' 
        ? 'A bónuszt sikeresen aktiváltuk! A feltételek (pl. befizetés) teljesítése után írjuk jóvá.' 
        : 'A bónuszt sikeresen jóváírtuk!';
        
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba történt a bónusz mentésekor.']);
}
$insert_stmt->close();