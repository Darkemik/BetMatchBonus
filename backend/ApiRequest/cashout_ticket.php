<?php
/**
 * CASHOUT_TICKET.PHP - Cash Out fogadás visszavétele
 * 
 * GET  ?ticket_id=X          → Cashout érték kiszámítása (nem hajt végre)
 * POST { ticketId: X }       → Cashout végrehajtása
 * 
 * CASHOUT LOGIKA (per-selection weight):
 * - WON: Co_i = Oe (teljes odds kredit)
 * - OPEN: Co_i = w = min(1.0, Oe/Ol)  (büntet ha romlik, nem jutalmaz 100%-on túl)
 * - Nincs live adat: Co_i = 1.0 (semleges)
 * - CashOut = (∏Co_i) × Tét × 0.92
 * - Ha bármely selection LOST → cashout = 0 (nincs lehetőség)
 */

session_start();
require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Nem vagy bejelentkezve!']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────
// GET: Cashout érték lekérése (preview)
// ────────────────────────────────────────────────────
if ($method === 'GET') {
    $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Hiányzó ticket_id']);
        exit;
    }

    $result = calculateCashout($conn, $ticketId, $userId);
    echo json_encode($result);
    exit;
}

// ────────────────────────────────────────────────────
// POST: Cashout végrehajtása
// ────────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($input['ticketId']) ? (int)$input['ticketId'] : 0;

    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Hiányzó ticketId']);
        exit;
    }

    // Cashout érték kiszámítása
    $calc = calculateCashout($conn, $ticketId, $userId);
    if ($calc['status'] !== 'ok' || !$calc['available'] || $calc['cashout_amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $calc['message'] ?? 'Cash out nem elérhető']);
        exit;
    }

    $cashoutAmount = (float)$calc['cashout_amount'];

    // Tranzakció
    $conn->begin_transaction();
    try {
        // 1. Ticket lezárása CASHOUT státuszra
        $stmtUpdate = $conn->prepare("
            UPDATE Tickets 
            SET status = 'CASHOUT', 
                cashout_amount = ?, 
                cashout_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'OPEN'
        ");
        $stmtUpdate->bind_param("dii", $cashoutAmount, $ticketId, $userId);
        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows === 0) {
            $stmtUpdate->close();
            throw new Exception('A szelvény már nem nyitott vagy nem a tiéd.');
        }
        $stmtUpdate->close();

        // 2. Összes OPEN selection lezárása CASHOUT státuszra
        $stmtSelUpd = $conn->prepare("
            UPDATE TicketSelections SET status = 'CASHOUT' WHERE ticket_id = ? AND status = 'OPEN'
        ");
        $stmtSelUpd->bind_param("i", $ticketId);
        $stmtSelUpd->execute();
        $stmtSelUpd->close();

        // 3. Egyenleg jóváírása (Users.balance + winnings_balance)
        $hasWinningsBalance = false;
        $wColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'winnings_balance'");
        $wColStmt->execute();
        $wColRes = $wColStmt->get_result()->fetch_assoc();
        $wColStmt->close();
        if ($wColRes && (int)$wColRes['cnt'] > 0) {
            $hasWinningsBalance = true;
        }

        if ($hasWinningsBalance) {
            $stmtBal = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
            $stmtBal->bind_param("ddi", $cashoutAmount, $cashoutAmount, $userId);
        } else {
            $stmtBal = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
            $stmtBal->bind_param("di", $cashoutAmount, $userId);
        }
        $stmtBal->execute();
        $stmtBal->close();

        // 4. WalletTransactions naplózása (type_id = 5 = CASHOUT)
        $stmtTx = $conn->prepare("
            INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
            SELECT id, ?, 5, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
        ");
        $stmtTx->bind_param("dii", $cashoutAmount, $ticketId, $userId);
        $stmtTx->execute();
        $stmtTx->close();

        // BalanceHistory bejegyzés
        require_once __DIR__ . '/../Auth/audit_helper.php';
        $coBal = $conn->query("SELECT balance FROM Users WHERE id = $userId")->fetch_assoc();
        $coNew = (float)($coBal['balance'] ?? 0);
        log_balance_change($userId, $coNew - $cashoutAmount, $coNew, $cashoutAmount, 'Cashout: #' . $ticketId . ' (' . number_format($cashoutAmount, 0, ',', ' ') . ' Ft)');

        // 5. Cashout már rögzítve a WalletTransactions-ben (fentebb)
        // A Transactions tábla csak valódi be/kifizetésekhez használatos.

        $conn->commit();

        // Frissített egyenleg lekérdezése
        $stmtNewBal = $conn->prepare("SELECT balance FROM Users WHERE id = ?");
        $stmtNewBal->bind_param("i", $userId);
        $stmtNewBal->execute();
        $newBalRow = $stmtNewBal->get_result()->fetch_assoc();
        $stmtNewBal->close();

        echo json_encode([
            'status' => 'ok',
            'message' => 'Cash out sikeres!',
            'cashout_amount' => $cashoutAmount,
            'new_balance' => (float)($newBalRow['balance'] ?? 0)
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Nem támogatott metódus']);
exit;

// ════════════════════════════════════════════════════
// CASHOUT ÉRTÉK KALKULÁCIÓ
// ════════════════════════════════════════════════════
function calculateCashout($conn, $ticketId, $userId) {
    // Ticket lekérése
    $stmtTicket = $conn->prepare("\n        SELECT id, stake, bonus_stake, user_bonus_id, total_odds, potential_win, status, cashout_amount, cashout_at\n        FROM Tickets\n        WHERE id = ? AND user_id = ?\n    ");
    $stmtTicket->bind_param("ii", $ticketId, $userId);
    $stmtTicket->execute();
    $ticket = $stmtTicket->get_result()->fetch_assoc();
    $stmtTicket->close();

    if (!$ticket) {
        return ['status' => 'error', 'available' => false, 'message' => 'Szelvény nem található'];
    }

    // Bónusz pénzből tett fogadások nem cashoutolhatók
    if ((float)($ticket['bonus_stake'] ?? 0) > 0) {
        return ['status' => 'ok', 'available' => false, 'message' => 'Bónusz egyenlegből tett fogadás nem cashoutolható'];
    }

    // Ingyenes fogadásból tett szelvények nem cashoutolhatók
    $ticketUserBonusId = (int)($ticket['user_bonus_id'] ?? 0);
    if ($ticketUserBonusId > 0) {
        $freeBetCheckStmt = $conn->prepare("\n            SELECT COALESCE(bc.bet_reward_type, '') AS bet_reward_type\n            FROM UserBonuses ub\n            INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id\n            WHERE ub.id = ? AND ub.user_id = ?\n            LIMIT 1\n        ");
        $freeBetCheckStmt->bind_param("ii", $ticketUserBonusId, $userId);
        $freeBetCheckStmt->execute();
        $freeBetCheckRow = $freeBetCheckStmt->get_result()->fetch_assoc();
        $freeBetCheckStmt->close();

        if (strtoupper((string)($freeBetCheckRow['bet_reward_type'] ?? '')) === 'FREE_BET') {
            return ['status' => 'ok', 'available' => false, 'message' => 'Ingyenes fogadásból tett szelvény nem cashoutolható'];
        }
    }

    // Már cash out-olt
    if ($ticket['status'] === 'CASHOUT') {
        return [
            'status' => 'ok',
            'available' => false,
            'message' => 'Már cash out-oltad ezt a szelvényt',
            'cashout_amount' => (float)$ticket['cashout_amount']
        ];
    }

    // Csak OPEN ticketekre érhető el
    if ($ticket['status'] !== 'OPEN') {
        return ['status' => 'ok', 'available' => false, 'message' => 'Csak nyitott szelvényre használható'];
    }

    $stake = (float)$ticket['stake'];
    $originalTotalOdds = (float)$ticket['total_odds'];
    $potentialWin = (float)$ticket['potential_win'];

    // Selections lekérése
    $stmtSel = $conn->prepare("
        SELECT ts.id, ts.match_id, ts.event_id, ts.pick_label, ts.market_name, 
               ts.odds_at_pick, ts.is_boosted, ts.status, ts.home_team, ts.away_team
        FROM TicketSelections ts
        WHERE ts.ticket_id = ?
    ");
    $stmtSel->bind_param("i", $ticketId);
    $stmtSel->execute();
    $selResult = $stmtSel->get_result();

    $selections = [];
    while ($row = $selResult->fetch_assoc()) {
        $selections[] = $row;
    }
    $stmtSel->close();

    if (empty($selections)) {
        return ['status' => 'ok', 'available' => false, 'message' => 'Nincs tétel a szelvényen'];
    }

    // Ha bármely selection LOST → nincs cashout
    foreach ($selections as $sel) {
        if ($sel['status'] === 'LOST') {
            return ['status' => 'ok', 'available' => false, 'cashout_amount' => 0, 'message' => 'Elvesztett tétel van a szelvényen'];
        }
    }

    // Oddsűrhajó (boosted) tételt tartalmazó szelvény nem cashoutolható
    foreach ($selections as $sel) {
        if (!empty($sel['is_boosted'])) {
            return ['status' => 'ok', 'available' => false, 'message' => 'Oddsűrhajó tételt tartalmazó szelvény nem cashoutolható'];
        }
    }

    // Minden selection WON → teljes kifizetés (de kissé csökkentett, mert még nem settled)
    $allWon = true;
    foreach ($selections as $sel) {
        if ($sel['status'] !== 'WON') {
            $allWon = false;
            break;
        }
    }
    // ── CASHOUT KALKULÁCIÓ ──
    // Per-selection weight modell:
    //   WON:   Co_i = Oe (teljes odds kredit)
    //   OPEN:  Co_i = w = min(1.0, Oe/Ol)  (büntet ha romlik, nem jutalmaz 100%-on túl)
    //   Nincs live adat: Co_i = 1.0 (semleges)
    //   CashOut = (∏Co_i) × Tét × 0.92
    $ALPHA = 0.92;

    $coProduct = 1.0;

    foreach ($selections as $sel) {
        $origOdds = (float)$sel['odds_at_pick'];

        if ($sel['status'] === 'WON') {
            // WON → teljes odds kredit
            $coProduct *= $origOdds;
        } elseif ($sel['status'] === 'OPEN') {
            // Live odds lekérése
            $matchApiId = (int)$sel['match_id'];
            $pickLabel  = $sel['pick_label'] ?? '';
            $marketName = $sel['market_name'] ?? '';

            $liveOdds = fetchLiveOddsForSelection($conn, $matchApiId, $marketName, $pickLabel);

            if ($liveOdds !== null && $liveOdds > 0) {
                // w = min(1.0, Oe/Ol) — büntet ha romlott, max 1.0 ha javult/változatlan
                $w = min(1.0, $origOdds / $liveOdds);
                $coProduct *= $w;
            }
            // Nincs live adat → Co_i = 1.0 (semleges, nem szorzunk)
        }
    }

    // CashOut = Co × Tét × 0.92
    $cashout = round($coProduct * $stake * $ALPHA, 0);

    // Maximum: potentialWin × alpha
    $cashout = min($cashout, round($potentialWin * $ALPHA, 0));

    return [
        'status' => 'ok',
        'available' => true,
        'cashout_amount' => $cashout,
        'potential_win' => $potentialWin,
        'stake' => $stake,
        'message' => $allWon
            ? 'Minden tipped nyert! Cash out elérhető.'
            : 'Cash out elérhető'
    ];
}

/**
 * Live odds lekérése egy adott selection-höz az API-ból
 * Visszaadja az aktuális odds-ot, vagy null ha nem sikerül
 */
function fetchLiveOddsForSelection($conn, $matchApiId, $marketName, $pickLabel) {
    if ($matchApiId <= 0) return null;

    try {
        $apiData = apiGet(EP_MATCH_DETAILS . '/' . $matchApiId);

        if (!isset($apiData['markets']) || !is_array($apiData['markets'])) {
            return null;
        }

        $marketNameLower = mb_strtolower(trim($marketName));
        $pickLabelLower  = mb_strtolower(trim($pickLabel));

        // 1) Pontos market név egyezés keresése
        foreach ($apiData['markets'] as $market) {
            $mName = mb_strtolower(trim($market['name'] ?? ''));
            if ($mName !== $marketNameLower) continue;

            foreach ($market['selections'] ?? [] as $selection) {
                $sName = mb_strtolower(trim($selection['name'] ?? ''));
                if ($sName === $pickLabelLower) {
                    $odd = (float)($selection['odd'] ?? 0);
                    if ($odd > 0) return $odd;
                }
            }
        }

        // 2) Ha nem találtuk pontosan → részleges egyezés (market típus + selection név)
        foreach ($apiData['markets'] as $market) {
            $mName = mb_strtolower(trim($market['name'] ?? ''));

            // Csak hasonló típusú piacot fogadunk el
            if (!marketTypeMatches($marketNameLower, $mName)) continue;

            foreach ($market['selections'] ?? [] as $selection) {
                $sName = mb_strtolower(trim($selection['name'] ?? ''));
                if ($sName === $pickLabelLower) {
                    $odd = (float)($selection['odd'] ?? 0);
                    if ($odd > 0) return $odd;
                }
            }
        }

        return null;
    } catch (Throwable $e) {
        error_log("Cashout live odds hiba (matchApiId=$matchApiId): " . $e->getMessage());
        return null;
    }
}

/**
 * Két market név típus-szintű egyezése (1x2 ≈ 1x2, over/under ≈ over/under, stb.)
 */
function marketTypeMatches($original, $live) {
    $types = [
        ['1x2', 'winner', 'győztes', 'gyoztes', 'match result', 'moneyline'],
        ['over', 'under', 'több', 'tobb', 'kevesebb', 'total', 'gólszám', 'golszam'],
        ['both teams', 'mindkét', 'mindket', 'btts'],
    ];

    foreach ($types as $group) {
        $origMatch = false;
        $liveMatch = false;
        foreach ($group as $keyword) {
            if (strpos($original, $keyword) !== false) $origMatch = true;
            if (strpos($live, $keyword) !== false) $liveMatch = true;
        }
        if ($origMatch && $liveMatch) return true;
    }

    return false;
}
