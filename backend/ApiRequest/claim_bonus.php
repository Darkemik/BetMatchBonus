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
$bonusId = isset($_POST['bonus_id']) ? (int)$_POST['bonus_id'] : 0;

if (empty($code) && $bonusId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Kérlek, adj meg egy bónuszkódot, vagy válassz bónuszt!']);
    exit();
}

// 1. Megkeressük a bónuszt (kód alapján vagy kód nélküli bónusznál ID alapján)
if (!empty($code)) {
    $stmt = $conn->prepare("SELECT * FROM BonusCodes WHERE code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
} else {
    $stmt = $conn->prepare("SELECT * FROM BonusCodes WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $bonusId);
}
$stmt->execute();
$result = $stmt->get_result();
$bonus = $result->fetch_assoc();
$stmt->close();

$isWeekday = ((int)date('N') <= 5);
$isAfterDailyRefresh = (date('H:i') >= '00:01');
$isWeekdayWindow = ($isWeekday && $isAfterDailyRefresh);

if (!$bonus) {
    if ($bonusId > 0 && empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Ez a bónusz jelenleg nem elérhető.']);
        exit();
    }

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

if ($bonusId > 0 && empty($code) && !empty($bonus['code'])) {
    echo json_encode(['success' => false, 'message' => 'Ehhez a bónuszhoz kód szükséges.']);
    exit();
}

// DARTS bónusz csak akkor igényelhető, ha holnap van darts meccs,
// és az előző nap már elmúlt 12:00.
$isDartsBonus = (strtoupper((string)($bonus['code'] ?? '')) === 'DARTSBONUSZ5K');
if ($isDartsBonus) {
    $todayNoon = date('Y-m-d 12:00:00');
    $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $dayAfterTomorrowStart = date('Y-m-d 00:00:00', strtotime('+2 day'));

    $isDartsClaimWindow = false;
    if (date('Y-m-d H:i:s') >= $todayNoon) {
        $dartsTomorrowStmt = $conn->prepare(" 
            SELECT 1
            FROM Events e
            INNER JOIN Sports s ON s.id = e.sport_id
            WHERE e.start_time >= ?
              AND e.start_time < ?
              AND (UPPER(s.name) = 'DARTS' OR s.api_id = 78)
            LIMIT 1
        ");
        $dartsTomorrowStmt->bind_param("ss", $tomorrowStart, $dayAfterTomorrowStart);
        $dartsTomorrowStmt->execute();
        $isDartsClaimWindow = $dartsTomorrowStmt->get_result()->num_rows > 0;
        $dartsTomorrowStmt->close();
    }

    if (!$isDartsClaimWindow) {
        echo json_encode(['success' => false, 'message' => 'A darts bónusz ma csak akkor elérhető, ha holnap van darts mérkőzés, és már elmúlt 12:00.']);
        exit();
    }
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

// Többlépcsős bónusznál (2. lépcsőtől) csak akkor engedjük a claimet,
// ha az előző lépcső COMPLETED.
if ((int)($bonus['is_step_bonus'] ?? 0) === 1 && (int)($bonus['step_number'] ?? 0) > 1) {
    $currentStep = (int)$bonus['step_number'];
    $previousStep = $currentStep - 1;
    $parentBonusId = isset($bonus['parent_bonus_id']) ? (int)$bonus['parent_bonus_id'] : 0;

    if ($parentBonusId > 0) {
        $prevBonusStmt = $conn->prepare(" 
            SELECT id
            FROM BonusCodes
            WHERE is_step_bonus = 1
              AND bonus_type_id = ?
              AND step_number = ?
              AND (id = ? OR parent_bonus_id = ?)
            LIMIT 1
        ");
        $prevBonusStmt->bind_param("iiii", $bonus['bonus_type_id'], $previousStep, $parentBonusId, $parentBonusId);
    } else {
        $prevBonusStmt = $conn->prepare(" 
            SELECT id
            FROM BonusCodes
            WHERE is_step_bonus = 1
              AND bonus_type_id = ?
              AND step_number = ?
            LIMIT 1
        ");
        $prevBonusStmt->bind_param("ii", $bonus['bonus_type_id'], $previousStep);
    }
    $prevBonusStmt->execute();
    $prevBonusRes = $prevBonusStmt->get_result();
    $prevBonusRow = $prevBonusRes->fetch_assoc();
    $prevBonusStmt->close();

    if (!$prevBonusRow || empty($prevBonusRow['id'])) {
        echo json_encode(['success' => false, 'message' => 'Az előző bónuszlépcső nem található.']);
        exit();
    }

    $prevCompletedStmt = $conn->prepare(" 
        SELECT id
        FROM UserBonuses
        WHERE user_id = ?
          AND bonus_id = ?
          AND status = 'COMPLETED'
        LIMIT 1
    ");
    $prevCompletedStmt->bind_param("ii", $user_id, $prevBonusRow['id']);
    $prevCompletedStmt->execute();
    $prevCompletedRes = $prevCompletedStmt->get_result();
    $isPrevCompleted = $prevCompletedRes->num_rows > 0;
    $prevCompletedStmt->close();

    if (!$isPrevCompleted) {
        echo json_encode(['success' => false, 'message' => 'Előbb az előző üdvözlő lépcsőt kell teljesítened.']);
        exit();
    }
}

// 2. Leellenőrizzük, hogy a user használta-e már ezt a bónuszt
$check_query = "SELECT id, status FROM UserBonuses WHERE user_id = ? AND bonus_id = ?";
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
$existing_claim = $already_claimed ? $check_result->fetch_assoc() : null;
$check_stmt->close();

if ($already_claimed) {
    if ($bonusId > 0 && empty($code)) {
        $isDepositTriggered = (strtoupper((string)($bonus['bonus_trigger'] ?? '')) === 'DEPOSIT');
        if ($isDepositTriggered) {
            $minDeposit = (float)($bonus['min_deposit'] ?? 0);
            $msg = 'Bónusz már aktiválva van nálad. A jóváírás a befizetés után történik.';
            if ($minDeposit > 0) {
                $msg .= ' Minimum befizetés: ' . number_format($minDeposit, 0, ',', ' ') . ' FT.';
            }
            echo json_encode(['success' => true, 'message' => $msg, 'status' => $existing_claim['status'] ?? null]);
            exit();
        }

        echo json_encode(['success' => true, 'message' => 'Bónusz már aktiválva van a fiókodban.', 'status' => $existing_claim['status'] ?? null]);
        exit();
    }

    $msg = !empty($bonus['valid_weekdays_only'])
        ? 'Ezt a hétköznapi bónuszt ma már beváltottad! Holnap 00:01 után újra aktiválhatod.'
        : 'Ezt a bónuszt már beváltottad!';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// 3. Bónusz összeg és státusz meghatározása
$bonusTrigger = strtoupper((string)($bonus['bonus_trigger'] ?? ''));
$isDepositTriggered = ($bonusTrigger === 'DEPOSIT');
$isBetTriggered = ($bonusTrigger === 'BET');
$status = ($isDepositTriggered || $isBetTriggered) ? 'PENDING' : 'ACTIVE';

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
$expireHours = isset($bonus['activation_expire_hours']) ? (int)$bonus['activation_expire_hours'] : 0;
$isWelcomeStep2 = ((int)($bonus['bonus_type_id'] ?? 0) === 1)
    && ((int)($bonus['is_step_bonus'] ?? 0) === 1)
    && ((int)($bonus['step_number'] ?? 0) === 2);
if ($expireHours <= 0 && $isWelcomeStep2) {
    $expireHours = 48;
}
if (!empty($bonus['valid_weekdays_only'])) {
    $daysUntilFriday = max(0, 5 - (int)date('N'));
    $expires_at = date('Y-m-d 23:59:00', strtotime('+' . $daysUntilFriday . ' day'));
} elseif ($expireHours > 0) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expireHours} hours"));
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
    } elseif ($isBetTriggered) {
        $minStake = (float)($bonus['min_deposit'] ?? 0);
        $minCombo = (int)($bonus['min_combo'] ?? 0);
        $minOdds = (float)($bonus['min_odds'] ?? 0);
        $msg = 'Bónusz aktiválva! A jóváírás a kvalifikáló fogadás után történik.';
        if ($minStake > 0) {
            $msg .= ' Minimum tét: ' . number_format($minStake, 0, ',', ' ') . ' FT.';
        }
        if ($minCombo > 0 || $minOdds > 0) {
            $msg .= ' Követelmény: legalább ' . max(1, $minCombo) . '-es kötés, min. ' . number_format($minOdds, 0, ',', ' ') . '-es össz odds.';
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