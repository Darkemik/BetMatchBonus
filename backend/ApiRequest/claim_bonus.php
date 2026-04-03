<?php
session_start();
require_once dirname(__DIR__) . '/connect.php';
date_default_timezone_set('Europe/Budapest');

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
$code = isset($_POST['bonus_code']) ? trim(strtoupper($_POST['bonus_code'])) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Kérlek, adj meg egy bónuszkódot!']);
    exit();
}

// 1. Megkeressük a kódot a BonusCodes táblában
$stmt = $conn->prepare("SELECT * FROM BonusCodes WHERE code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$bonus = $result->fetch_assoc();
$stmt->close();

$isWeekday = ((int)date('N') <= 5);
$isAfterDailyRefresh = (date('H:i') >= '00:01');
$isWeekdayWindow = ($isWeekday && $isAfterDailyRefresh);

if (!$bonus) {
    // Megnézzük, hogy létezik-e egyáltalán a kód (de inaktív)
    $check = $conn->prepare("SELECT code, valid_weekdays_only FROM BonusCodes WHERE code = ? LIMIT 1");
    $check->bind_param("s", $code);
    $check->execute();
    $check_res = $check->get_result();
    $inactive_bonus = $check_res->fetch_assoc();
    $check->close();

    if ($inactive_bonus && $inactive_bonus['valid_weekdays_only']) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód csak hétköznapokon (hétfő-péntek) érvényes! Gyere vissza hétköznap.']);
    } else if ($inactive_bonus) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód jelenleg inaktív.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen bónuszkód!']);
    }
    exit();
}

// Hétköznap-only bónusz csak hétfő 00:01 - péntek 23:59 között aktiválható
if (!empty($bonus['valid_weekdays_only']) && !$isWeekdayWindow) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód csak hétköznapokon 00:01 és 23:59 között aktiválható!']);
    exit();
}

// Nem hétköznap-only bónusznál marad a manuális is_active ellenőrzés
if (empty($bonus['valid_weekdays_only']) && (int)$bonus['is_active'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód jelenleg inaktív.']);
    exit();
}

// 2. Leellenőrizzük, hogy a user használta-e már ezt a bónuszt
$check_query = "SELECT id FROM UserBonuses WHERE user_id = ? AND bonus_id = ?";
$bind_types = "ii";
$today_from = null;
$tomorrow_from = null;

// Hétköznap-only bónusz naponta egyszer aktiválható (00:01-től)
if (!empty($bonus['valid_weekdays_only'])) {
    $check_query .= " AND created_at >= ? AND created_at < ?";
    $bind_types = "iiss";
    $today_from = date('Y-m-d 00:01:00');
    $tomorrow_from = date('Y-m-d 00:01:00', strtotime('+1 day'));
}

$check_stmt = $conn->prepare($check_query);
if (!empty($bonus['valid_weekdays_only'])) {
    $check_stmt->bind_param($bind_types, $user_id, $bonus['id'], $today_from, $tomorrow_from);
} else {
    $check_stmt->bind_param($bind_types, $user_id, $bonus['id']);
}
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$already_claimed = $check_result->num_rows > 0;
$check_stmt->close();

if ($already_claimed) {
    $msg = !empty($bonus['valid_weekdays_only'])
        ? 'Ezt a hétköznapi bónuszt ma már beváltottad! Holnap 00:01 után újra aktiválhatod.'
        : 'Ezt a bónuszt már beváltottad!';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// 3. Bónusz összeg és státusz meghatározása
$isDepositTriggered = (strtoupper((string)($bonus['bonus_trigger'] ?? '')) === 'DEPOSIT');
$status = $isDepositTriggered ? 'PENDING' : 'ACTIVE';

// Granted amount kiszámítása
$granted_amount = 0.00;
if (!$isDepositTriggered) {
    if ($bonus['max_bonus_amount'] > 0) {
        $granted_amount = $bonus['max_bonus_amount'];
    } elseif ($bonus['bonus_amount'] > 0) {
        $granted_amount = $bonus['bonus_amount'];
    }
}

// Lejárati dátum
$expires_at = null;
if (!$isDepositTriggered && isset($bonus['activation_expire_hours']) && $bonus['activation_expire_hours'] > 0) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$bonus['activation_expire_hours']} hours"));
}

// Forgatási követelmény
$wagering_required = 0.00;
if ($granted_amount > 0 && isset($bonus['wagering_multiplier']) && $bonus['wagering_multiplier'] > 0) {
    $wagering_required = $granted_amount * $bonus['wagering_multiplier'];
}

// 4. Bónusz hozzárendelése a felhasználóhoz

$insert_stmt = $conn->prepare("
    INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount, wagering_required, expires_at) 
    VALUES (?, ?, ?, ?, ?, ?)
");
$insert_stmt->bind_param("iisdds", $user_id, $bonus['id'], $status, $granted_amount, $wagering_required, $expires_at);

if ($insert_stmt->execute()) {
    // Csak az azonnal aktiválódó bónusz kerül jóváírásra itt.
    // DEPOSIT trigger esetén a jóváírás a stripe_payment_process.php-ban történik.
    if (!$isDepositTriggered && $granted_amount > 0) {
        $wallet_stmt = $conn->prepare("UPDATE Wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?");
        $wallet_stmt->bind_param("di", $granted_amount, $user_id);
        $wallet_stmt->execute();
        $wallet_stmt->close();
    }

    if ($isDepositTriggered) {
        $minDeposit = (float)($bonus['min_deposit'] ?? 0);
        $msg = 'Bónusz aktiválva! A jóváírás a befizetés után történik.';
        if ($minDeposit > 0) {
            $msg .= ' Minimum befizetés: ' . number_format($minDeposit, 0, ',', ' ') . ' FT.';
        }
    } else {
        $msg = 'Bónusz sikeresen beváltva! ' . number_format($granted_amount, 0, ',', ' ') . ' FT jóváírva a fiókodban.';
        if ($wagering_required > 0) {
            $msg .= ' Forgatási követelmény: ' . number_format($wagering_required, 0, ',', ' ') . ' FT.';
        }
    }
        
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba történt a bónusz mentésekor.']);
}
$insert_stmt->close();