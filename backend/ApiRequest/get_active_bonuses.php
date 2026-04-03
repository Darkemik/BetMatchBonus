<?php
session_start();
require_once dirname(__DIR__) . '/connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$isWeekday = ((int)date('N') <= 5);
$isAfterDailyRefresh = (date('H:i') >= '00:01');
$isWeekdayWindow = ($isWeekday && $isAfterDailyRefresh);

// Csak az aktív bónuszokat kérjük le
$query = "SELECT id, code, name, description, bonus_amount, min_deposit, max_bonus_amount, match_percent, is_step_bonus, bet_reward_type, valid_weekdays_only 
          FROM BonusCodes 
          WHERE is_active = 1 OR valid_weekdays_only = 1
          ORDER BY id DESC";

$result = $conn->query($query);
$bonuses = [];

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$todayFrom = date('Y-m-d 00:01:00');
$tomorrowFrom = date('Y-m-d 00:01:00', strtotime('+1 day'));

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $isVisible = ((int)$row['valid_weekdays_only'] === 1) ? $isWeekdayWindow : true;
        if (!$isVisible) {
            continue;
        }

        // Jogosultság: hétköznapi napi bónusz ne jelenjen meg annak, aki ma már aktiválta.
        if ($userId > 0 && (int)$row['valid_weekdays_only'] === 1) {
                        $claimedTodayStmt = $conn->prepare(" 
                                SELECT id
                                FROM UserBonuses
                                WHERE user_id = ?
                                    AND bonus_id = ?
                                    AND created_at >= ?
                                    AND created_at < ?
                                LIMIT 1
                        ");
            $claimedTodayStmt->bind_param("iiss", $userId, $row['id'], $todayFrom, $tomorrowFrom);
            $claimedTodayStmt->execute();
            $claimedTodayRes = $claimedTodayStmt->get_result();
            $alreadyClaimedToday = $claimedTodayRes->num_rows > 0;
            $claimedTodayStmt->close();

            if ($alreadyClaimedToday) {
                continue;
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

        // Formázzuk a kiírást a frontend számára
        $bonuses[] = [
            'id' => $row['id'],
            'code' => $row['code'], // Ha null, akkor backendben lekezeljük
            'title' => $row['name'],
            'amount' => $amountText,
            'condition' => $conditionText,
            'isStepBonus' => $isStepBonus,
            'status' => 'AKTÍV',
            'longDescription' => $row['description'],
            // Ide jöhet valami generikus kép, vagy bevezethetünk egy 'image_url' oszlopot később. Most fix képet adok:
            'image' => '../../img/logo.png' 
        ];
    }
}

echo json_encode($bonuses);