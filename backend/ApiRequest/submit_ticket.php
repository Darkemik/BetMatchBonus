<?php
/**
 * SUBMIT_TICKET.PHP - Ticket mentése az adatbázisba
 * POST JSON: { stake, totalOdds, potentialWin, items: [{homeTeam, awayTeam, pick, odds, market, matchId}] }
 */

session_start();
require_once __DIR__ . "/connect.php";

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
$stmtWallet = $conn->prepare("SELECT balance FROM Wallets WHERE user_id = ?");
$stmtWallet->bind_param("i", $userId);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
$wallet = $walletResult->fetch_assoc();
$stmtWallet->close();

if (!$wallet || $wallet['balance'] < $stake) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nincs elegendő egyenleg!']);
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
    $stmtSelection = $conn->prepare("
        INSERT INTO TicketSelections (ticket_id, outcome_id, event_id, odds_at_pick, status, created_at)
        VALUES (?, ?, ?, ?, 'OPEN', NOW())
    ");

    foreach ($items as $item) {
        $matchId = (int)$item['matchId'];
        $pick = $item['pick'];
        $odds = (float)$item['odds'];
        $market = $item['market'];

        // Outcome és Event ID keresése az adatbázisból (egyszerűsített - az API-tól jön)
        $stmtOutcome = $conn->prepare("
            SELECT o.id FROM OddsOutcomes o
            JOIN EventMarkets em ON o.event_market_id = em.id
            WHERE em.event_id = ? AND o.label = ?
            LIMIT 1
        ");
        $stmtOutcome->bind_param("is", $matchId, $pick);
        $stmtOutcome->execute();
        $outcomeResult = $stmtOutcome->get_result();
        $outcomeRow = $outcomeResult->fetch_assoc();
        $stmtOutcome->close();

        if ($outcomeRow) {
            $outcomeId = (int)$outcomeRow['id'];
            $stmtSelection->bind_param("iiid", $ticketId, $outcomeId, $matchId, $odds);
            $stmtSelection->execute();
        }
    }
    $stmtSelection->close();

    // 3. WALLET UPDATE - Tét levonása
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

    // TRANZAKCIÓ COMMIT
    $conn->commit();

    echo json_encode([
        'status' => 'ok',
        'message' => 'Ticket sikeresen leadva!',
        'ticket_id' => $ticketId,
        'stake' => $stake,
        'potential_win' => $potentialWin
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Hiba a mentéskor: ' . $e->getMessage()]);
}

?>
