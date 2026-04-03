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

if ($stake < 100 || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Minimum tét: 100 Ft, legalább 1 tétel szükséges']);
    exit;
}

// Wallet ellenőrzése
$stmtWallet = $conn->prepare("SELECT balance FROM Users WHERE id = ?");
$stmtWallet->bind_param("i", $userId);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
$wallet = $walletResult->fetch_assoc();
$stmtWallet->close();

if (!$wallet || !isset($wallet['balance']) || $wallet['balance'] === 0 || $wallet['balance'] < $stake) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nincs elegendő egyenleg! Kérjük, töltse fel az accountot.']);
    exit;
}

// TRANZAKCIÓ KEZDÉSE
$conn->begin_transaction();

try {
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

        // Outcome és Event ID keresése az adatbázisból (opcionális - lehet NULL)
        $outcomeId = null;
        $eventId = null;

        $stmtEvent = $conn->prepare("SELECT id FROM Events WHERE api_id = ? LIMIT 1");
        $stmtEvent->bind_param("i", $matchId);
        $stmtEvent->execute();
        $eventResult = $stmtEvent->get_result();
        $eventRow = $eventResult->fetch_assoc();
        $stmtEvent->close();

        if ($eventRow) {
            $eventId = (int)$eventRow['id'];

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
        $stmtUpdateWallet = $conn->prepare("
            UPDATE Wallets SET balance = balance - ? WHERE user_id = ?
        ");
        $stmtUpdateWallet->bind_param("di", $stake, $userId);
        $stmtUpdateWallet->execute();
        $stmtUpdateWallet->close();

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
    $stmtUpdateUserBalance = $conn->prepare("
        UPDATE Users SET balance = balance - ? WHERE id = ?
    ");
    $stmtUpdateUserBalance->bind_param("di", $stake, $userId);
    $stmtUpdateUserBalance->execute();
    $stmtUpdateUserBalance->close();
    
    // 6. TRANSACTION RÖGZÍTÉSE - UserProfile modulos Transactions táblához
    $stmtTransaction = $conn->prepare("
        INSERT INTO Transactions (user_id, type, amount, payment_method, status, transaction_id, created_at)
        VALUES (?, 'withdrawal', ?, 'bet', 'completed', ?, NOW())
    ");
    $transactionId = uniqid('BET_');
    $stmtTransaction->bind_param("ids", $userId, $stake, $transactionId);
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

    // 7. AKTÍV BÓNUSZOK FORGATÁSI HALADÁSÁNAK FRISSÍTÉSE
    $stmtUpdateWagering = $conn->prepare(" 
        UPDATE UserBonuses
        SET wagering_progress = LEAST(
                COALESCE(wagering_progress, 0) + ?,
                COALESCE(wagering_required, COALESCE(wagering_progress, 0) + ?)
            )
        WHERE user_id = ?
          AND status = 'ACTIVE'
          AND used = 0
          AND COALESCE(wagering_required, 0) > 0
          AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmtUpdateWagering->bind_param("ddi", $stake, $stake, $userId);
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

    echo json_encode([
        'status' => 'ok',
        'message' => 'Ticket sikeresen leadva!',
        'ticket_id' => $ticketId,
        'stake' => $stake,
        'potential_win' => $potentialWin,
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Hiba a mentéskor: ' . $e->getMessage()]);
}

?>
