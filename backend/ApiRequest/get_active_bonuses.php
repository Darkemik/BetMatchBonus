<?php
session_start();
require_once dirname(__DIR__) . '/connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$isWeekday = ((int)date('N') <= 5);
$isAfterDailyRefresh = (date('H:i') >= '00:01');
$isWeekdayWindow = ($isWeekday && $isAfterDailyRefresh);

// Csak az aktív bónuszokat kérjük le
$query = "SELECT id, code, name, description, bonus_amount, min_deposit, max_bonus_amount, match_percent, is_step_bonus, step_number, parent_bonus_id, bonus_type_id, bet_reward_type, valid_weekdays_only, birthday_bonus 
          FROM BonusCodes 
          WHERE (is_active = 1 OR valid_weekdays_only = 1)
            AND birthday_bonus = 0
          ORDER BY id DESC";

$result = $conn->query($query);
$bonuses = [];

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$todayFrom = date('Y-m-d 00:01:00');
$tomorrowFrom = date('Y-m-d 00:01:00', strtotime('+1 day'));
$isBrandNewUser = false;
$isDartsPrematchWindow = false;

$todayNoon = date('Y-m-d 12:00:00');
$tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
$dayAfterTomorrowStart = date('Y-m-d 00:00:00', strtotime('+2 day'));

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
        $dartsTomorrowRes = $dartsTomorrowStmt->get_result();
        $isDartsPrematchWindow = $dartsTomorrowRes->num_rows > 0;
        $dartsTomorrowStmt->close();
}

if ($userId > 0) {
    $freshStmt = $conn->prepare(" 
        SELECT
            (SELECT COUNT(*) FROM Transactions t WHERE t.user_id = ? AND t.type = 'deposit' AND t.status = 'completed') AS deposits_count,
            (SELECT COUNT(*) FROM Tickets tk WHERE tk.user_id = ?) AS tickets_count
    ");
    $freshStmt->bind_param("ii", $userId, $userId);
    $freshStmt->execute();
    $freshData = $freshStmt->get_result()->fetch_assoc();
    $freshStmt->close();

    $depositsCount = (int)($freshData['deposits_count'] ?? 0);
    $ticketsCount = (int)($freshData['tickets_count'] ?? 0);
    $isBrandNewUser = ($depositsCount === 0 && $ticketsCount === 0);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $isVisible = ((int)$row['valid_weekdays_only'] === 1) ? $isWeekdayWindow : true;
        if (!$isVisible) {
            continue;
        }

        // DARTS bónusz csak akkor látszódjon, ha holnap van darts meccs,
        // és már legalább az előző nap 12:00 van.
        $isDartsBonus = (strtoupper((string)($row['code'] ?? '')) === 'DARTSBONUSZ5K');
        if ($isDartsBonus && !$isDartsPrematchWindow) {
            continue;
        }

        // Üdvözlő 1. lépés kizárólag vadonatúj fiókoknál jelenjen meg.
        $isWelcomeStep1 = ((int)$row['bonus_type_id'] === 1 && (int)$row['is_step_bonus'] === 1 && (int)$row['step_number'] === 1);
        if ($isWelcomeStep1 && $userId > 0) {
            if (!$isBrandNewUser) {
                continue;
            }
        }

        // Többlépcsős bónusznál a következő lépcső csak akkor jelenjen meg,
        // ha az előző lépcső a felhasználónál COMPLETED.
        // Nem bejelentkezett felhasználók minden bónuszt látnak.
        if ((int)($row['is_step_bonus'] ?? 0) === 1 && (int)($row['step_number'] ?? 0) > 1) {
            if ($userId > 0) {

            $currentStep = (int)$row['step_number'];
            $parentBonusId = isset($row['parent_bonus_id']) ? (int)$row['parent_bonus_id'] : 0;
            $previousStep = $currentStep - 1;

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
                $prevBonusStmt->bind_param("iiii", $row['bonus_type_id'], $previousStep, $parentBonusId, $parentBonusId);
            } else {
                $prevBonusStmt = $conn->prepare(" 
                    SELECT id
                    FROM BonusCodes
                    WHERE is_step_bonus = 1
                      AND bonus_type_id = ?
                      AND step_number = ?
                    LIMIT 1
                ");
                $prevBonusStmt->bind_param("ii", $row['bonus_type_id'], $previousStep);
            }
            $prevBonusStmt->execute();
            $prevBonusRes = $prevBonusStmt->get_result();
            $prevBonusRow = $prevBonusRes->fetch_assoc();
            $prevBonusStmt->close();

            if (!$prevBonusRow || empty($prevBonusRow['id'])) {
                continue;
            }

            $prevCompletedStmt = $conn->prepare(" 
                SELECT id
                FROM UserBonuses
                WHERE user_id = ?
                  AND bonus_id = ?
                  AND status = 'COMPLETED'
                LIMIT 1
            ");
            $prevCompletedStmt->bind_param("ii", $userId, $prevBonusRow['id']);
            $prevCompletedStmt->execute();
            $prevCompletedRes = $prevCompletedStmt->get_result();
            $isPrevCompleted = $prevCompletedRes->num_rows > 0;
            $prevCompletedStmt->close();

            if (!$isPrevCompleted) {
                continue;
            }
            }
        }

        // Üdvözlő 2. lépcső csak egyszer jelenjen meg felhasználónként.
        // Ha a user egyszer már aktiválta, a bónusz oldalon többé ne látszódjon.
        $isWelcomeStep2 = ((int)$row['bonus_type_id'] === 1
            && (int)$row['is_step_bonus'] === 1
            && (int)$row['step_number'] === 2);
        if ($isWelcomeStep2 && $userId > 0) {
            $claimedWelcomeStep2Stmt = $conn->prepare(" 
                SELECT id
                FROM UserBonuses
                WHERE user_id = ?
                  AND bonus_id = ?
                LIMIT 1
            ");
            $claimedWelcomeStep2Stmt->bind_param("ii", $userId, $row['id']);
            $claimedWelcomeStep2Stmt->execute();
            $claimedWelcomeStep2Res = $claimedWelcomeStep2Stmt->get_result();
            $hasClaimedWelcomeStep2 = $claimedWelcomeStep2Res->num_rows > 0;
            $claimedWelcomeStep2Stmt->close();

            if ($hasClaimedWelcomeStep2) {
                continue;
            }
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