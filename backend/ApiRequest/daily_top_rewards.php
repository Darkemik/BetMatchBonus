<?php
/**
 * DAILY_TOP_REWARDS.PHP — Napi Top jutalom: Free Bet a legjobb felhasználóknak
 * 
 * Top 1 befizető, Top 1 fogadó, Top 1 nyertes → 1000 Ft Free Bet + értesítés
 * Naponta egyszer fut (refresh_all.php-ból hívva), duplikáció-védelem beépítve.
 */

if (!isset($conn)) {
    require_once dirname(__DIR__) . '/connect.php';
}

function awardDailyTopRewards(mysqli $conn): array {
    $bpTz = new DateTimeZone('Europe/Budapest');
    $utcTz = new DateTimeZone('UTC');
    $dayStartBp = new DateTime('today 00:00:00', $bpTz);
    $dayEndBp = new DateTime('today 23:59:59', $bpTz);
    $dayStartBp->setTimezone($utcTz);
    $dayEndBp->setTimezone($utcTz);
    $fromUtc = $dayStartBp->format('Y-m-d H:i:s');
    $toUtc = $dayEndBp->format('Y-m-d H:i:s');

    $freeBetAmount = 1000;
    $expireHours = 48;
    $awarded = [];

    // Duplikáció-védelem: ma már futott-e?
    $dupCheck = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM Notifications
        WHERE type = 'top_reward'
          AND created_at BETWEEN ? AND ?
    ");
    $dupCheck->bind_param("ss", $fromUtc, $toUtc);
    $dupCheck->execute();
    $dupRow = $dupCheck->get_result()->fetch_assoc();
    $dupCheck->close();

    if ((int)($dupRow['cnt'] ?? 0) > 0) {
        return ['skipped' => true, 'message' => 'Ma már kiosztásra kerültek a napi top jutalmak.'];
    }

    $categories = [
        [
            'key' => 'top_depositor',
            'title' => 'Top Befizető',
            'notif_title' => '🏆 Napi Top Befizető!',
            'notif_msg' => 'Gratulálunk! Te vagy a mai nap legnagyobb befizetője! Jutalmad: %d Ft Free Bet. Ha több top kategóriát is megnyersz ma, mindegyikért külön jutalom jár.',
            'sql' => "
                SELECT u.id AS user_id, u.username, SUM(t.amount) AS total_amount
                FROM Transactions t
                JOIN Users u ON t.user_id = u.id
                WHERE t.type = 'deposit'
                  AND t.status = 'completed'
                                    AND t.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_amount DESC
                LIMIT 1
            "
        ],
        [
            'key' => 'top_bettor',
            'title' => 'Top Fogadó',
            'notif_title' => '🏆 Napi Top Fogadó!',
            'notif_msg' => 'Gratulálunk! Te vagy a mai nap legnagyobb fogadója! Jutalmad: %d Ft Free Bet. Ha több top kategóriát is megnyersz ma, mindegyikért külön jutalom jár.',
            'sql' => "
                SELECT u.id AS user_id, u.username, SUM(t2.stake) AS total_amount
                FROM Tickets t2
                JOIN Users u ON t2.user_id = u.id
                WHERE t2.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_amount DESC
                LIMIT 1
            "
        ],
        [
            'key' => 'top_winner',
            'title' => 'Top Nyertes',
            'notif_title' => '🏆 Napi Top Nyertes!',
            'notif_msg' => 'Gratulálunk! Te vagy a mai nap legnagyobb nyertese! Jutalmad: %d Ft Free Bet. Ha több top kategóriát is megnyersz ma, mindegyikért külön jutalom jár.',
            'sql' => "
                SELECT u.id AS user_id, u.username, SUM(t2.potential_win) AS total_amount
                FROM Tickets t2
                JOIN Users u ON t2.user_id = u.id
                WHERE t2.status = 'WON'
                                    AND t2.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_amount DESC
                LIMIT 1
            "
        ]
    ];

    // BonusCodes-ban kell egy TOP_REWARD rekord (vagy létrehozzuk menet közben)
    $bcStmt = $conn->prepare("SELECT id FROM BonusCodes WHERE code = 'TOP_REWARD_DAILY' LIMIT 1");
    $bcStmt->execute();
    $bcRow = $bcStmt->get_result()->fetch_assoc();
    $bcStmt->close();

    if (!$bcRow) {
        $conn->query("
            INSERT INTO BonusCodes (code, name, description, bonus_type_id, bonus_amount,
                bet_reward_type, bonus_trigger, sport_restriction, live_only,
                wagering_multiplier, max_win_multiplier, per_user_limit, is_active)
            VALUES ('TOP_REWARD_DAILY', 'Napi Top Jutalom (1.000 Ft Free Bet)',
                'Automatikus napi jutalom a top befizető, top fogadó és top nyertes számára. Ha valaki több kategóriában is első, kategóriánként külön 1.000 Ft Free Betet kap.',
                7, 1000, 'FREE_BET', 'MANUAL', 'ANY', 0, NULL, NULL, 9999, 1)
        ");
        $bonusCodeId = $conn->insert_id;
    } else {
        $bonusCodeId = (int)$bcRow['id'];

        // Biztosítjuk, hogy a meglévő bonus code leírása is egyértelmű legyen.
        $descUpdate = $conn->prepare(" 
            UPDATE BonusCodes
            SET description = 'Automatikus napi jutalom a top befizető, top fogadó és top nyertes számára. Ha valaki több kategóriában is első, kategóriánként külön 1.000 Ft Free Betet kap.'
            WHERE id = ?
        ");
        if ($descUpdate) {
            $descUpdate->bind_param("i", $bonusCodeId);
            $descUpdate->execute();
            $descUpdate->close();
        }
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expireHours} hours"));

    foreach ($categories as $cat) {
        $stmt = $conn->prepare($cat['sql']);
        $stmt->bind_param("ss", $fromUtc, $toUtc);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (float)($row['total_amount'] ?? 0) <= 0) {
            $awarded[] = ['category' => $cat['key'], 'status' => 'skip', 'reason' => 'Nincs mai adat'];
            continue;
        }

        $userId = (int)$row['user_id'];
        $username = $row['username'];

        // Free Bet létrehozása
        $insertStmt = $conn->prepare("
            INSERT INTO UserBonuses (user_id, bonus_id, status, granted_amount,
                free_bet_amount, bonus_money_amount, bonus_balance,
                wagering_required, expires_at, created_at)
            VALUES (?, ?, 'ACTIVE', ?, ?, 0, 0, 0, ?, NOW())
        ");
        $insertStmt->bind_param("iidds", $userId, $bonusCodeId, $freeBetAmount, $freeBetAmount, $expiresAt);
        $insertStmt->execute();
        $insertStmt->close();

        // Értesítés küldése
        $notifMsg = sprintf($cat['notif_msg'], $freeBetAmount);
        $notifStmt = $conn->prepare("
            INSERT INTO Notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'top_reward', NOW())
        ");
        $notifStmt->bind_param("iss", $userId, $cat['notif_title'], $notifMsg);
        $notifStmt->execute();
        $notifStmt->close();

        $awarded[] = [
            'category' => $cat['key'],
            'status' => 'awarded',
            'user' => $username,
            'amount' => number_format((float)$row['total_amount'], 0, ',', ' ')
        ];
    }

    return ['skipped' => false, 'awarded' => $awarded];
}
