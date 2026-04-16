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
$query = "SELECT id, code, name, description, image_url, bonus_amount, min_deposit, max_bonus_amount, match_percent, 
                 is_step_bonus, step_number, bonus_type_id, valid_weekdays_only, is_active,
                 daily_start_time, admin_force_active, sport_restriction, bonus_trigger
          FROM BonusCodes 
          WHERE is_active = 1
            AND birthday_bonus = 0
          ORDER BY id ASC";

$result = $conn->query($query);
$bonuses = [];

// Cache: live sportok ellenőrzése (sport_restriction-ös bónuszokhoz)
$liveSportsCache = null;
function hasLiveSport($conn, $sportName, &$cache) {
    if ($cache === null) {
        $cache = [];
        $r = $conn->query("
            SELECT UPPER(s.name) AS sport_name, COUNT(*) AS cnt
            FROM Events e
            JOIN Sports s ON e.sport_id = s.id
            WHERE e.is_live = 1
            GROUP BY s.id
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $cache[$row['sport_name']] = (int)$row['cnt'];
            }
        }
    }
    return ($cache[strtoupper($sportName)] ?? 0) > 0;
}

// Többszörös bónusz rendszer: nincs egyszerre-egy-bónusz korlátozás.
// Minden bónusznak saját egyenlege van (UserBonuses.bonus_balance).
$hasExistingBonus = false; // Kompatibilitás megtartása a frontend felé

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

        // Sport-specifikus bónusz: csak akkor jelenik meg, ha van élő meccs az adott sportból
        $sportRestriction = $row['sport_restriction'] ?? null;
        if ($sportRestriction && $sportRestriction !== 'ANY') {
            if (!hasLiveSport($conn, $sportRestriction, $liveSportsCache)) {
                continue; // Nincs élő meccs ebből a sportból → ne jelenjen meg
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

        $conditionText = '';
        $bonusTrigger = $row['bonus_trigger'] ?? 'DEPOSIT';
        if ($bonusTrigger === 'BET') {
            $conditionText = 'Min. tét: ' . number_format($minDeposit, 0, '', ' ') . ' FT';
        } else {
            $conditionText = 'Min. befizetés: ' . number_format($minDeposit, 0, '', ' ') . ' FT';
        }
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
            'image' => !empty($row['image_url']) ? $row['image_url'] : '../../img/logo.png',
            'hasExistingBonus' => $hasExistingBonus,
            'sportRestriction' => ($sportRestriction && $sportRestriction !== 'ANY') ? $sportRestriction : null,
            'bonusTrigger' => $bonusTrigger
        ];
    }
}

echo json_encode($bonuses);