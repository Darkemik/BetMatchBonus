<?php
/**
 * CHECK_BETS.PHP - Nyitott szelvenyek kiertkelese az adatbazisbol
 * 
 * JAVITAS: A meccseket CSAK akkor ertekeli ki, ha TENYLEG VEGET ERTEK:
 * 1) status_id = 3 (FINISHED) az adatbazisban
 * 2) liveStatus szoveg kifejezetten "ended/finished/ft" stb.
 * 3) Idobased fallback: 4+ ora eltelt ES nem elo ES van eredmeny
 * 
 * NEM ertekeli ki, ha:
 * - A meccs meg elo (is_live = 1)
 * - A meccs meg folyamatban van (van eredmeny de nincs "ended" status)
 * - A meccs meg nem kezdodott el
 */

if (!isset($conn)) {
    require_once dirname(__DIR__) . "/connect.php";
}
require_once dirname(__DIR__) . "/config.php";

/**
 * Nyitott ticketek kiertkelese a bejelentkezett felhasznalonel
 */
function evaluateOpenTickets($conn, $userId) {
    // 1. Nyitott ticketek lekerdese
    $stmtTickets = $conn->prepare("
        SELECT id FROM Tickets 
        WHERE user_id = ? AND status = 'OPEN'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmtTickets->bind_param("i", $userId);
    $stmtTickets->execute();
    $ticketsResult = $stmtTickets->get_result();

    $ticketIds = [];
    while ($row = $ticketsResult->fetch_assoc()) {
        $ticketIds[] = (int)$row['id'];
    }
    $stmtTickets->close();

    if (empty($ticketIds)) return;

    // 2. Minden nyitott ticket OPEN selectionjeit kiertekeljuk
    foreach ($ticketIds as $ticketId) {
        $stmtSel = $conn->prepare("
            SELECT id, match_id, event_id, home_team, away_team, pick_label, market_name, status
            FROM TicketSelections
            WHERE ticket_id = ? AND status = 'OPEN'
        ");
        $stmtSel->bind_param("i", $ticketId);
        $stmtSel->execute();
        $selResult = $stmtSel->get_result();

        $selectionsToCheck = [];
        while ($sel = $selResult->fetch_assoc()) {
            $selectionsToCheck[] = $sel;
        }
        $stmtSel->close();

        if (empty($selectionsToCheck)) continue;

        // 3. Minden OPEN selection-hoz lekerjuk a meccs adatokat
        foreach ($selectionsToCheck as $sel) {
            $matchId = (int)$sel['match_id'];
            $eventId = $sel['event_id'] ? (int)$sel['event_id'] : null;
            if ($matchId <= 0 && !$eventId) continue;

            // === LEPES 1: Adatbazisbol probaljuk kiolvasni ===
            $matchData = fetchMatchDataFromDB($conn, $matchId, $eventId);
            
            // === LEPES 2: Ha az adatbazisban nincs eleg adat -> API ===
            if (!$matchData || !isMatchFinished($matchData)) {
                $apiData = fetchMatchDataFromAPI($matchId);
                if ($apiData && isset($apiData['id']) && $apiData['id'] > 0) {
                    // API adat kiegeszitese DB adatokkal (status_id)
                    $dbEvent = getEventFromDB($conn, $matchId, $eventId);
                    if ($dbEvent) {
                        $apiData['statusId'] = (int)($dbEvent['status_id'] ?? 0);
                        $apiData['startTime'] = $dbEvent['start_time'];
                        $dtChk = new DateTime($dbEvent['start_time'], new DateTimeZone('UTC'));
                        $apiData['isStarted'] = ($dtChk->getTimestamp() <= time());
                    }
                    $matchData = $apiData;
                }
            }

            // === LEPES 3: Idoalapu fallback - CSAK 4+ ora utan ===
            if (!$matchData || !isMatchFinished($matchData)) {
                $dbEvent = getEventFromDB($conn, $matchId, $eventId);
                if ($dbEvent) {
                    $dtFb = new DateTime($dbEvent['start_time'], new DateTimeZone('UTC'));
                    $startTime = $dtFb->getTimestamp();
                    $now = time();
                    $isLive = (int)($dbEvent['is_live'] ?? 0);
                    $hoursElapsed = ($now - $startTime) / 3600;
                    
                    // CSAK 4+ ora eltelt ES nem elo -> befejezett
                    if ($hoursElapsed >= 4 && !$isLive && $startTime > 0) {
                        if ($dbEvent['home_score'] !== null && $dbEvent['away_score'] !== null) {
                            $matchData = [
                                'isLive' => false,
                                'liveStatus' => 'ended',
                                'score' => [(int)$dbEvent['home_score'], (int)$dbEvent['away_score']],
                                'homeTeam' => $dbEvent['home_team_name'],
                                'awayTeam' => $dbEvent['away_team_name'],
                                'isStarted' => true,
                                'statusId' => 3,
                                '_source' => 'time_fallback_with_score'
                            ];
                        } else {
                            // Nincs score -> 0-0 fallbackkent
                            $matchData = [
                                'isLive' => false,
                                'liveStatus' => 'ended',
                                'score' => [0, 0],
                                'homeTeam' => $dbEvent['home_team_name'],
                                'awayTeam' => $dbEvent['away_team_name'],
                                'isStarted' => true,
                                'statusId' => 3,
                                '_source' => 'time_fallback_no_score'
                            ];
                        }
                        
                        // Frissitsuk az Events tabla statuszat
                        $stmtFixStatus = $conn->prepare("UPDATE Events SET is_live = 0, live_status = 'Ended', status_id = 3 WHERE api_id = ?");
                        $stmtFixStatus->bind_param("i", $matchId);
                        $stmtFixStatus->execute();
                        $stmtFixStatus->close();
                    }
                }
            }

            if (!$matchData) continue;

            $isFinished = isMatchFinished($matchData);
            if (!$isFinished) continue;

            // Meccs veget ert - eredmeny kiolvasasa
            $score = $matchData['score'] ?? [];
            $homeScore = 0;
            $awayScore = 0;
            
            if (is_array($score) && count($score) >= 2) {
                $homeScore = (int)$score[0];
                $awayScore = (int)$score[1];
            }

            $homeTeam = $sel['home_team'] ?: ($matchData['homeTeam'] ?? '');
            $awayTeam = $sel['away_team'] ?: ($matchData['awayTeam'] ?? '');
            $pick = $sel['pick_label'] ?? '';
            $market = $sel['market_name'] ?? '';

            $won = checkIfPickWon($pick, $market, $homeScore, $awayScore, $homeTeam, $awayTeam);
            $newStatus = $won ? 'WON' : 'LOST';

            // Selection statusz frissitese
            $stmtUpdate = $conn->prepare("UPDATE TicketSelections SET status = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $newStatus, $sel['id']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        // 4. Ticket osszesitett statusz ujraszamolasa
        updateTicketStatus($conn, $ticketId, $userId);
    }
}

/**
 * Meccs adatok lekerdese az ADATBAZIS Events tablajabol
 */
function fetchMatchDataFromDB($conn, $matchApiId, $eventId = null) {
    $event = getEventFromDB($conn, $matchApiId, $eventId);
    if (!$event) return null;

    $isLive = (int)($event['is_live'] ?? 0);
    $liveStatus = $event['live_status'] ?? '';
    $homeScore = $event['home_score'];
    $awayScore = $event['away_score'];
    $statusId = (int)($event['status_id'] ?? 0);

    return [
        'id' => (int)$event['api_id'],
        'homeTeam' => $event['home_team_name'],
        'awayTeam' => $event['away_team_name'],
        'isLive' => (bool)$isLive,
        'liveStatus' => $liveStatus,
        'liveTime' => $event['live_time'] ?? '',
        'score' => ($homeScore !== null && $awayScore !== null) ? [(int)$homeScore, (int)$awayScore] : [],
        'isStarted' => ((new DateTime($event['start_time'], new DateTimeZone('UTC')))->getTimestamp() <= time()),
        'startTime' => $event['start_time'],
        'statusId' => $statusId,
        '_source' => 'database'
    ];
}

/**
 * Nyers Events sor lekerdese az adatbazisbol
 */
function getEventFromDB($conn, $matchApiId, $eventId = null) {
    if ($eventId) {
        $stmt = $conn->prepare("SELECT * FROM Events WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $eventId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Events WHERE api_id = ? LIMIT 1");
        $stmt->bind_param("i", $matchApiId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    return $event;
}

/**
 * AZ OSSZES USER OSSZES NYITOTT TICKETJET kiertekeli.
 * Cron jobbol vagy admin dashboardrol hivhato.
 */
function evaluateAllOpenTickets($conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT t.user_id
        FROM Tickets t
        WHERE t.status = 'OPEN'
        ORDER BY t.user_id
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $evaluatedUsers = 0;
    while ($row = $result->fetch_assoc()) {
        $uid = (int)$row['user_id'];
        evaluateOpenTickets($conn, $uid);
        $evaluatedUsers++;
    }
    $stmt->close();

    return $evaluatedUsers;
}


/**
 * Ticket statusz frissitese a selectionjei alapjan
 */
function updateTicketStatus($conn, $ticketId, $userId) {
    $stmtAll = $conn->prepare("SELECT status FROM TicketSelections WHERE ticket_id = ?");
    $stmtAll->bind_param("i", $ticketId);
    $stmtAll->execute();
    $result = $stmtAll->get_result();

    $allFinished = true;
    $anyLost = false;
    $count = 0;

    while ($row = $result->fetch_assoc()) {
        $count++;
        if ($row['status'] === 'OPEN') {
            $allFinished = false;
        } elseif ($row['status'] === 'LOST') {
            $anyLost = true;
        }
    }
    $stmtAll->close();

    if ($count === 0) return;

    if ($anyLost) {
        $newStatus = 'LOST';
    } elseif ($allFinished) {
        $newStatus = 'WON';
    } else {
        $newStatus = 'OPEN';
    }

    // Csak akkor frissitunk ha valtozott
    $stmtCheck = $conn->prepare("SELECT status, stake, potential_win, bonus_stake, user_bonus_id FROM Tickets WHERE id = ?");
    $stmtCheck->bind_param("i", $ticketId);
    $stmtCheck->execute();
    $ticketRow = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$ticketRow || $ticketRow['status'] === $newStatus) return;

    // Ne írjuk felül a CASHOUT státuszt
    if ($ticketRow['status'] === 'CASHOUT') return;

    $stmtUpd = $conn->prepare("UPDATE Tickets SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpd->bind_param("si", $newStatus, $ticketId);
    $stmtUpd->execute();
    $stmtUpd->close();

    // evaluate_on_settle: BET-triggeres bónuszok aktiválása, amik erre a ticketre vártak
    if ($newStatus === 'WON' || $newStatus === 'LOST') {
        $evalSettleStmt = $conn->prepare("
            SELECT ub.id AS user_bonus_id, bc.bonus_amount, bc.wagering_multiplier,
                   bc.activation_expire_hours, bc.bet_reward_type
            FROM UserBonuses ub
            INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
            WHERE ub.ticket_id = ?
              AND ub.user_id = ?
              AND ub.status = 'PENDING'
              AND bc.evaluate_on_settle = 1
              AND bc.bonus_trigger = 'BET'
        ");
        $evalSettleStmt->bind_param("ii", $ticketId, $userId);
        $evalSettleStmt->execute();
        $evalSettleBonuses = $evalSettleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $evalSettleStmt->close();

        foreach ($evalSettleBonuses as $esBonus) {
            $esGranted = (float)($esBonus['bonus_amount'] ?? 0);
            if ($esGranted <= 0) continue;

            $isFreeBetReward = (strtoupper((string)($esBonus['bet_reward_type'] ?? '')) === 'FREE_BET');
            $esBonusMoney = $isFreeBetReward ? 0.00 : $esGranted;
            $esFreeBet = $isFreeBetReward ? $esGranted : 0.00;
            $esIndividualBal = $isFreeBetReward ? 0.00 : $esGranted;

            $esWageringReq = 0.00;
            if (!empty($esBonus['wagering_multiplier']) && (float)$esBonus['wagering_multiplier'] > 0) {
                $esWageringReq = $esGranted * (float)$esBonus['wagering_multiplier'];
            }

            $esExpiresAt = null;
            if (!empty($esBonus['activation_expire_hours']) && (int)$esBonus['activation_expire_hours'] > 0) {
                $esExpiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$esBonus['activation_expire_hours'] . ' hours'));
            }

            $activateEsStmt = $conn->prepare("
                UPDATE UserBonuses
                SET status = 'ACTIVE',
                    granted_amount = ?,
                    bonus_money_amount = ?,
                    free_bet_amount = ?,
                    bonus_balance = ?,
                    wagering_required = ?,
                    expires_at = ?
                WHERE id = ?
            ");
            $activateEsStmt->bind_param("dddddsi", $esGranted, $esBonusMoney, $esFreeBet, $esIndividualBal, $esWageringReq, $esExpiresAt, $esBonus['user_bonus_id']);
            $activateEsStmt->execute();
            $activateEsStmt->close();

            // Users bonus_balance frissítése
            if (!$isFreeBetReward) {
                $esBonusBalStmt = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
                $esBonusBalStmt->bind_param("di", $esIndividualBal, $userId);
                $esBonusBalStmt->execute();
                $esBonusBalStmt->close();
            }
        }
    }

    // Ha NYERTES -> nyeremeny jovairasa
    if ($newStatus === 'WON') {
        $potentialWin = (float)$ticketRow['potential_win'];
        $bonusStake = (float)($ticketRow['bonus_stake'] ?? 0);
        $isBonusTicket = ($bonusStake > 0);
        $ticketUserBonusId = !empty($ticketRow['user_bonus_id']) ? (int)$ticketRow['user_bonus_id'] : 0;

        // Bónusz szelvénynél: max nyeremény cap (max_win_multiplier × granted_amount)
        if ($isBonusTicket && $ticketUserBonusId > 0) {
            $capStmt = $conn->prepare("
                SELECT ub.granted_amount, bc.max_win_multiplier
                FROM UserBonuses ub
                INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
                WHERE ub.id = ?
                  AND ub.user_id = ?
                  AND COALESCE(ub.granted_amount, 0) > 0
                LIMIT 1
            ");
            $capStmt->bind_param("ii", $ticketUserBonusId, $userId);
            $capStmt->execute();
            $capRow = $capStmt->get_result()->fetch_assoc();
            $capStmt->close();

            if ($capRow) {
                $maxWinMultiplier = (float)($capRow['max_win_multiplier'] ?? 5.0);
                $grantedAmount = (float)$capRow['granted_amount'];
                $maxWin = $grantedAmount * $maxWinMultiplier;
                if ($potentialWin > $maxWin) {
                    $potentialWin = $maxWin;
                }
            }

            // Ellenőrizzük, hogy a forgatás teljesült-e EZEN bónusznál
            $wagerDoneStmt = $conn->prepare("
                SELECT ub.wagering_required, ub.wagering_progress, ub.status AS ub_status, ub.used,
                       COALESCE(bc.bet_reward_type, '') AS bet_reward_type
                FROM UserBonuses ub
                INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
                WHERE ub.id = ?
                  AND ub.user_id = ?
                LIMIT 1
            ");
            $wagerDoneStmt->bind_param("ii", $ticketUserBonusId, $userId);
            $wagerDoneStmt->execute();
            $wagerRow = $wagerDoneStmt->get_result()->fetch_assoc();
            $wagerDoneStmt->close();

            $isFreeBetType = (strtoupper($wagerRow['bet_reward_type'] ?? '') === 'FREE_BET');
            $wagerReq = (float)($wagerRow['wagering_required'] ?? 0);
            $wagerProg = (float)($wagerRow['wagering_progress'] ?? 0);
            $wageringCompleted = $isFreeBetType || ($wagerReq <= 0) || ($wagerProg >= $wagerReq);

            if ($wageringCompleted) {
                // Forgatás teljesítve → nyeremény a rendes egyenlegbe
                $stmtCredit = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
                $stmtCredit->bind_param("ddi", $potentialWin, $potentialWin, $userId);
                $stmtCredit->execute();
                $stmtCredit->close();
            } else {
                // Forgatás NINCS kész → nyeremény vissza az adott bónusz egyenlegébe
                $stmtCreditBonus = $conn->prepare("UPDATE UserBonuses SET bonus_balance = bonus_balance + ? WHERE id = ? AND user_id = ?");
                $stmtCreditBonus->bind_param("dii", $potentialWin, $ticketUserBonusId, $userId);
                $stmtCreditBonus->execute();
                $stmtCreditBonus->close();

                $stmtCreditUserBonus = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
                $stmtCreditUserBonus->bind_param("di", $potentialWin, $userId);
                $stmtCreditUserBonus->execute();
                $stmtCreditUserBonus->close();
            }
        } elseif ($isBonusTicket) {
            // Fallback: régi típusú bónusz ticket user_bonus_id nélkül
            $stmtCredit = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
            $stmtCredit->bind_param("di", $potentialWin, $userId);
            $stmtCredit->execute();
            $stmtCredit->close();
        } else {
            // Nem bónusz szelvény → normál kifizetés (balance + winnings_balance)
            $stmtUserBal = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
            $stmtUserBal->bind_param("ddi", $potentialWin, $potentialWin, $userId);
            $stmtUserBal->execute();
            $stmtUserBal->close();
        }

        // Wallet tranzakcio rogzitese naplozas celjabol (type_id = 4 = WIN)
        $stmtTx = $conn->prepare("
            INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
            SELECT id, ?, 4, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
        ");
        $stmtTx->bind_param("dii", $potentialWin, $ticketId, $userId);
        $stmtTx->execute();
        $stmtTx->close();

        // BalanceHistory bejegyzés
        if (!$isBonusTicket || $wageringCompleted) {
            require_once __DIR__ . '/../Auth/audit_helper.php';
            $bhBal = $conn->query("SELECT balance FROM Users WHERE id = $userId")->fetch_assoc();
            $bhNew = (float)($bhBal['balance'] ?? 0);
            log_balance_change($userId, $bhNew - $potentialWin, $bhNew, $potentialWin, 'Nyeremény: #' . $ticketId . ' (' . number_format($potentialWin, 0, ',', ' ') . ' Ft)');
        }
    }

    // LOSS trigger: vesztes fogadásnál cashback free bet bónusz
    if ($newStatus === 'LOST') {
        $lossStake = (float)$ticketRow['stake'];
        $lossBonusStake = (float)($ticketRow['bonus_stake'] ?? 0);

        // Csak rendes tétes szelvényeknél (bónusz szelvénynél NEM jár cashback)
        if ($lossBonusStake <= 0 && $lossStake > 0) {
            // Ticket odds lekérdezése
            $lossOddsStmt = $conn->prepare("SELECT total_odds FROM Tickets WHERE id = ? LIMIT 1");
            $lossOddsStmt->bind_param("i", $ticketId);
            $lossOddsStmt->execute();
            $lossOddsRow = $lossOddsStmt->get_result()->fetch_assoc();
            $lossOddsStmt->close();
            $lossTicketOdds = (float)($lossOddsRow['total_odds'] ?? 0);

            // LOSS triggeres bónuszok keresése (a felhasználó "subscription" sorai)
            $lossBonusStmt = $conn->prepare("
                SELECT ub.id AS user_bonus_id, bc.id AS bonus_code_id,
                       bc.match_percent, bc.min_deposit, bc.min_odds,
                       bc.max_bonus_amount, bc.activation_expire_hours
                FROM UserBonuses ub
                INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
                WHERE ub.user_id = ?
                  AND ub.status = 'ACTIVE'
                  AND ub.used = 0
                  AND COALESCE(ub.free_bet_amount, 0) = 0
                  AND COALESCE(ub.bonus_balance, 0) = 0
                  AND bc.bonus_trigger = 'LOSS'
                  AND bc.is_active = 1
                  AND (bc.valid_to IS NULL OR bc.valid_to >= NOW())
                  AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
            ");
            $lossBonusStmt->bind_param("i", $userId);
            $lossBonusStmt->execute();
            $lossBonuses = $lossBonusStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $lossBonusStmt->close();

            foreach ($lossBonuses as $lossBonus) {
                $lbMinStake = (float)($lossBonus['min_deposit'] ?? 0);
                $lbMinOdds = (float)($lossBonus['min_odds'] ?? 0);
                $lbMatchPercent = (float)($lossBonus['match_percent'] ?? 0);
                $lbMaxAmount = (float)($lossBonus['max_bonus_amount'] ?? 0);
                $lbExpireHours = (int)($lossBonus['activation_expire_hours'] ?? 0);

                // Minimum tét ellenőrzése
                if ($lbMinStake > 0 && $lossStake < $lbMinStake) continue;
                // Minimum odds ellenőrzése
                if ($lbMinOdds > 0 && $lossTicketOdds < $lbMinOdds) continue;

                // Napi limit: már kapott-e ma cashback free betet ebből a bónuszból?
                $dailyCheckStmt = $conn->prepare("
                    SELECT COUNT(*) AS cnt FROM UserBonuses
                    WHERE user_id = ? AND bonus_id = ?
                      AND COALESCE(free_bet_amount, 0) > 0
                      AND DATE(created_at) = CURDATE()
                ");
                $dailyCheckStmt->bind_param("ii", $userId, $lossBonus['bonus_code_id']);
                $dailyCheckStmt->execute();
                $dailyCheckRow = $dailyCheckStmt->get_result()->fetch_assoc();
                $dailyCheckStmt->close();

                if ((int)($dailyCheckRow['cnt'] ?? 0) >= 1) continue;

                // Cashback összeg számítása
                $cashbackAmount = round($lossStake * ($lbMatchPercent / 100), 2);
                if ($cashbackAmount <= 0) continue;
                if ($lbMaxAmount > 0 && $cashbackAmount > $lbMaxAmount) {
                    $cashbackAmount = $lbMaxAmount;
                }

                // Lejárati dátum
                $cbExpiresAt = null;
                if ($lbExpireHours > 0) {
                    $cbExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $lbExpireHours . ' hours'));
                }

                // Új UserBonuses sor létrehozása az ingyenes fogadással
                $createFreeBetStmt = $conn->prepare("
                    INSERT INTO UserBonuses (user_id, bonus_id, ticket_id, status, granted_amount,
                        free_bet_amount, bonus_money_amount, bonus_balance, wagering_required, expires_at, created_at)
                    VALUES (?, ?, ?, 'ACTIVE', ?, ?, 0, 0, 0, ?, NOW())
                ");
                $createFreeBetStmt->bind_param("iiidds",
                    $userId, $lossBonus['bonus_code_id'], $ticketId,
                    $cashbackAmount, $cashbackAmount, $cbExpiresAt
                );
                $createFreeBetStmt->execute();
                $createFreeBetStmt->close();

                // Értesítés a felhasználónak
                $notifMsg = 'Vesztes fogadásod után ' . number_format($cashbackAmount, 0, ',', ' ') . ' Ft Free Bet jóváírva!';
                $notifStmt = $conn->prepare("
                    INSERT INTO Notifications (user_id, title, message, type, related_type, related_id, created_at)
                    VALUES (?, 'Free Bet jóváírás', ?, 'bonus', 'Ticket', ?, NOW())
                ");
                $notifStmt->bind_param("isi", $userId, $notifMsg, $ticketId);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
    }
}

/**
 * Meccs adatok lekerdese az API-bol (fallback)
 */
function fetchMatchDataFromAPI($matchId) {
    // Proba 1: /matches/event?eventId=
    $url = rtrim(API_BASE_URL, '/') . "/api/matches/event?eventId=$matchId";
    $data = curlGetJson($url);
    if ($data && isset($data['id']) && $data['id'] > 0) return $data;

    // Proba 2: /matches/{id}
    $url = rtrim(API_BASE_URL, '/') . "/api/matches/$matchId";
    $data = curlGetJson($url);
    if ($data && isset($data['id']) && $data['id'] > 0) return $data;

    return null;
}

function curlGetJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * Ellenorzi, hogy a meccs veget ert-e
 * 
 * FONTOS: Egy meccs CSAK AKKOR tekintheto befejezettnek, ha:
 * 1) A status_id = 3 (FINISHED) az adatbazisban, VAGY
 * 2) A liveStatus szoveg kifejezetten befejezest jelez ("ended", "finished", "ft", stb.), VAGY
 * 3) A liveTime szoveg befejezest jelez, VAGY
 * 4) 4+ ora eltelt a kezdes ota ES nem elo (idoalapu fallback), VAGY
 * 5) A _source "time_fallback" (mar a hivo kod eldontotte)
 * 
 * NEM tekintjuk befejezettnek pustan azert mert van eredmeny es nem elo!
 * Egy 2:0-as allas kozben az API frissites kozott atmenetileg is_live=0 lehet.
 */
function isMatchFinished($matchData) {
    // 1) status_id = 3 (FINISHED) az adatbazisban - legmegbizhatobb
    $statusId = $matchData['statusId'] ?? 0;
    if ($statusId === 3) {
        return true;
    }

    // 2) liveStatus mezo ellenorzese - szoveges befejezesi jelzok
    $liveStatus = $matchData['liveStatus'] ?? $matchData['status'] ?? '';
    $liveStatusLower = strtolower(trim($liveStatus));
    $finishedStatuses = ['ended', 'finished', 'final', 'ft', 'aet', 'ap', 'closed', 
                         'retired', 'walkover', 'cancelled', 'abandoned', 'after penalties',
                         'after extra time', 'full-time', 'result'];
    
    foreach ($finishedStatuses as $fs) {
        if ($liveStatusLower !== '' && strpos($liveStatusLower, $fs) !== false) {
            return true;
        }
    }

    // 3) liveTime tartalmaz befejezest jelzo szoveget
    $liveTime = $matchData['liveTime'] ?? '';
    $liveTimeLower = strtolower(trim($liveTime));
    if ($liveTimeLower !== '' && (
        strpos($liveTimeLower, 'ended') !== false || 
        strpos($liveTimeLower, 'ft') !== false ||
        strpos($liveTimeLower, 'vege') !== false ||
        strpos($liveTimeLower, 'final') !== false ||
        strpos($liveTimeLower, 'finished') !== false)) {
        return true;
    }

    // 4) Idoalapu fallback: 4+ ora eltelt ES nem elo ES mar elkezdodott
    $isLive = !empty($matchData['isLive']);
    $isStarted = !empty($matchData['isStarted']);
    
    if (!$isLive && $isStarted) {
        $startTime = $matchData['startTime'] ?? null;
        if ($startTime) {
            $startTs = strtotime($startTime);
            $hoursElapsed = (time() - $startTs) / 3600;
            if ($hoursElapsed >= 4) {
                return true;
            }
        }
    }

    // 5) _source = time_fallback -> biztosan befejezett (a hivo kod dontotte el)
    if (isset($matchData['_source']) && strpos($matchData['_source'], 'time_fallback') !== false) {
        return true;
    }

    // MINDEN MAS ESETBEN: NEM befejezett
    // Meg ha van is eredmeny (pl. 2:0) es epp nem is_live, a meccs meg folyamatban lehet!
    return false;
}

function normalizeText($text) {
    $text = strtolower(trim((string)$text));
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u'
    ];
    return strtr($text, $map);
}

/**
 * Ellenorzi, hogy egy adott fogadasi pick nyert-e az eredmeny alapjan
 */
function checkIfPickWon($pick, $market, $homeScore, $awayScore, $homeTeam, $awayTeam) {
    $pickLower = normalizeText($pick);
    $marketLower = normalizeText($market);
    $homeTeamLower = normalizeText($homeTeam);
    $awayTeamLower = normalizeText($awayTeam);

    // ===== 1X2 / Match Winner =====
    if (strpos($marketLower, '1x2') !== false || 
        strpos($marketLower, 'winner') !== false || 
        strpos($marketLower, 'gyoztes') !== false ||
        strpos($marketLower, 'match result') !== false ||
        strpos($marketLower, 'full time result') !== false ||
        strpos($marketLower, 'moneyline') !== false) {
        
        if ($pickLower === '1' || $pickLower === 'home' || $pickLower === $homeTeamLower) {
            return $homeScore > $awayScore;
        }
        if ($pickLower === '2' || $pickLower === 'away' || $pickLower === $awayTeamLower) {
            return $awayScore > $homeScore;
        }
        if ($pickLower === 'x' || $pickLower === 'draw' || $pickLower === 'dontetlen') {
            return $homeScore === $awayScore;
        }
    }

    // ===== Over/Under =====
    if (strpos($marketLower, 'over') !== false || strpos($marketLower, 'under') !== false ||
        strpos($marketLower, 'tobb') !== false || strpos($marketLower, 'kevesebb') !== false ||
        strpos($marketLower, 'total') !== false || strpos($marketLower, 'golszam') !== false ||
        strpos($marketLower, 'golok szama') !== false) {
        
        $totalGoals = $homeScore + $awayScore;
        
        // Keressuk a vonalat a piac nevebol: "(2.5)" vagy "(4.5)" stb.
        preg_match('/\((\d+\.?\d*)\)/', $market, $matches);
        $line = isset($matches[1]) ? floatval($matches[1]) : 0;
        
        // Ha a pick tartalmazza a szamot: "5,5 alatt" -> line = 5.5
        if ($line == 0) {
            preg_match('/(\d+[,.]?\d*)/', $pick, $pickMatches);
            if (isset($pickMatches[1])) {
                $line = floatval(str_replace(',', '.', $pickMatches[1]));
            }
        }
        
        if ($line > 0) {
            if (strpos($pickLower, 'over') !== false || strpos($pickLower, 'tobb') !== false || 
                strpos($pickLower, 'folott') !== false || strpos($pickLower, 'felett') !== false) {
                return $totalGoals > $line;
            }
            if (strpos($pickLower, 'under') !== false || strpos($pickLower, 'kevesebb') !== false || 
                strpos($pickLower, 'alatt') !== false) {
                return $totalGoals < $line;
            }
        }
    }

    // ===== Both Teams To Score =====
    if (strpos($marketLower, 'both teams') !== false || strpos($marketLower, 'mindket') !== false ||
        strpos($marketLower, 'btts') !== false) {
        $bothScored = ($homeScore > 0 && $awayScore > 0);
        if ($pickLower === 'yes' || $pickLower === 'igen') return $bothScored;
        if ($pickLower === 'no' || $pickLower === 'nem') return !$bothScored;
    }

    // ===== Double Chance / Ketesely =====
    if (strpos($marketLower, 'double chance') !== false || strpos($marketLower, 'dupla') !== false ||
        strpos($marketLower, 'ketesely') !== false) {
        if ($pickLower === '1x' || $pickLower === 'home or draw') return $homeScore >= $awayScore;
        if ($pickLower === 'x2' || $pickLower === 'draw or away') return $awayScore >= $homeScore;
        if ($pickLower === '12' || $pickLower === 'home or away') return $homeScore !== $awayScore;
        
        // Magyar szoveges formatum: "Dontetlen vagy [csapat]" / "[csapat] vagy Dontetlen"
        $hasDraw = strpos($pickLower, 'dontetlen') !== false || strpos($pickLower, 'draw') !== false;
        $hasHome = strpos($pickLower, $homeTeamLower) !== false;
        $hasAway = strpos($pickLower, $awayTeamLower) !== false;
        
        if ($hasDraw && $hasHome) return $homeScore >= $awayScore;        // 1X
        if ($hasDraw && $hasAway) return $awayScore >= $homeScore;        // X2
        if ($hasHome && $hasAway) return $homeScore !== $awayScore;       // 12
    }

    // ===== Handicap =====
    if (strpos($marketLower, 'handicap') !== false || strpos($marketLower, 'hendikep') !== false) {
        preg_match('/\(([+-]?\d+\.?\d*)\)/', $market, $matches);
        $handicap = isset($matches[1]) ? floatval($matches[1]) : 0;
        
        if ($pickLower === '1' || $pickLower === $homeTeamLower || strpos($pickLower, 'home') !== false) {
            return ($homeScore + $handicap) > $awayScore;
        }
        if ($pickLower === '2' || $pickLower === $awayTeamLower || strpos($pickLower, 'away') !== false) {
            return $awayScore > ($homeScore + $handicap);
        }
    }

    // ===== Odd/Even =====
    if (strpos($marketLower, 'odd') !== false || strpos($marketLower, 'even') !== false ||
        strpos($marketLower, 'paros') !== false || strpos($marketLower, 'paratlan') !== false) {
        $total = $homeScore + $awayScore;
        if ($pickLower === 'odd' || $pickLower === 'paratlan') return ($total % 2) !== 0;
        if ($pickLower === 'even' || $pickLower === 'paros') return ($total % 2) === 0;
    }

    // ===== Csapatnev alapu match (1X2 piacon kivul is) =====
    if ($pickLower === $homeTeamLower && !empty($homeTeamLower)) {
        return $homeScore > $awayScore;
    }
    if ($pickLower === $awayTeamLower && !empty($awayTeamLower)) {
        return $awayScore > $homeScore;
    }

    // Ha nem tudtuk megallapitani -> vesztes
    return false;
}

// Ha kozvetlenul hivjak (nem include), futtassuk a bejelentkezett user ticketjeit
// VAGY ?action=evaluate_all -> az OSSZES user OSSZES nyitott ticketjet kiertekeli (cron/admin)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'evaluate_all') {
        // Cron job / admin: osszes user kiertkelese
        header('Content-Type: application/json; charset=utf-8');
        $startTime = microtime(true);
        $evaluatedUsers = evaluateAllOpenTickets($conn);
        $elapsed = round((microtime(true) - $startTime) * 1000);
        echo json_encode([
            'status' => 'ok',
            'message' => "Kiertekeles kesz: $evaluatedUsers user szelvenyei ellenorizve.",
            'evaluated_users' => $evaluatedUsers,
            'elapsed_ms' => $elapsed,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Bejelentkezett user ticketjei
        session_start();
        header('Content-Type: application/json; charset=utf-8');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Nem vagy bejelentkezve']);
            exit;
        }
        
        evaluateOpenTickets($conn, (int)$_SESSION['user_id']);
        echo json_encode(['status' => 'ok', 'message' => 'Kiertekeles kesz']);
    }
}
