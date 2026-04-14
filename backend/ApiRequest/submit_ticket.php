<?php
/**
 * SUBMIT_TICKET.PHP - Ticket mentése az adatbázisba
 * POST JSON: { stake, totalOdds, potentialWin, items: [{homeTeam, awayTeam, pick, odds, market, matchId}] }
 */

session_start();
require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . '/Auth/settings_helper.php';

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
$useBonusBet = !empty($input['useBonus']);
$freeBetUserBonusId = isset($input['freeBetUserBonusId']) ? (int)$input['freeBetUserBonusId'] : 0;
$userBonusId = isset($input['userBonusId']) ? (int)$input['userBonusId'] : 0;
$hasDailyTipBoost = !empty($input['hasDailyTipBoost']);
$hasOddsPyramidBoost = !empty($input['hasOddsPyramidBoost']);
$selectionCount = count($items);
$ticketMinOdds = null;
$calculatedTotalOdds = 1.0;
$ticketSportIds = [];
$allSelectionsResolved = true;
$hasBonusBalance = false;
$hasWinningsBalance = false;
$deductFromBalance = 0.0;
$deductFromDeposited = 0.0;
$deductFromWinnings = 0.0;
$deductFromBonus = 0.0;
$freeBetToConsume = 0.0;
$isFreeBetTicket = false;

$minBet = get_setting_int('min_bet_amount', 100);
if ($stake < $minBet || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Minimum tét: ' . number_format($minBet, 0, ',', ' ') . ' Ft, legalább 1 tétel szükséges']);
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

// Napi tipp 1.2x szorzó ellenőrzése szerver oldalon
$dailyTipBoostVerified = false;
if ($hasDailyTipBoost) {
    $dailyTipsCacheFile = dirname(__DIR__) . '/uploads/daily_tips_cache.json';
    if (file_exists($dailyTipsCacheFile)) {
        $dailyTipsCache = json_decode(file_get_contents($dailyTipsCacheFile), true);
        $todayUTC = gmdate('Y-m-d');
        if (is_array($dailyTipsCache) && isset($dailyTipsCache['date']) && $dailyTipsCache['date'] === $todayUTC && !empty($dailyTipsCache['tips'])) {
            // Összegyűjtjük a napi tipp eventId-keit
            $dailyTipEventIds = [];
            foreach ($dailyTipsCache['tips'] as $tip) {
                if (!empty($tip['picks'])) {
                    foreach ($tip['picks'] as $pick) {
                        if (!empty($pick['eventId'])) {
                            $dailyTipEventIds[] = (int)$pick['eventId'];
                        }
                    }
                }
            }
            // Ellenőrizzük, hogy legalább egy tétel a szelvényen napi tipp-e
            foreach ($items as $item) {
                $itemMatchId = isset($item['matchId']) ? (int)$item['matchId'] : 0;
                if ($itemMatchId > 0 && in_array($itemMatchId, $dailyTipEventIds, true)) {
                    $dailyTipBoostVerified = true;
                    break;
                }
            }
        }
    }
}

if ($dailyTipBoostVerified) {
    $dailyTipMultiplier = get_setting_float('daily_tip_multiplier', 1.2);
    $calculatedTotalOdds = round($calculatedTotalOdds * $dailyTipMultiplier, 2);
}

// Oddspiramis: szorzó ha 6+ fogadás van a szelvényen (szerver oldali ellenőrzés)
$oddsPyramidBoostVerified = false;
$minPyramidSelections = get_setting_int('min_pyramid_selections', 6);
if ($hasOddsPyramidBoost && $selectionCount >= $minPyramidSelections) {
    $oddsPyramidBoostVerified = true;
    $oddsPyramidMultiplier = get_setting_float('odds_pyramid_multiplier', 1.3);
    $calculatedTotalOdds = round($calculatedTotalOdds * $oddsPyramidMultiplier, 2);
}

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

// Bónusz és rendes pénz nem keverhető! A felhasználó választ.
if ($useBonusBet) {
    // Konkrét bónusz egyenlegből fogadás (userBonusId kötelező)
    if ($userBonusId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Válassz ki egy bónusz egyenleget a fogadáshoz!']);
        exit;
    }
    $bonusCheckStmt = $conn->prepare("
        SELECT ub.id, ub.bonus_balance, ub.granted_amount, bc.max_win_multiplier
        FROM UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        WHERE ub.id = ? AND ub.user_id = ? AND ub.status = 'ACTIVE' AND ub.used = 0
          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
        LIMIT 1
    ");
    $bonusCheckStmt->bind_param("ii", $userBonusId, $userId);
    $bonusCheckStmt->execute();
    $selectedBonus = $bonusCheckStmt->get_result()->fetch_assoc();
    $bonusCheckStmt->close();

    if (!$selectedBonus) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'A kiválasztott bónusz nem elérhető.']);
        exit;
    }
    $availableForBet = (float)$selectedBonus['bonus_balance'];
} else {
    $availableForBet = $mainBalance;
}

// Bónusz bet és free bet nem kombinálható
if ($useBonusBet && $useFreeBet) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bónusz egyenleg és ingyenes fogadás nem használható egyszerre.']);
    exit;
}

if ($useFreeBet) {
    if ($freeBetUserBonusId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ingyenes fogadás nincs megfelelően kiválasztva.']);
        exit;
    }

    $freeBetStmt = $conn->prepare(" 
                SELECT ub.id,
                             COALESCE(ub.free_bet_amount, 0) AS free_bet_amount,
                             COALESCE(bc.min_combo, 0) AS min_combo,
                             COALESCE(bc.min_odds, 0) AS min_odds,
                             COALESCE(bc.min_odds_per_event, 0) AS min_odds_per_event
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

    $freeBetMinCombo = (int)($freeBetRow['min_combo'] ?? 0);
    $freeBetMinOdds = (float)($freeBetRow['min_odds'] ?? 0);
    $freeBetMinOddsPerEvent = (float)($freeBetRow['min_odds_per_event'] ?? 0);
    if (($freeBetMinCombo > 0 && $selectionCount < $freeBetMinCombo)
        || ($freeBetMinOdds > 0 && $effectiveTotalOdds < $freeBetMinOdds)
        || ($freeBetMinOddsPerEvent > 0 && $ticketMinOdds < $freeBetMinOddsPerEvent)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Az ingyenes fogadás feltételei nem teljesülnek a kiválasztott szelvényhez.']);
        exit;
    }

    $isFreeBetTicket = true;
    $freeBetToConsume = $stake;
}

// A kifizetéshez mindig szerver oldalon számoljuk a potenciális nyereményt.
// Ingyenes fogadásnál a tét nem jár vissza, ezért nettó nyereményt tárolunk.
if ($isFreeBetTicket) {
    $potentialWin = round($stake * max(0, ($effectiveTotalOdds - 1)), 2);
} else {
    $potentialWin = round($stake * $effectiveTotalOdds, 2);
}

// Bónusz szelvénynél: max nyeremény cap (max_win_multiplier × granted_amount)
if ($useBonusBet && !$isFreeBetTicket && isset($selectedBonus)) {
    $maxWinMultiplier = (float)($selectedBonus['max_win_multiplier'] ?? 5.0);
    $grantedAmount = (float)$selectedBonus['granted_amount'];
    $maxWin = $grantedAmount * $maxWinMultiplier;
    if ($potentialWin > $maxWin) {
        $potentialWin = $maxWin;
    }
}

if (!$wallet || (!$isFreeBetTicket && $availableForBet < $stake)) {
    http_response_code(400);
    $msg = $useBonusBet
        ? 'Nincs elegendő bónusz egyenleg!'
        : 'Nincs elegendő egyenleg! Kérjük, töltse fel az accountot.';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Levonás: bónusz VAGY rendes egyenleg (nem keverhető!)
$remainingStake = $isFreeBetTicket ? 0.0 : $stake;

if ($useBonusBet) {
    // Bónusz egyenlegből fogadás
    $deductFromBonus = $remainingStake;
    $remainingStake = 0.0;
} else {
    // Rendes egyenlegből fogadás (befizetett → nyeremény prioritás)
    $deductFromDeposited = min($remainingStake, $depositedPart);
    $remainingStake -= $deductFromDeposited;

    if ($hasWinningsBalance && $remainingStake > 0) {
        $deductFromWinnings = min($remainingStake, max(0.0, $winningsBalance));
        $remainingStake -= $deductFromWinnings;
    }
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
    $ticketUserBonusId = $useBonusBet ? $userBonusId : null;
    $stmtTicket = $conn->prepare("
        INSERT INTO Tickets (user_id, stake, bonus_stake, user_bonus_id, total_odds, potential_win, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'OPEN', NOW(), NOW())
    ");
    $stmtTicket->bind_param("iddidd", $userId, $stake, $deductFromBonus, $ticketUserBonusId, $totalOdds, $potentialWin);
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

    // 5b. KONKRÉT BÓNUSZ EGYENLEG CSÖKKENTÉSE (multi-bonus rendszer)
    if ($useBonusBet && $deductFromBonus > 0 && $userBonusId > 0) {
        $stmtDeductBonusIndiv = $conn->prepare("
            UPDATE UserBonuses SET bonus_balance = GREATEST(0, bonus_balance - ?) WHERE id = ? AND user_id = ?
        ");
        $stmtDeductBonusIndiv->bind_param("dii", $deductFromBonus, $userBonusId, $userId);
        $stmtDeductBonusIndiv->execute();
        $stmtDeductBonusIndiv->close();
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
    
    // 6. Fogadás már rögzítve a WalletTransactions-ben (fentebb, 4. lépés)
    // A Transactions tábla csak valódi be/kifizetésekhez (deposit/withdrawal) használatos.

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
                bonus_balance = ?,
                wagering_required = ?,
                expires_at = ?
            WHERE id = ?
        ");
        $individualBonusBal = $isFreeBetReward ? 0.00 : $grantedBetBonus;
        $activateBetBonusStmt->bind_param("dddddsi", $grantedBetBonus, $bonusMoneyAmount, $freeBetAmount, $individualBonusBal, $wageringRequired, $betBonusExpiresAt, $betBonus['user_bonus_id']);
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
    // Csak bónusz egyenlegből tett fogadás számít bele a forgatási követelménybe!
    // Multi-bonus: csak a kiválasztott bónuszé frissül!
    if ($useBonusBet && $deductFromBonus > 0 && $userBonusId > 0) {
    $stmtUpdateWagering = $conn->prepare(" 
        UPDATE UserBonuses ub
        INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
        SET ub.wagering_progress = LEAST(
                COALESCE(ub.wagering_progress, 0) + ?,
                COALESCE(ub.wagering_required, COALESCE(ub.wagering_progress, 0) + ?)
            )
        WHERE ub.id = ?
          AND ub.user_id = ?
          AND ub.status = 'ACTIVE'
          AND ub.used = 0
          AND COALESCE(ub.wagering_required, 0) > 0
          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
          AND (bc.min_combo IS NULL OR bc.min_combo = 0 OR bc.min_combo <= ?)
          AND (bc.min_odds IS NULL OR bc.min_odds = 0 OR bc.min_odds <= ?)
          AND (bc.min_odds_per_event IS NULL OR bc.min_odds_per_event = 0 OR bc.min_odds_per_event <= ?)
    ");
        $stmtUpdateWagering->bind_param("ddiiidd", $stake, $stake, $userBonusId, $userId, $selectionCount, $effectiveTotalOdds, $ticketMinOdds);
    $stmtUpdateWagering->execute();
    $stmtUpdateWagering->close();

    // Ha teljesítve lett a forgatási követelmény, lezárjuk a bónuszt (csak a kiválasztott)
    $stmtCompleteBonus = $conn->prepare(" 
        UPDATE UserBonuses
        SET status = 'COMPLETED',
            used = 1,
            used_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND status = 'ACTIVE'
          AND used = 0
          AND COALESCE(wagering_required, 0) > 0
          AND COALESCE(wagering_progress, 0) >= wagering_required
    ");
    $stmtCompleteBonus->bind_param("ii", $userBonusId, $userId);
    $stmtCompleteBonus->execute();
    $bonusCompleted = $stmtCompleteBonus->affected_rows > 0;
    $stmtCompleteBonus->close();

    // Ha ez a bónusz teljesítve lett, az egyenlege átkerül a rendes balance-ba
    if ($bonusCompleted) {
        $completedBonusBalStmt = $conn->prepare("SELECT bonus_balance FROM UserBonuses WHERE id = ? LIMIT 1");
        $completedBonusBalStmt->bind_param("i", $userBonusId);
        $completedBonusBalStmt->execute();
        $completedBonusBalRow = $completedBonusBalStmt->get_result()->fetch_assoc();
        $completedBonusBalStmt->close();

        $completedBonusBal = (float)($completedBonusBalRow['bonus_balance'] ?? 0);
        if ($completedBonusBal > 0) {
            // Átvitel rendes egyenlegbe
            $moveStmt = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ?, bonus_balance = bonus_balance - ? WHERE id = ?");
            $moveStmt->bind_param("dddi", $completedBonusBal, $completedBonusBal, $completedBonusBal, $userId);
            $moveStmt->execute();
            $moveStmt->close();

            // UserBonuses egyenlege nullázása
            $zeroBonusStmt = $conn->prepare("UPDATE UserBonuses SET bonus_balance = 0 WHERE id = ?");
            $zeroBonusStmt->bind_param("i", $userBonusId);
            $zeroBonusStmt->execute();
            $zeroBonusStmt->close();
        }
    }
    } // end if ($useBonusBet) — forgatás + transfer blokk

    // Aktuális egyenleg lekérdezése azonnali frontend frissítéshez
    $newBalance = null;
    $newBonusBalance = null;
    $stmtBalance = $conn->prepare("SELECT balance, bonus_balance FROM Users WHERE id = ? LIMIT 1");
    $stmtBalance->bind_param("i", $userId);
    $stmtBalance->execute();
    $balanceRes = $stmtBalance->get_result();
    $balanceRow = $balanceRes->fetch_assoc();
    $stmtBalance->close();
    if ($balanceRow) {
        $newBalance = (float)($balanceRow['balance'] ?? 0);
        $newBonusBalance = (float)($balanceRow['bonus_balance'] ?? 0);
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
        'new_bonus_balance' => $newBonusBalance,
        'free_bet_used' => $isFreeBetTicket,
        'bonus_bet_used' => $useBonusBet
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Hiba a mentéskor: ' . $e->getMessage()]);
}

?>
