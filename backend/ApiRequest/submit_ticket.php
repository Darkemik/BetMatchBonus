<?php
/**
 * SUBMIT_TICKET.PHP - Ticket mentése az adatbázisba
 * POST JSON: { stake, totalOdds, potentialWin, items: [{homeTeam, awayTeam, pick, odds, market, matchId}] }
 */

session_start();
require_once dirname(__DIR__) . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

// Ellenőrizd, hogy a felhasználó bejelentkezett-e
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Nem vagy bejelentkezve!']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['stake']) || !isset($input['items'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Érvénytelen adatok']);
    exit;
}

$stake = (float)$input['stake'];
$totalOdds = (float)$input['totalOdds'];
$potentialWin = (float)$input['potentialWin'];
$items = $input['items'] ?? [];
$useFreeBet = !empty($input['useFreeBet']);
$freeBetUserBonusId = isset($input['freeBetUserBonusId']) ? (int)$input['freeBetUserBonusId'] : 0;
$selectionCount = count($items);
$ticketMinOdds = null;
$calculatedTotalOdds = 1.0;
$ticketSportIds = [];
$allSelectionsResolved = true;
$hasBonusBalance = false;
$hasWinningsBalance = false;
$deductFromBalance = 0.0;
$deductFromWinnings = 0.0;
$deductFromBonus = 0.0;
$freeBetToConsume = 0.0;
$isFreeBetTicket = false;

if ($stake < 100 || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Minimum tét: 100 Ft, legalább 1 tétel szükséges']);
    exit;
}

foreach ($items as $oddsItem) {
    $itemOdds = isset($oddsItem['odds']) ? (float)$oddsItem['odds'] : 0.0;
    if ($ticketMinOdds === null || $itemOdds < $ticketMinOdds) {
        $ticketMinOdds = $itemOdds;
    }
    if ($itemOdds > 0) {
        $calculatedTotalOdds *= $itemOdds;
    }
}
if ($ticketMinOdds === null) {
    $ticketMinOdds = 0.0;
}

$calculatedTotalOdds = round($calculatedTotalOdds, 2);
$effectiveTotalOdds = max(round($totalOdds, 2), $calculatedTotalOdds);

// Wallet ellenőrzése
// Opcionális oszlopok detektálása (schema kompatibilitás miatt).
$bonusColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'bonus_balance'");
$bonusColStmt->execute();
$bonusColRes = $bonusColStmt->get_result()->fetch_assoc();
$bonusColStmt->close();
if ($bonusColRes && (int)$bonusColRes['cnt'] > 0) {
    $hasBonusBalance = true;
}

$winningsColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'winnings_balance'");
$winningsColStmt->execute();
$winningsColRes = $winningsColStmt->get_result()->fetch_assoc();
$winningsColStmt->close();
if ($winningsColRes && (int)$winningsColRes['cnt'] > 0) {
    $hasWinningsBalance = true;
}

$selectCols = "balance";
if ($hasBonusBalance) {
    $selectCols .= ", bonus_balance";
}
if ($hasWinningsBalance) {
    $selectCols .= ", winnings_balance";
}

$stmtWallet = $conn->prepare("SELECT {$selectCols} FROM Users WHERE id = ?");
$stmtWallet->bind_param("i", $userId);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
$wallet = $walletResult->fetch_assoc();
$stmtWallet->close();

$mainBalance = (float)($wallet['balance'] ?? 0);
$bonusBalance = $hasBonusBalance ? (float)($wallet['bonus_balance'] ?? 0) : 0.0;
$winningsBalance = $hasWinningsBalance ? (float)($wallet['winnings_balance'] ?? 0) : 0.0;
$depositedPart = max(0.0, $mainBalance - max(0.0, $winningsBalance));
$availableForBet = $mainBalance + $bonusBalance;

if ($useFreeBet) {
    if ($freeBetUserBonusId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ingyenes fogadás nincs megfelelően kiválasztva.']);
        exit;
    }

    $freeBetStmt = $conn->prepare(" 
        SELECT ub.id, COALESCE(ub.free_bet_amount, 0) AS free_bet_amount
        FROM UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        WHERE ub.id = ?
          AND ub.user_id = ?
          AND ub.status = 'ACTIVE'
          AND ub.used = 0
          AND UPPER(COALESCE(bc.bet_reward_type, '')) = 'FREE_BET'
          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
        LIMIT 1
    ");
    $freeBetStmt->bind_param("ii", $freeBetUserBonusId, $userId);
    $freeBetStmt->execute();
    $freeBetRes = $freeBetStmt->get_result();
    $freeBetRow = $freeBetRes->fetch_assoc();
    $freeBetStmt->close();

    if (!$freeBetRow) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Az ingyenes fogadás nem elérhető.']);
        exit;
    }

    $freeBetAmount = (float)($freeBetRow['free_bet_amount'] ?? 0.0);
    if ($freeBetAmount <= 0 || $freeBetAmount < $stake) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Az ingyenes fogadás összege nem fedezi a tétet.']);
        exit;
    }

    $isFreeBetTicket = true;
    $freeBetToConsume = $stake;
}

if (!$wallet || (!$isFreeBetTicket && $availableForBet < $stake)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nincs elegendő egyenleg! Kérjük, töltse fel az accountot.']);
    exit;
}

// Levonási prioritás:
// 1) befizetett egyenleg (balance - winnings_balance),
// 2) nyeremény egyenleg (winnings_balance),
// 3) bónusz egyenleg (bonus_balance).
$remainingStake = $isFreeBetTicket ? 0.0 : $stake;

$deductFromDeposited = min($remainingStake, $depositedPart);
$remainingStake -= $deductFromDeposited;

if ($hasWinningsBalance && $remainingStake > 0) {
    $deductFromWinnings = min($remainingStake, max(0.0, $winningsBalance));
    $remainingStake -= $deductFromWinnings;
}

if ($hasBonusBalance && $remainingStake > 0) {
    $deductFromBonus = min($remainingStake, max(0.0, $bonusBalance));
    $remainingStake -= $deductFromBonus;
}

$deductFromBalance = $deductFromDeposited + $deductFromWinnings;

// TRANZAKCIÓ KEZDÉSE
$conn->begin_transaction();

try {
    $dartsSportIds = [];
    $dartsSportStmt = $conn->query("SELECT id FROM Sports WHERE UPPER(name) = 'DARTS' OR api_id = 78");
    if ($dartsSportStmt) {
        while ($dartsSport = $dartsSportStmt->fetch_assoc()) {
            $dartsSportIds[] = (int)$dartsSport['id'];
        }
    }

    // 1. TICKET MENTÉSE
    $stmtTicket = $conn->prepare("
        INSERT INTO Tickets (user_id, stake, total_odds, potential_win, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'OPEN', NOW(), NOW())
    ");
    $stmtTicket->bind_param("iddd", $userId, $stake, $totalOdds, $potentialWin);
    $stmtTicket->execute();
    $ticketId = $stmtTicket->insert_id;
    $stmtTicket->close();

    if ($ticketId <= 0) {
        throw new Exception("Ticket mentés sikertelen");
    }

    // 2. TICKET SELECTIONS MENTÉSE
    foreach ($items as $item) {
        $matchId = (int)$item['matchId'];
        $pick = $item['pick'];
        $odds = (float)$item['odds'];
        $market = $item['market'];
        $homeTeam = $item['homeTeam'] ?? '';
        $awayTeam = $item['awayTeam'] ?? '';

        // Outcome, Event ID és sport keresése az adatbázisból (opcionális - lehet NULL)
        $outcomeId = null;
        $eventId = null;
        $eventSportId = null;

        $stmtEvent = $conn->prepare("SELECT id, sport_id FROM Events WHERE api_id = ? LIMIT 1");
        $stmtEvent->bind_param("i", $matchId);
        $stmtEvent->execute();
        $eventResult = $stmtEvent->get_result();
        $eventRow = $eventResult->fetch_assoc();
        $stmtEvent->close();

        if ($eventRow) {
            $eventId = (int)$eventRow['id'];
            $eventSportId = (int)$eventRow['sport_id'];
            $ticketSportIds[] = $eventSportId;

            $stmtOutcome = $conn->prepare("
                SELECT o.id FROM OddsOutcomes o
                JOIN EventMarkets em ON o.event_market_id = em.id
                WHERE em.event_id = ? AND o.label = ?
                LIMIT 1
            ");
            $stmtOutcome->bind_param("is", $eventId, $pick);
            $stmtOutcome->execute();
            $outcomeResult = $stmtOutcome->get_result();
            $outcomeRow = $outcomeResult->fetch_assoc();
            $stmtOutcome->close();

            if ($outcomeRow) {
                $outcomeId = (int)$outcomeRow['id'];
            }
        } else {
            $allSelectionsResolved = false;
        }

        // MINDIG mentjük a selection-t - outcome_id és event_id lehet NULL
        $stmtSel = $conn->prepare("
            INSERT INTO TicketSelections (ticket_id, outcome_id, event_id, match_id, home_team, away_team, pick_label, market_name, odds_at_pick, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', NOW())
        ");
        $stmtSel->bind_param("iiiissssd",
            $ticketId, $outcomeId, $eventId, $matchId,
            $homeTeam, $awayTeam, $pick, $market, $odds
        );
        $stmtSel->execute();
        $stmtSel->close();
    }

    // 3. WALLET UPDATE - Tét levonása (ha Wallets tábla létezik)
    $stmtCheckWallets = $conn->prepare("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_NAME='Wallets' AND TABLE_SCHEMA=DATABASE()");
    $stmtCheckWallets->execute();
    $walletCheck = $stmtCheckWallets->get_result()->fetch_assoc();
    $stmtCheckWallets->close();
    
    if ($walletCheck['cnt'] > 0) {
        if ($deductFromBalance > 0) {
            $stmtUpdateWallet = $conn->prepare(" 
                UPDATE Wallets SET balance = balance - ? WHERE user_id = ?
            ");
            $stmtUpdateWallet->bind_param("di", $deductFromBalance, $userId);
            $stmtUpdateWallet->execute();
            $stmtUpdateWallet->close();
        }

        // 4. WALLET TRANSACTION RÖGZÍTÉSE
        $stmtTx = $conn->prepare("
            INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
            SELECT id, ?, 3, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
        ");
        $stmtTx->bind_param("dii", $stake, $ticketId, $userId);
        $stmtTx->execute();
        $stmtTx->close();
    }
    
    // 5. USERS BALANCE UPDATE - UserProfile rendszerhez
    if ($deductFromBalance > 0 || $deductFromWinnings > 0 || $deductFromBonus > 0) {
        if ($hasWinningsBalance && $hasBonusBalance) {
            $stmtUpdateUserBalance = $conn->prepare("UPDATE Users SET balance = balance - ?, winnings_balance = winnings_balance - ?, bonus_balance = bonus_balance - ? WHERE id = ?");
            $stmtUpdateUserBalance->bind_param("dddi", $deductFromBalance, $deductFromWinnings, $deductFromBonus, $userId);
        } elseif ($hasWinningsBalance) {
            $stmtUpdateUserBalance = $conn->prepare("UPDATE Users SET balance = balance - ?, winnings_balance = winnings_balance - ? WHERE id = ?");
            $stmtUpdateUserBalance->bind_param("ddi", $deductFromBalance, $deductFromWinnings, $userId);
        } elseif ($hasBonusBalance) {
            $stmtUpdateUserBalance = $conn->prepare("UPDATE Users SET balance = balance - ?, bonus_balance = bonus_balance - ? WHERE id = ?");
            $stmtUpdateUserBalance->bind_param("ddi", $deductFromBalance, $deductFromBonus, $userId);
        } else {
            $stmtUpdateUserBalance = $conn->prepare("UPDATE Users SET balance = balance - ? WHERE id = ?");
            $stmtUpdateUserBalance->bind_param("di", $deductFromBalance, $userId);
        }
        $stmtUpdateUserBalance->execute();
        $stmtUpdateUserBalance->close();
    }

    if ($isFreeBetTicket) {
        $consumeFreeBetStmt = $conn->prepare(" 
            UPDATE UserBonuses
            SET free_bet_amount = GREATEST(0, COALESCE(free_bet_amount, 0) - ?),
                used = CASE WHEN COALESCE(free_bet_amount, 0) - ? <= 0 THEN 1 ELSE used END,
                status = CASE WHEN COALESCE(free_bet_amount, 0) - ? <= 0 THEN 'COMPLETED' ELSE status END,
                used_at = CASE WHEN COALESCE(free_bet_amount, 0) - ? <= 0 THEN NOW() ELSE used_at END
            WHERE id = ? AND user_id = ?
        ");
        $consumeFreeBetStmt->bind_param("ddddii", $freeBetToConsume, $freeBetToConsume, $freeBetToConsume, $freeBetToConsume, $freeBetUserBonusId, $userId);
        $consumeFreeBetStmt->execute();
        $consumeFreeBetStmt->close();
    }
    
    // 6. TRANSACTION RÖGZÍTÉSE - UserProfile modulos Transactions táblához
    $paymentMethodForTx = $isFreeBetTicket ? 'free_bet' : 'bet';
    $stmtTransaction = $conn->prepare(" 
        INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, created_at)
        VALUES (?, 'withdrawal', ?, ?, 'completed', ?, NOW())
    ");
    $transactionId = uniqid('BET_');
    $stmtTransaction->bind_param("idss", $userId, $stake, $paymentMethodForTx, $transactionId);
    $stmtTransaction->execute();
    $stmtTransaction->close();

    // 6.5. Korábbi hibából lezárt, de valójában nem teljesített DEPOSIT bónuszok visszanyitása
    $stmtReopenInconsistentBonus = $conn->prepare(" 
        UPDATE UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        SET ub.status = 'ACTIVE',
            ub.used = 0,
            ub.used_at = NULL
        WHERE ub.user_id = ?
          AND ub.status = 'COMPLETED'
          AND ub.used = 1
          AND bc.bonus_trigger = 'DEPOSIT'
          AND COALESCE(ub.wagering_required, 0) > 0
          AND COALESCE(ub.wagering_progress, 0) < ub.wagering_required
    ");
    $stmtReopenInconsistentBonus->bind_param("i", $userId);
    $stmtReopenInconsistentBonus->execute();
    $stmtReopenInconsistentBonus->close();

    // 6.6. BET triggeres bónuszok aktiválása (pl. darts bónusz) kvalifikáló fogadás után
    $isAllDartsTicket = false;
    if ($allSelectionsResolved && $selectionCount > 0 && count($ticketSportIds) === $selectionCount && !empty($dartsSportIds)) {
        $isAllDartsTicket = true;
        foreach ($ticketSportIds as $sportId) {
            if (!in_array((int)$sportId, $dartsSportIds, true)) {
                $isAllDartsTicket = false;
                break;
            }
        }
    }

    $betPendingStmt = $conn->prepare(" 
        SELECT
            ub.id AS user_bonus_id,
            bc.bonus_amount,
            bc.min_deposit,
            bc.min_combo,
            bc.min_odds,
            bc.min_odds_per_event,
            bc.wagering_multiplier,
            bc.activation_expire_hours,
            bc.sport_restriction,
            bc.bet_reward_type
        FROM UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        WHERE ub.user_id = ?
          AND ub.status = 'PENDING'
          AND ub.used = 0
          AND bc.bonus_trigger = 'BET'
          AND (bc.valid_to IS NULL OR bc.valid_to >= NOW())
    ");
    $betPendingStmt->bind_param("i", $userId);
    $betPendingStmt->execute();
    $betPendingRes = $betPendingStmt->get_result();
    $betPendingBonuses = $betPendingRes->fetch_all(MYSQLI_ASSOC);
    $betPendingStmt->close();

    $betBonusToBalance = 0.00;
    $betBonusToBonusBalance = 0.00;

    foreach ($betPendingBonuses as $betBonus) {
        $minStakeRequired = (float)($betBonus['min_deposit'] ?? 0);
        $minComboRequired = (int)($betBonus['min_combo'] ?? 0);
        $minOddsRequired = (float)($betBonus['min_odds'] ?? 0);
        $minOddsPerEventRequired = (float)($betBonus['min_odds_per_event'] ?? 0);
        $sportRestriction = strtoupper((string)($betBonus['sport_restriction'] ?? 'ANY'));

        if ($minStakeRequired > 0 && $stake < $minStakeRequired) {
            continue;
        }
        if ($minComboRequired > 0 && $selectionCount < $minComboRequired) {
            continue;
        }
        if ($minOddsRequired > 0 && $effectiveTotalOdds < $minOddsRequired) {
            continue;
        }
        if ($minOddsPerEventRequired > 0 && $ticketMinOdds < $minOddsPerEventRequired) {
            continue;
        }
        if ($sportRestriction === 'DARTS' && !$isAllDartsTicket) {
            continue;
        }

        $grantedBetBonus = (float)($betBonus['bonus_amount'] ?? 0);
        if ($grantedBetBonus <= 0) {
            continue;
        }

        $wageringRequired = 0.00;
        if (!empty($betBonus['wagering_multiplier']) && (float)$betBonus['wagering_multiplier'] > 0) {
            $wageringRequired = $grantedBetBonus * (float)$betBonus['wagering_multiplier'];
        }

        $betBonusExpiresAt = null;
        if (!empty($betBonus['activation_expire_hours']) && (int)$betBonus['activation_expire_hours'] > 0) {
            $betBonusExpiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$betBonus['activation_expire_hours'] . ' hours'));
        }

        $isFreeBetReward = (strtoupper((string)($betBonus['bet_reward_type'] ?? '')) === 'FREE_BET');
        $bonusMoneyAmount = $isFreeBetReward ? 0.00 : $grantedBetBonus;
        $freeBetAmount = $isFreeBetReward ? $grantedBetBonus : 0.00;

        $activateBetBonusStmt = $conn->prepare(" 
            UPDATE UserBonuses
            SET status = 'ACTIVE',
                granted_amount = ?,
                bonus_money_amount = ?,
                free_bet_amount = ?,
                wagering_required = ?,
                expires_at = ?
            WHERE id = ?
        ");
        $activateBetBonusStmt->bind_param("ddddsi", $grantedBetBonus, $bonusMoneyAmount, $freeBetAmount, $wageringRequired, $betBonusExpiresAt, $betBonus['user_bonus_id']);
        $activateBetBonusStmt->execute();
        $activateBetBonusStmt->close();

        if (strtoupper((string)($betBonus['bet_reward_type'] ?? '')) === 'BONUS_MONEY') {
            $betBonusToBalance += $grantedBetBonus;
        } elseif (!$isFreeBetReward) {
            $betBonusToBonusBalance += $grantedBetBonus;
        }
    }

    if ($betBonusToBalance > 0) {
        $betBonusBalanceStmt = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
        $betBonusBalanceStmt->bind_param("di", $betBonusToBalance, $userId);
        $betBonusBalanceStmt->execute();
        $betBonusBalanceStmt->close();
    }

    if ($betBonusToBonusBalance > 0) {
        $betBonusBonusBalanceStmt = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
        $betBonusBonusBalanceStmt->bind_param("di", $betBonusToBonusBalance, $userId);
        $betBonusBonusBalanceStmt->execute();
        $betBonusBonusBalanceStmt->close();
    }

    // 7. AKTÍV BÓNUSZOK FORGATÁSI HALADÁSÁNAK FRISSÍTÉSE
    $stmtUpdateWagering = $conn->prepare(" 
        UPDATE UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        SET ub.wagering_progress = LEAST(
                COALESCE(ub.wagering_progress, 0) + ?,
                COALESCE(ub.wagering_required, COALESCE(ub.wagering_progress, 0) + ?)
            )
        WHERE ub.user_id = ?
          AND ub.status = 'ACTIVE'
          AND ub.used = 0
          AND COALESCE(ub.wagering_required, 0) > 0
          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
          AND (bc.min_combo IS NULL OR bc.min_combo = 0 OR bc.min_combo <= ?)
          AND (bc.min_odds IS NULL OR bc.min_odds = 0 OR bc.min_odds <= ?)
          AND (bc.min_odds_per_event IS NULL OR bc.min_odds_per_event = 0 OR bc.min_odds_per_event <= ?)
    ");
        $stmtUpdateWagering->bind_param("ddiidd", $stake, $stake, $userId, $selectionCount, $effectiveTotalOdds, $ticketMinOdds);
    $stmtUpdateWagering->execute();
    $stmtUpdateWagering->close();

    // Ha teljesítve lett a forgatási követelmény, lezárjuk a bónuszt
    $stmtCompleteBonus = $conn->prepare(" 
        UPDATE UserBonuses
        SET status = 'COMPLETED',
            used = 1,
            used_at = NOW()
        WHERE user_id = ?
          AND status = 'ACTIVE'
          AND used = 0
          AND COALESCE(wagering_required, 0) > 0
          AND COALESCE(wagering_progress, 0) >= wagering_required
    ");
    $stmtCompleteBonus->bind_param("i", $userId);
    $stmtCompleteBonus->execute();
    $stmtCompleteBonus->close();

    // Aktuális egyenleg lekérdezése azonnali frontend frissítéshez
    $newBalance = null;
    $stmtBalance = $conn->prepare("SELECT balance FROM Users WHERE id = ? LIMIT 1");
    $stmtBalance->bind_param("i", $userId);
    $stmtBalance->execute();
    $balanceRes = $stmtBalance->get_result();
    $balanceRow = $balanceRes->fetch_assoc();
    $stmtBalance->close();
    if ($balanceRow && isset($balanceRow['balance'])) {
        $newBalance = (float)$balanceRow['balance'];
    }

    // TRANZAKCIÓ COMMIT
    $conn->commit();

    if (!isset($_SESSION['session_bet_total'])) {
        $_SESSION['session_bet_total'] = 0.0;
    }
    $_SESSION['session_bet_total'] = (float)$_SESSION['session_bet_total'] + (float)$stake;

    echo json_encode([
        'status' => 'ok',
        'message' => 'Ticket sikeresen leadva!',
        'ticket_id' => $ticketId,
        'stake' => $stake,
        'potential_win' => $potentialWin,
        'new_balance' => $newBalance,
        'free_bet_used' => $isFreeBetTicket
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Hiba a mentéskor: ' . $e->getMessage()]);
}

?>
