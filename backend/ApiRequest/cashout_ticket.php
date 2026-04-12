<?php
/**
 * CASHOUT_TICKET.PHP - Cash Out fogadás visszavétele
 * 
 * GET  ?ticket_id=X          → Cashout érték kiszámítása (nem hajt végre)
 * POST { ticketId: X }       → Cashout végrehajtása
 * 
 * CASHOUT LOGIKA:
 * - Alap: tét × (aktuális összszorzó / eredeti összszorzó) × 0.90 (10% jutalék)
 * - Ha egy selectionhöz tartozó meccs élő és az ellenfél vezet / gólt lőtt → csökkentett szorzó
 * - Ha bármely selection LOST → cashout = 0 (nincs lehetőség)
 * - Ha minden selection WON → cashout = potential_win (teljes kifizetés)
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
    $stmtTicket = $conn->prepare("
        SELECT id, stake, total_odds, potential_win, status, cashout_amount, cashout_at
        FROM Tickets
        WHERE id = ? AND user_id = ?
    ");
    $stmtTicket->bind_param("ii", $ticketId, $userId);
    $stmtTicket->execute();
    $ticket = $stmtTicket->get_result()->fetch_assoc();
    $stmtTicket->close();

    if (!$ticket) {
        return ['status' => 'error', 'available' => false, 'message' => 'Szelvény nem található'];
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
               ts.odds_at_pick, ts.status, ts.home_team, ts.away_team
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

    // Minden selection WON → teljes kifizetés (de kissé csökkentett, mert még nem settled)
    $allWon = true;
    foreach ($selections as $sel) {
        if ($sel['status'] !== 'WON') {
            $allWon = false;
            break;
        }
    }
    if ($allWon) {
        // Minden nyert → 95%-ot adjuk (5% jutalék az azonnali kifizetésért)
        $cashout = round($potentialWin * 0.95, 0);
        return [
            'status' => 'ok',
            'available' => true,
            'cashout_amount' => $cashout,
            'potential_win' => $potentialWin,
            'message' => 'Minden tipped nyert! Cash out elérhető.'
        ];
    }

    // ── DINAMIKUS CASHOUT KALKULÁCIÓ ──
    // Szorzó: a WON selection-ök odds-ját "biztos"-nak vesszük (1.0),
    // az OPEN selection-ökhöz élő odds alapján korrigálunk
    $cashoutMultiplier = 1.0;
    $HOUSE_EDGE = 0.90; // 10% jutalék a platformnak

    foreach ($selections as $sel) {
        if ($sel['status'] === 'WON') {
            // Nyert tétel → teljes odds beszámítása
            $cashoutMultiplier *= (float)$sel['odds_at_pick'];
            continue;
        }

        // OPEN tétel → élő meccs állapota alapján korrigálunk
        $matchId = (int)$sel['match_id'];
        $eventId = $sel['event_id'] ? (int)$sel['event_id'] : null;
        $originalOdds = (float)$sel['odds_at_pick'];
        $pickLabel = strtolower(trim($sel['pick_label'] ?? ''));
        $marketName = strtolower(trim($sel['market_name'] ?? ''));

        // Élő meccs adatok az adatbázisból
        $liveData = getLiveMatchData($conn, $matchId, $eventId);

        if (!$liveData) {
            // Nem tudunk live adatot → eredeti odds 85%-a (bizonytalansági levonás)
            $cashoutMultiplier *= ($originalOdds * 0.85);
            continue;
        }

        $homeScore = (int)($liveData['home_score'] ?? 0);
        $awayScore = (int)($liveData['away_score'] ?? 0);
        $isLive = (bool)($liveData['is_live'] ?? false);
        $liveTime = $liveData['live_time'] ?? '';

        // Elapsed perc becslése
        $elapsedMinutes = parseElapsedMinutes($liveTime);

        if (!$isLive && $elapsedMinutes === 0) {
            // Meccs még nem kezdődött → eredeti odds × enyhe csökkentés
            $cashoutMultiplier *= ($originalOdds * 0.92);
            continue;
        }

        // ── KOCKÁZAT ÉRTÉKELÉS az élő állás alapján ──
        $adjustedOdds = calculateLiveAdjustedOdds(
            $originalOdds, $pickLabel, $marketName,
            $homeScore, $awayScore, $elapsedMinutes,
            $sel['home_team'], $sel['away_team']
        );

        $cashoutMultiplier *= $adjustedOdds;
    }

    // Végső cashout = tét × korrigált szorzó × jutalék
    $rawCashout = $stake * ($cashoutMultiplier / $originalTotalOdds) * $HOUSE_EDGE;

    // Minimum: 0, Maximum: potentialWin 95%-a
    $cashout = max(0, min($rawCashout, $potentialWin * 0.95));
    $cashout = round($cashout, 0); // Egész forintra kerekítve

    // Ha 0-ra jönne ki, nem érhető el
    if ($cashout <= 0) {
        return ['status' => 'ok', 'available' => false, 'cashout_amount' => 0, 'message' => 'Cash out jelenleg nem elérhető'];
    }

    return [
        'status' => 'ok',
        'available' => true,
        'cashout_amount' => $cashout,
        'potential_win' => $potentialWin,
        'stake' => $stake,
        'message' => 'Cash out elérhető'
    ];
}

/**
 * Élő meccsadatok lekérése az Events táblából
 */
function getLiveMatchData($conn, $matchApiId, $eventId = null) {
    if ($eventId) {
        $stmt = $conn->prepare("SELECT is_live, live_time, live_status, home_score, away_score, start_time, status_id, home_team_name, away_team_name FROM Events WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $eventId);
    } else {
        $stmt = $conn->prepare("SELECT is_live, live_time, live_status, home_score, away_score, start_time, status_id, home_team_name, away_team_name FROM Events WHERE api_id = ? LIMIT 1");
        $stmt->bind_param("i", $matchApiId);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

/**
 * Lejátszott percek becslése a live_time string alapján
 */
function parseElapsedMinutes($liveTime) {
    if (empty($liveTime)) return 0;
    $lt = strtolower(trim($liveTime));

    // "45+2'" vagy "67'" vagy "HT" stb.
    if (preg_match('/(\d+)/', $lt, $m)) {
        return (int)$m[1];
    }
    if (strpos($lt, 'ht') !== false || strpos($lt, 'half') !== false || strpos($lt, 'félidő') !== false) {
        return 45;
    }
    if (strpos($lt, 'ft') !== false || strpos($lt, 'vége') !== false || strpos($lt, 'ended') !== false) {
        return 90;
    }
    return 0;
}

/**
 * Élő korrigált odds számítás a meccs állapota alapján
 * 
 * A logika:
 * - 1X2 piac: ha a fogadott csapat vezet → odds nő (kedvezőbb cashout)
 *              ha az ellenfél vezet → odds csökken (kedvezőtlenebb cashout)
 * - Over/Under: gólkülönbség alapján
 * - Időtényező: minél több idő telt el, annál több információnk van
 */
function calculateLiveAdjustedOdds($originalOdds, $pick, $market, $homeScore, $awayScore, $elapsedMinutes, $homeTeam, $awayTeam) {
    $homeTeamLower = strtolower(trim($homeTeam ?? ''));
    $awayTeamLower = strtolower(trim($awayTeam ?? ''));

    // Idő-faktor: 0.0 (épp kezdődött) → 1.0 (90. perc)
    $timeFactor = min(1.0, max(0.0, $elapsedMinutes / 90.0));

    // ── 1X2 / MATCH WINNER ──
    if (is1x2Market($market)) {
        $betOnHome = ($pick === '1' || $pick === 'home' || $pick === $homeTeamLower);
        $betOnAway = ($pick === '2' || $pick === 'away' || $pick === $awayTeamLower);
        $betOnDraw = ($pick === 'x' || $pick === 'draw' || $pick === 'döntetlen');

        $goalDiff = $homeScore - $awayScore; // + = hazai vezet, - = vendég vezet

        if ($betOnHome) {
            if ($goalDiff > 0) {
                // Hazai vezet → jó irányba megy → odds emelkedik (könnyebb cashout)
                $boost = 1.0 + ($goalDiff * 0.25 * $timeFactor);
                return $originalOdds * min($boost, 1.8);
            } elseif ($goalDiff < 0) {
                // Vendég vezet → rossz irány → odds csökken
                $penalty = 1.0 - (abs($goalDiff) * 0.30 * (0.5 + $timeFactor * 0.5));
                return $originalOdds * max($penalty, 0.05);
            }
            // Döntetlen → enyhe csökkenés az idő előrehaladtával
            return $originalOdds * (1.0 - $timeFactor * 0.1);
        }

        if ($betOnAway) {
            if ($goalDiff < 0) {
                // Vendég vezet → jó irány
                $boost = 1.0 + (abs($goalDiff) * 0.25 * $timeFactor);
                return $originalOdds * min($boost, 1.8);
            } elseif ($goalDiff > 0) {
                // Hazai vezet → rossz irány
                $penalty = 1.0 - ($goalDiff * 0.30 * (0.5 + $timeFactor * 0.5));
                return $originalOdds * max($penalty, 0.05);
            }
            return $originalOdds * (1.0 - $timeFactor * 0.1);
        }

        if ($betOnDraw) {
            if ($homeScore === $awayScore) {
                // Döntetlen → jó irány (de idővel kevésbé valószínű)
                $boost = 1.0 + (0.15 * $timeFactor);
                return $originalOdds * min($boost, 1.5);
            } else {
                // Nem döntetlen → rossz irány
                $diff = abs($goalDiff);
                $penalty = 1.0 - ($diff * 0.35 * (0.5 + $timeFactor * 0.5));
                return $originalOdds * max($penalty, 0.05);
            }
        }
    }

    // ── OVER/UNDER ──
    if (isOverUnderMarket($market)) {
        $totalGoals = $homeScore + $awayScore;

        // Vonal kinyerése
        $line = 0;
        if (preg_match('/\((\d+\.?\d*)\)/', $market, $m)) {
            $line = (float)$m[1];
        }
        if ($line == 0 && preg_match('/(\d+[,.]?\d*)/', $pick, $m)) {
            $line = (float)str_replace(',', '.', $m[1]);
        }

        if ($line > 0) {
            $isOver = (strpos($pick, 'over') !== false || strpos($pick, 'több') !== false || strpos($pick, 'fölött') !== false || strpos($pick, 'felett') !== false);

            if ($isOver) {
                $diff = $totalGoals - $line;
                if ($diff > 0) {
                    // Már over → kedvező
                    return $originalOdds * min(1.0 + (0.3 * $timeFactor), 1.6);
                } else {
                    // Még under → kedvezőtlen (idő múlásával rosszabb)
                    $remaining = $line - $totalGoals;
                    $penalty = 1.0 - ($remaining * 0.15 * $timeFactor);
                    return $originalOdds * max($penalty, 0.1);
                }
            } else {
                // Under
                $diff = $line - $totalGoals;
                if ($diff > 0 && $timeFactor > 0.5) {
                    // Még under + sok idő eltelt → kedvező
                    return $originalOdds * min(1.0 + (0.2 * $timeFactor), 1.4);
                } elseif ($totalGoals >= $line) {
                    // Már over → elveszett
                    return $originalOdds * 0.05;
                }
                return $originalOdds * (1.0 - $timeFactor * 0.05);
            }
        }
    }

    // ── BOTH TEAMS TO SCORE ──
    if (isBttsMarket($market)) {
        $bothScored = ($homeScore > 0 && $awayScore > 0);
        $isYes = (strpos($pick, 'yes') !== false || strpos($pick, 'igen') !== false);

        if ($isYes) {
            if ($bothScored) {
                return $originalOdds * min(1.0 + (0.4 * $timeFactor), 1.7);
            }
            // Minél kevesebb idő van hátra és még nem lőtt mindkét csapat → rosszabb
            $penalty = 1.0 - ($timeFactor * 0.25);
            return $originalOdds * max($penalty, 0.2);
        } else {
            if ($bothScored) {
                return $originalOdds * 0.05; // Már lőtt mindkettő
            }
            return $originalOdds * (1.0 + ($timeFactor * 0.15));
        }
    }

    // ── ISMERETLEN PIAC → mérsékelt csökkentés ──
    return $originalOdds * (0.90 - $timeFactor * 0.05);
}

function is1x2Market($market) {
    return (strpos($market, '1x2') !== false || strpos($market, 'winner') !== false ||
            strpos($market, 'győztes') !== false || strpos($market, 'gyoztes') !== false ||
            strpos($market, 'match result') !== false || strpos($market, 'moneyline') !== false ||
            strpos($market, 'full time result') !== false);
}

function isOverUnderMarket($market) {
    return (strpos($market, 'over') !== false || strpos($market, 'under') !== false ||
            strpos($market, 'több') !== false || strpos($market, 'tobb') !== false ||
            strpos($market, 'kevesebb') !== false || strpos($market, 'total') !== false ||
            strpos($market, 'gólszám') !== false || strpos($market, 'golszam') !== false);
}

function isBttsMarket($market) {
    return (strpos($market, 'both teams') !== false || strpos($market, 'mindkét') !== false ||
            strpos($market, 'mindket') !== false || strpos($market, 'btts') !== false);
}
