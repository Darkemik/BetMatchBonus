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
$isWeekend = ((int)date('N') >= 6);
$isBetmatchBonusDay = (date('m-d') === '05-26');

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

$bonusName = (string)($bonus['name'] ?? '');
$isBetmatchBirthdayBonus = (bool)preg_match('/^BETMATCH(?:\s*BONUS)?\s+SZ[ÜU]LET[ÉE]SNAPI\s+B[ÓO]NUSZ/ui', $bonusName);
if ($isBetmatchBirthdayBonus && !$isBetmatchBonusDay) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónusz csak május 26-án érhető el.']);
    exit();
}

// Hétköznap-only bónusz csak hétfő daily_start_time - péntek 23:59 között aktiválható
// Admin felülírás esetén (admin_force_active = 1) a hétköznapi korlátozás átugorható
$adminForceActive = !empty($bonus['admin_force_active']);
$dailyStart = $bonus['daily_start_time'] ?? null;
$isAfterDailyStart = ($dailyStart === null || date('H:i:s') >= $dailyStart);
$isWeekdayWindow = ($isWeekday && $isAfterDailyStart);

if (!empty($bonus['valid_weekdays_only']) && !$isWeekdayWindow && !$adminForceActive) {
    $startLabel = $dailyStart ? substr($dailyStart, 0, 5) : '00:01';
    echo json_encode(['success' => false, 'message' => "Ez a bónuszkód csak hétköznapokon {$startLabel} és 23:59 között aktiválható!"]);
    exit();
}

// Hétvégi bónusz csak szombat-vasárnap aktiválható.
$bonusCode = strtoupper((string)($bonus['code'] ?? ''));
if ($bonusCode === 'HETVEGI5K' && !$isWeekend) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód csak szombaton és vasárnap aktiválható!']);
    exit();
}

// Nem hétköznap-only bónusznál marad a manuális is_active ellenőrzés
if (empty($bonus['valid_weekdays_only']) && (int)$bonus['is_active'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Ez a bónuszkód jelenleg inaktív.']);
    exit();
}

// 2. Leellenőrizzük, hogy a user használta-e már ezt a bónuszt
$perUserLimit = (int)($bonus['per_user_limit'] ?? 1);
$hasLimit = ($perUserLimit > 0);
$check_query = "SELECT id, status FROM UserBonuses WHERE user_id = ? AND bonus_id = ?";
$bind_types = "ii";
$today_from = null;
$tomorrow_from = null;

// Hétköznap-only bónusz naponta egyszer aktiválható (daily_start_time-től)
if (!empty($bonus['valid_weekdays_only'])) {
    $dayStart = $dailyStart ? date('Y-m-d') . ' ' . $dailyStart : date('Y-m-d 00:01:00');
    $check_query .= " AND created_at >= ? AND created_at < ?";
    $bind_types = "iiss";
    $today_from = $dayStart;
    $tomorrow_from = date('Y-m-d', strtotime('+1 day')) . ' ' . ($dailyStart ?? '00:01:00');
}

$check_stmt = $conn->prepare($check_query);
if (!empty($bonus['valid_weekdays_only'])) {
    $check_stmt->bind_param($bind_types, $user_id, $bonus['id'], $today_from, $tomorrow_from);
} else {
    $check_stmt->bind_param($bind_types, $user_id, $bonus['id']);
}
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$claimCount = $check_result->num_rows;
$already_claimed = ($hasLimit && $claimCount >= $perUserLimit);
$existing_claim = $claimCount > 0 ? $check_result->fetch_assoc() : null;
$check_stmt->close();

// Aktív/Pending példány ellenőrzése: limitelt bónusznál csak akkor tiltunk,
// ha az aktív/pending példányok száma elérte a per_user_limit értéket.
$activeCheckStmt = $conn->prepare(" 
    SELECT COUNT(*) AS cnt FROM UserBonuses
    WHERE user_id = ? AND bonus_id = ?
      AND status IN ('ACTIVE', 'PENDING')
      AND used = 0
      AND (expires_at IS NULL OR expires_at > NOW())
");
$activeCheckStmt->bind_param("ii", $user_id, $bonus['id']);
$activeCheckStmt->execute();
$activeRow = $activeCheckStmt->get_result()->fetch_assoc();
$activeCount = (int)($activeRow['cnt'] ?? 0);
$activeCheckStmt->close();

$activeLimitReached = $hasLimit ? ($activeCount >= $perUserLimit) : false;
if ($activeLimitReached) {
    echo json_encode(['success' => false, 'message' => 'Ebből a bónuszból jelenleg elérted az egyidejű aktív/várakozó limiteket. Várd meg, amíg lejár vagy teljesíted!']);
    exit();
}

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
        ? 'Ezt a hétköznapi bónuszt ma már beváltottad! Holnap ' . ($dailyStart ? substr($dailyStart, 0, 5) : '00:01') . ' után újra aktiválhatod.'
        : ($perUserLimit > 1 ? 'Ezt a bónuszt már elérted a maximális ' . $perUserLimit . ' beváltást!' : 'Ezt a bónuszt már beváltottad!');
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// 3. Bónusz összeg és státusz meghatározása
$bonusTrigger = strtoupper((string)($bonus['bonus_trigger'] ?? ''));
$isDepositTriggered = ($bonusTrigger === 'DEPOSIT');
$isBetTriggered = ($bonusTrigger === 'BET');
$isLossTriggered = ($bonusTrigger === 'LOSS');
$status = ($isDepositTriggered || $isBetTriggered) ? 'PENDING' : 'ACTIVE';

// Granted amount kiszámítása
$granted_amount = 0.00;
if (!$isDepositTriggered && !$isLossTriggered) {
    if ($bonus['max_bonus_amount'] > 0) {
        $granted_amount = $bonus['max_bonus_amount'];
    } elseif ($bonus['bonus_amount'] > 0) {
        $granted_amount = $bonus['bonus_amount'];
    }
}

// Lejárati dátum
$expires_at = null;
$expireHours = isset($bonus['activation_expire_hours']) ? (int)$bonus['activation_expire_hours'] : 0;
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

// FREE_BET típus kezelése
$betRewardType = strtoupper((string)($bonus['bet_reward_type'] ?? ''));
$isFreeBetReward = ($betRewardType === 'FREE_BET');

// Azonnali bónuszok egyedi egyenlege
$individualBalance = (!$isDepositTriggered && !$isBetTriggered && $granted_amount > 0 && !$isFreeBetReward) ? $granted_amount : 0.00;
$freeBetAmount = (!$isDepositTriggered && !$isBetTriggered && $granted_amount > 0 && $isFreeBetReward) ? $granted_amount : 0.00;
$bonusMoneyAmount = $individualBalance;

$insert_stmt = $conn->prepare("
    INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount, bonus_balance, free_bet_amount, bonus_money_amount, wagering_required, expires_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insert_stmt->bind_param("iisddddds", $user_id, $bonus['id'], $status, $granted_amount, $individualBalance, $freeBetAmount, $bonusMoneyAmount, $wagering_required, $expires_at);

if ($insert_stmt->execute()) {
    // Users.bonus_balance szinkronizálása (összes aktív bónusz egyenlegek összege)
    if ($individualBalance > 0) {
        $syncStmt = $conn->prepare("
            UPDATE Users SET bonus_balance = (
                SELECT COALESCE(SUM(ub.bonus_balance), 0) FROM UserBonuses ub
                WHERE ub.user_id = ? AND ub.status = 'ACTIVE' AND ub.used = 0
                  AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
            ) WHERE id = ?
        ");
        $syncStmt->bind_param("ii", $user_id, $user_id);
        $syncStmt->execute();
        $syncStmt->close();
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
    } elseif ($isLossTriggered) {
        $cbPercent = (float)($bonus['match_percent'] ?? 0);
        $cbMinStake = (float)($bonus['min_deposit'] ?? 0);
        $cbMinOdds = (float)($bonus['min_odds'] ?? 0);
        $msg = 'Cashback bónusz aktiválva! Ha egy vesztes fogadásod megfelel a feltételeknek, ' . number_format($cbPercent, 0) . '% Free Bet-et kapsz. Naponta egyszer.';
        if ($cbMinStake > 0) {
            $msg .= ' Min. tét: ' . number_format($cbMinStake, 0, ',', ' ') . ' Ft.';
        }
        if ($cbMinOdds > 0) {
            $msg .= ' Min. odds: ' . number_format($cbMinOdds, 2, ',', '') . '.';
        }
    } else {
        $msg = 'Bónusz sikeresen beváltva! ' . number_format($granted_amount, 0, ',', ' ') . ' FT jóváírva a fiókodban.';
        if ($wagering_required > 0) {
            $msg .= ' Forgatási követelmény: ' . number_format($wagering_required, 0, ',', ' ') . ' FT.';
        }
    }
        
    require_once dirname(__DIR__) . '/Auth/audit_helper.php';
    log_activity($user_id, 'bonus', 'Bónusz aktiválva: ' . ($bonus['name'] ?? 'Ismeretlen') . '. Összeg: ' . number_format($granted_amount, 0, ',', '.') . ' Ft.');

    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba történt a bónusz mentésekor.']);
}
$insert_stmt->close();