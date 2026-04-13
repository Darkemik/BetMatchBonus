<?php
session_start();
require_once dirname(__DIR__) . '/connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$isWeekday = ((int)date('N') <= 5);

$isGuest = !isset($_SESSION['user_id']);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$todayFrom = date('Y-m-d 00:01:00'); // fallback, per-bonus override below
$tomorrowFrom = date('Y-m-d 00:01:00', strtotime('+1 day'));

// Lekérdezés: csak aktív bónuszok
$query = "SELECT id, code, name, description, bonus_amount, min_deposit, max_bonus_amount, match_percent, 
                 is_step_bonus, step_number, bonus_type_id, valid_weekdays_only, is_active,
                 daily_start_time, admin_force_active
          FROM BonusCodes 
          WHERE is_active = 1
            AND birthday_bonus = 0
          ORDER BY id ASC";

$result = $conn->query($query);
$bonuses = [];

// Egyszerre csak egy bónusz lehet igényelve (PENDING vagy ACTIVE, nem lejárt)
$hasExistingBonus = false;
if ($userId > 0) {
    $existingBonusStmt = $conn->prepare("
        SELECT 1 FROM UserBonuses
        WHERE user_id = ?
          AND status IN ('PENDING', 'ACTIVE')
          AND used = 0
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $existingBonusStmt->bind_param("i", $userId);
    $existingBonusStmt->execute();
    $hasExistingBonus = $existingBonusStmt->get_result()->num_rows > 0;
    $existingBonusStmt->close();
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!$isGuest) {
            // Hétköznapi bónusz láthatóság: hétköznap + daily_start_time után, VAGY admin_force_active
            if ((int)$row['valid_weekdays_only'] === 1 && empty($row['admin_force_active'])) {
                $dailyStart = $row['daily_start_time'] ?? null;
                $isAfterDailyStart = ($dailyStart === null || date('H:i:s') >= $dailyStart);
                $isWeekdayWindow = ($isWeekday && $isAfterDailyStart);
                if (!$isWeekdayWindow) {
                    continue;
                }
            }

            // Hétköznapi napi bónusz ne jelenjen meg, ha ma már igényelték
            if ($userId > 0 && (int)$row['valid_weekdays_only'] === 1) {
                $ds = $row['daily_start_time'] ?? '00:01:00';
                $bonusTodayFrom = date('Y-m-d') . ' ' . $ds;
                $bonusTomorrowFrom = date('Y-m-d', strtotime('+1 day')) . ' ' . $ds;
                $claimedTodayStmt = $conn->prepare(" 
                    SELECT id
                    FROM UserBonuses
                    WHERE user_id = ?
                      AND bonus_id = ?
                      AND created_at >= ?
                      AND created_at < ?
                    LIMIT 1
                ");
                $claimedTodayStmt->bind_param("iiss", $userId, $row['id'], $bonusTodayFrom, $bonusTomorrowFrom);
                $claimedTodayStmt->execute();
                $claimedTodayRes = $claimedTodayStmt->get_result();
                $alreadyClaimedToday = $claimedTodayRes->num_rows > 0;
                $claimedTodayStmt->close();

                if ($alreadyClaimedToday) {
                    continue;
                }
            }
        }

        $isStepBonus = ((int)($row['is_step_bonus'] ?? 0) === 1);
        $matchPercent = (float)($row['match_percent'] ?? 0);
        $maxBonusAmount = (float)($row['max_bonus_amount'] ?? 0);
        $bonusAmount = (float)($row['bonus_amount'] ?? 0);
        $minDeposit = (float)($row['min_deposit'] ?? 0);

        $amountText = 'Bónusz ajánlat';
        if ($matchPercent > 0 && $maxBonusAmount > 0) {
            $amountText = number_format($matchPercent, 0, '', ' ') . '% max ' . number_format($maxBonusAmount, 0, '', ' ') . ' FT';
        } elseif ($bonusAmount > 0) {
            $amountText = number_format($bonusAmount, 0, '', ' ') . ' FT';
        }

        $conditionText = 'Min. befizetés: ' . number_format($minDeposit, 0, '', ' ') . ' FT';
        if ($isStepBonus) {
            $conditionText .= ' | Több lépcsős bónusz';
        }

        $bonuses[] = [
            'id' => $row['id'],
            'code' => $row['code'],
            'title' => $row['name'],
            'amount' => $amountText,
            'condition' => $conditionText,
            'isStepBonus' => $isStepBonus,
            'status' => $isGuest ? null : 'AKTÍV',
            'longDescription' => $row['description'],
            'image' => '../../img/logo.png',
            'hasExistingBonus' => $hasExistingBonus
        ];
    }
}

echo json_encode($bonuses);