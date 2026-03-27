<?php
session_start();
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$code = isset($_POST['bonus_code']) ? trim($_POST['bonus_code']) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Kérjük, adjon meg egy bónuszkódot!']);
    exit();
}

// 1. Megkeressük a kódot a BonusCodes táblában
$stmt = $conn->prepare("SELECT * FROM BonusCodes WHERE code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$bonus = $result->fetch_assoc();
$stmt->close();

if (!$bonus) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen bónuszkód!']);
    exit();
}

// 2. Ellenőrizzük, hogy aktív-e
if (!(int)$bonus['is_active']) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód jelenleg inaktív!']);
    exit();
}

// 3. Ellenőrizzük az érvényességi időszakot (valid_from / valid_to)
$now = new DateTime();

if (!empty($bonus['valid_from'])) {
    $valid_from = new DateTime($bonus['valid_from']);
    if ($now < $valid_from) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód még nem aktiválható (érvényesség kezdete: ' . $valid_from->format('Y-m-d H:i') . ').']);
        exit();
    }
}

if (!empty($bonus['valid_to'])) {
    $valid_to = new DateTime($bonus['valid_to']);
    if ($now > $valid_to) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód lejárt (' . $valid_to->format('Y-m-d H:i') . ' után nem aktiválható)!']);
        exit();
    }
}

// 4. Ellenőrizzük a globális felhasználási limitet (usage_limit)
if (!is_null($bonus['usage_limit'])) {
    $usage_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM UserBonuses WHERE bonus_id = ?");
    $usage_stmt->bind_param("i", $bonus['id']);
    $usage_stmt->execute();
    $usage_result = $usage_stmt->get_result();
    $usage_row = $usage_result->fetch_assoc();
    $usage_stmt->close();

    if ((int)$usage_row['cnt'] >= (int)$bonus['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód elérte a maximális felhasználási korlátot!']);
        exit();
    }
}

// 5. Ellenőrizzük a felhasználónkénti limitet (per_user_limit, alapértelmezés: 1)
$per_user_limit = isset($bonus['per_user_limit']) && $bonus['per_user_limit'] > 0 ? (int)$bonus['per_user_limit'] : 1;

$check_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM UserBonuses WHERE user_id = ? AND bonus_id = ?");
$check_stmt->bind_param("ii", $user_id, $bonus['id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_row = $check_result->fetch_assoc();
$check_stmt->close();

if ((int)$check_row['cnt'] >= $per_user_limit) {
    echo json_encode(['success' => false, 'message' => 'Ezt a bónuszt már beváltotta!']);
    exit();
}

// 6. Meghatározzuk a bónusz induló státuszát és összegét
// DEPOSIT trigger esetén PENDING, egyébként ACTIVE
$status = ($bonus['bonus_trigger'] === 'DEPOSIT') ? 'PENDING' : 'ACTIVE';

$granted_amount = 0.00;
if ($bonus['bonus_trigger'] === 'MANUAL' || $bonus['bonus_trigger'] === 'AUTO') {
    $granted_amount = (float)$bonus['bonus_amount'];
}

// 7. Lejárati idő kiszámítása (csak ACTIVE státusznál)
$expires_at = null;
if ($status === 'ACTIVE' && !empty($bonus['activation_expire_hours']) && (int)$bonus['activation_expire_hours'] > 0) {
    $expires_at = date('Y-m-d H:i:s', strtotime('+' . (int)$bonus['activation_expire_hours'] . ' hours'));
}

// 8. Szükséges forgatás kiszámítása
$wagering_required = 0.00;
if ($granted_amount > 0 && !empty($bonus['wagering_multiplier']) && (float)$bonus['wagering_multiplier'] > 0) {
    $wagering_required = $granted_amount * (float)$bonus['wagering_multiplier'];
}

// 9. Tranzakció: bónusz rögzítése + bónusz egyenleg jóváírása (ha ACTIVE)
$conn->begin_transaction();

try {
    // Bónusz rögzítése a UserBonuses táblában
    $insert_stmt = $conn->prepare("
        INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount, bonus_money_amount, wagering_required, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $bonus_money = ($status === 'ACTIVE') ? $granted_amount : 0.00;
    $insert_stmt->bind_param("iisddds", $user_id, $bonus['id'], $status, $granted_amount, $bonus_money, $wagering_required, $expires_at);
    $insert_stmt->execute();
    $insert_stmt->close();

    // Ha azonnal ACTIVE, jóváírjuk a bónusz egyenleget (bonus_balance)
    if ($status === 'ACTIVE' && $granted_amount > 0) {
        $update_stmt = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
        $update_stmt->bind_param("di", $granted_amount, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    $conn->commit();

    $msg = $status === 'PENDING'
        ? 'A bónuszt sikeresen aktiváltuk! Teljesítsd a szükséges befizetést a bónusz jóváírásához.'
        : 'A bónuszt sikeresen jóváírtuk a bónusz egyenlegedre!';

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Hiba történt a bónusz mentésekor.']);
}
