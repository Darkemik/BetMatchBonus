<?php
session_start();
require_once dirname(__DIR__) . '/Auth/admin_guard.php';
admin_guard('ADMIN');

require_once dirname(__DIR__) . '/Auth/audit_helper.php';
require_once dirname(__DIR__) . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
    exit;
}

$action   = $_POST['action'] ?? '';
$ticketId = (int)($_POST['ticket_id'] ?? 0);

if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen szelvény ID.']);
    exit;
}

// Szelvény lekérése
$stmt = $conn->prepare("
    SELECT t.id, t.user_id, t.stake, t.bonus_stake, t.user_bonus_id, t.total_odds, t.potential_win, t.status
    FROM Tickets t
    WHERE t.id = ?
");
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Szelvény nem található.']);
    exit;
}

$userId     = (int)$ticket['user_id'];
$stake      = (float)$ticket['stake'];
$bonusStake = (float)$ticket['bonus_stake'];
$userBonusId = $ticket['user_bonus_id'] ? (int)$ticket['user_bonus_id'] : null;
$potentialWin = (float)$ticket['potential_win'];

// ── 1) VOID – Szelvény érvénytelenítése (csak OPEN) ──
if ($action === 'void') {
    if ($ticket['status'] !== 'OPEN') {
        echo json_encode(['success' => false, 'message' => 'Csak OPEN státuszú szelvényt lehet érvényteleníteni.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Szelvény státusz → VOID
        $upd = $conn->prepare("UPDATE Tickets SET status = 'VOID', updated_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $ticketId);
        $upd->execute();
        $upd->close();

        // Tippek státusz → VOID
        $updSel = $conn->prepare("UPDATE TicketSelections SET status = 'VOID' WHERE ticket_id = ?");
        $updSel->bind_param("i", $ticketId);
        $updSel->execute();
        $updSel->close();

        // Tét visszaadása
        if ($bonusStake > 0) {
            // Bónusz tét → bonus_balance-ba vissza
            $bal = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
            $bal->bind_param("di", $bonusStake, $userId);
            $bal->execute();
            $bal->close();
        }
        if ($stake > 0) {
            // Normál tét → balance-ba vissza
            $bal = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
            $bal->bind_param("di", $stake, $userId);
            $bal->execute();
            $bal->close();
        }

        // WalletTransaction log (type_id = 6 = VOID / refund)
        $totalRefund = $stake + $bonusStake;
        $tx = $conn->prepare("
            INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
            SELECT id, ?, 6, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
        ");
        $tx->bind_param("dii", $totalRefund, $ticketId, $userId);
        $tx->execute();
        $tx->close();

        // BalanceHistory bejegyzés (normál tét visszatérítés)
        if ($stake > 0) {
            $vdBal = $conn->query("SELECT balance FROM Users WHERE id = $userId")->fetch_assoc();
            $vdNew = (float)($vdBal['balance'] ?? 0);
            log_balance_change($userId, $vdNew - $stake, $vdNew, $stake, 'Void visszatérítés: szelvény #' . $ticketId);
        }

        $conn->commit();
        log_audit('ticket_void', 'ticket', $ticketId, "Szelvény #$ticketId érvénytelenítve, tét visszaadva: " . number_format($totalRefund, 0, ',', ' ') . " Ft");
        echo json_encode(['success' => true, 'message' => "Szelvény #$ticketId érvénytelenítve! Tét visszaadva: " . number_format($totalRefund, 0, ',', ' ') . " Ft"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
    }
    exit;
}

// ── 2) Manuális lezárás (WON / LOST) – csak OPEN ──
if ($action === 'manual_close') {
    $newStatus = strtoupper(trim($_POST['new_status'] ?? ''));

    if (!in_array($newStatus, ['WON', 'LOST'])) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen státusz. Csak WON vagy LOST.']);
        exit;
    }

    if ($ticket['status'] !== 'OPEN') {
        echo json_encode(['success' => false, 'message' => 'Csak OPEN státuszú szelvényt lehet manuálisan lezárni.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Szelvény státusz frissítése
        $upd = $conn->prepare("UPDATE Tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param("si", $newStatus, $ticketId);
        $upd->execute();
        $upd->close();

        // Tippek státusz frissítése
        $updSel = $conn->prepare("UPDATE TicketSelections SET status = ? WHERE ticket_id = ? AND status = 'OPEN'");
        $updSel->bind_param("si", $newStatus, $ticketId);
        $updSel->execute();
        $updSel->close();

        if ($newStatus === 'WON') {
            $isBonusTicket = ($bonusStake > 0 && $userBonusId);

            if ($isBonusTicket) {
                // A szelvényhez tartozó konkrét UserBonuses rekord lekérése
                $ubStmt = $conn->prepare("
                    SELECT ub.id, ub.granted_amount, ub.wagering_required, ub.wagering_progress,
                           ub.status AS ub_status, ub.used,
                           COALESCE(bc.max_win_multiplier, 5.00) AS max_win_multiplier,
                           COALESCE(bc.bet_reward_type, '') AS bet_reward_type
                    FROM UserBonuses ub
                    INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
                    WHERE ub.id = ?
                ");
                $ubStmt->bind_param("i", $userBonusId);
                $ubStmt->execute();
                $ubRow = $ubStmt->get_result()->fetch_assoc();
                $ubStmt->close();

                // Max win cap ellenőrzés
                if ($ubRow) {
                    $maxWin = (float)$ubRow['granted_amount'] * (float)$ubRow['max_win_multiplier'];
                    if ($maxWin > 0 && $potentialWin > $maxWin) {
                        $potentialWin = $maxWin;
                    }
                }

                // Free bet (wagering=0) vagy wagering teljesítve → egyenlegre
                $wageringReq = (float)($ubRow['wagering_required'] ?? 0);
                $wageringProg = (float)($ubRow['wagering_progress'] ?? 0);
                $isFreeBet = (strtoupper($ubRow['bet_reward_type'] ?? '') === 'FREE_BET');
                $wageringDone = ($wageringReq <= 0 || $wageringProg >= $wageringReq);

                if ($isFreeBet || $wageringDone) {
                    // Egyenlegre (balance + winnings_balance)
                    $credit = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
                    $credit->bind_param("ddi", $potentialWin, $potentialWin, $userId);
                } else {
                    // Forgatás nem teljesült → bónusz egyenlegre
                    $credit = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
                    $credit->bind_param("di", $potentialWin, $userId);

                    // Egyedi bónusz egyenleg is frissítés
                    $ubCredit = $conn->prepare("UPDATE UserBonuses SET bonus_balance = bonus_balance + ? WHERE id = ?");
                    $ubCredit->bind_param("di", $potentialWin, $userBonusId);
                    $ubCredit->execute();
                    $ubCredit->close();
                }
            } else {
                // Normál szelvény → balance + winnings_balance
                $credit = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
                $credit->bind_param("ddi", $potentialWin, $potentialWin, $userId);
            }

            $credit->execute();
            $credit->close();

            // WalletTransaction log (type_id = 4 = WIN)
            $tx = $conn->prepare("
                INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
                SELECT id, ?, 4, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
            ");
            $tx->bind_param("dii", $potentialWin, $ticketId, $userId);
            $tx->execute();
            $tx->close();

            // BalanceHistory bejegyzés (csak ha rendes egyenlegbe ment)
            if (!$isBonusTicket || ($userBonusId > 0 && ($isFreeBet || $wageringDone))) {
                $atBal = $conn->query("SELECT balance FROM Users WHERE id = $userId")->fetch_assoc();
                $atNew = (float)($atBal['balance'] ?? 0);
                log_balance_change($userId, $atNew - $potentialWin, $atNew, $potentialWin, 'Admin nyeremény: szelvény #' . $ticketId);
            }
        }

        $conn->commit();

        if ($newStatus === 'WON') {
            log_audit('ticket_close', 'ticket', $ticketId, "Szelvény #$ticketId → WON, nyeremény: " . number_format($potentialWin, 0, ',', ' ') . " Ft");
            echo json_encode(['success' => true, 'message' => "Szelvény #$ticketId → WON! Nyeremény jóváírva: " . number_format($potentialWin, 0, ',', ' ') . " Ft"]);
        } else {
            log_audit('ticket_close', 'ticket', $ticketId, "Szelvény #$ticketId → LOST");
            echo json_encode(['success' => true, 'message' => "Szelvény #$ticketId → LOST. Nincs kifizetés."]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
