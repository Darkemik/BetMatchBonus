<?php
session_start();
require_once dirname(__DIR__) . '/Auth/admin_guard.php';
admin_guard('ADMIN');

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
    SELECT t.id, t.user_id, t.stake, t.bonus_stake, t.total_odds, t.potential_win, t.status
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

        $conn->commit();
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
            $isBonusTicket = ($bonusStake > 0);

            if ($isBonusTicket) {
                // Bónusz szelvény: ellenőrizzük a wagering-et
                $wagerStmt = $conn->prepare("
                    SELECT 1 FROM UserBonuses
                    WHERE user_id = ?
                      AND status = 'COMPLETED'
                      AND used = 1
                      AND COALESCE(wagering_required, 0) > 0
                      AND COALESCE(wagering_progress, 0) >= wagering_required
                    ORDER BY used_at DESC
                    LIMIT 1
                ");
                $wagerStmt->bind_param("i", $userId);
                $wagerStmt->execute();
                $wageringCompleted = $wagerStmt->get_result()->num_rows > 0;
                $wagerStmt->close();

                // Max win cap ellenőrzés
                $capStmt = $conn->prepare("
                    SELECT ub.granted_amount, bc.max_win_multiplier
                    FROM UserBonuses ub
                    INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
                    WHERE ub.user_id = ?
                      AND ub.status IN ('ACTIVE', 'COMPLETED')
                      AND COALESCE(ub.granted_amount, 0) > 0
                    ORDER BY ub.id DESC
                    LIMIT 1
                ");
                $capStmt->bind_param("i", $userId);
                $capStmt->execute();
                $capRow = $capStmt->get_result()->fetch_assoc();
                $capStmt->close();

                if ($capRow) {
                    $maxWin = (float)$capRow['granted_amount'] * (float)$capRow['max_win_multiplier'];
                    if ($potentialWin > $maxWin) {
                        $potentialWin = $maxWin;
                    }
                }

                if ($wageringCompleted) {
                    $credit = $conn->prepare("UPDATE Users SET balance = balance + ?, winnings_balance = winnings_balance + ? WHERE id = ?");
                    $credit->bind_param("ddi", $potentialWin, $potentialWin, $userId);
                } else {
                    $credit = $conn->prepare("UPDATE Users SET bonus_balance = bonus_balance + ? WHERE id = ?");
                    $credit->bind_param("di", $potentialWin, $userId);
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
        }

        $conn->commit();

        if ($newStatus === 'WON') {
            echo json_encode(['success' => true, 'message' => "Szelvény #$ticketId → WON! Nyeremény jóváírva: " . number_format($potentialWin, 0, ',', ' ') . " Ft"]);
        } else {
            echo json_encode(['success' => true, 'message' => "Szelvény #$ticketId → LOST. Nincs kifizetés."]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
