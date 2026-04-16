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

        $ticketSelectionBoostColStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'TicketSelections' AND COLUMN_NAME = 'is_boosted'");
        $ticketSelectionBoostColStmt->execute();
        $ticketSelectionBoostColRes = $ticketSelectionBoostColStmt->get_result()->fetch_assoc();
        $ticketSelectionBoostColStmt->close();
        $hasTicketSelectionIsBoosted = $ticketSelectionBoostColRes && (int)$ticketSelectionBoostColRes['cnt'] > 0;

    // Selections lekérése
        $selectionFields = "ts.id, ts.match_id, ts.event_id, ts.pick_label, ts.market_name, ts.odds_at_pick, ts.status, ts.home_team, ts.away_team";
        if ($hasTicketSelectionIsBoosted) {
            $selectionFields = "ts.id, ts.match_id, ts.event_id, ts.pick_label, ts.market_name, ts.odds_at_pick, ts.is_boosted, ts.status, ts.home_team, ts.away_team";
        }
        $stmtSel = $conn->prepare("\n        SELECT {$selectionFields}\n        FROM TicketSelections ts\n        WHERE ts.ticket_id = ?\n    ");
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
    if ($hasTicketSelectionIsBoosted) {
        foreach ($selections as $sel) {
            if (!empty($sel['is_boosted'])) {
                return ['status' => 'ok', 'available' => false, 'message' => 'Oddsűrhajó tételt tartalmazó szelvény nem cashoutolható'];
            }
        }
    }

    // Minden selection WON → teljes kifizetés (max potential win * alpha)
    $allWon = true;
    $wonCount = 0;
    $openCount = 0;
    foreach ($selections as $sel) {
        if ($sel['status'] === 'WON') {
            $wonCount++;
        } elseif ($sel['status'] === 'OPEN') {
            $openCount++;
            $allWon = false;
        } else {
            $allWon = false;
        }
    }

    // ── CASHOUT KALKULÁCIÓ ──
    // Biztonságos, potenciális nyeremény alapú modell:
    //   1) Abszolút maximum = potential_win * 0.92
    //   2) Exponenciális görbe a nyert selectionök arányára
    //   3) Live odds csak mérsékelt módosító (főleg lefelé), hogy ne lehessen korán túl sokat kivenni
    $ALPHA = 0.92;
    $maxCashout = round($potentialWin * $ALPHA, 0);

    $totalSelections = count($selections);
    $settlementProgress = $totalSelections > 0 ? ($wonCount / $totalSelections) : 0.0;

    $avgOpenRisk = 1.0;
    $openRiskCount = 0;
    $impliedCurrentOdds = 1.0;
    $selectionUpdates = [];

    foreach ($selections as $sel) {
        $origOdds = (float)$sel['odds_at_pick'];
        $selectionUpdate = [
            'match_id' => isset($sel['match_id']) ? (int)$sel['match_id'] : null,
            'event_id' => isset($sel['event_id']) ? (int)$sel['event_id'] : null,
            'pick_label' => (string)($sel['pick_label'] ?? ''),
            'market_name' => (string)($sel['market_name'] ?? ''),
            'status' => (string)($sel['status'] ?? 'OPEN'),
            'odds_at_pick' => $origOdds,
            'live_odds' => null,
            'trend' => 'neutral'
        ];

        if ($sel['status'] === 'WON') {
            $selectionUpdate['trend'] = 'won';
            $impliedCurrentOdds *= 1.0;
        } elseif ($sel['status'] === 'OPEN') {
            // Live odds lekérése
            $matchApiId = (int)($sel['match_id'] ?? 0);
            if ($matchApiId <= 0 && !empty($sel['event_id'])) {
                $matchApiId = (int)$sel['event_id'];
            }
            if ($matchApiId <= 0) {
                $matchApiId = resolveMatchApiIdByTeams($conn, $sel['home_team'] ?? '', $sel['away_team'] ?? '');
            }
            $pickLabel  = $sel['pick_label'] ?? '';
            $marketName = $sel['market_name'] ?? '';

            $liveOdds = fetchLiveOddsForSelection($conn, $matchApiId, $marketName, $pickLabel);

            if ($liveOdds !== null && $liveOdds > 0) {
                $selectionUpdate['live_odds'] = (float)$liveOdds;
                // Open selection kockázati score: romló odds csökkent, javuló max 1.0-ig megy.
                $riskScore = min(1.0, $origOdds / $liveOdds);
                $avgOpenRisk += $riskScore;
                $openRiskCount++;
                $impliedCurrentOdds *= $liveOdds;

                if ($liveOdds < $origOdds) {
                    $selectionUpdate['trend'] = 'up';
                } elseif ($liveOdds > $origOdds) {
                    $selectionUpdate['trend'] = 'down';
                } else {
                    $selectionUpdate['trend'] = 'flat';
                }
            } else {
                // Nincs live adat: fallback az eredeti oddsszal, enyhén konzervatív score
                $avgOpenRisk += 0.95;
                $openRiskCount++;
                $impliedCurrentOdds *= max(1.01, $origOdds);
            }
        } elseif ($sel['status'] === 'LOST') {
            $selectionUpdate['trend'] = 'lost';
        }

        $selectionUpdates[] = $selectionUpdate;
    }

    if ($openRiskCount > 0) {
        $avgOpenRisk = $avgOpenRisk / (1.0 + $openRiskCount);
    }

    // Fair érték a jelenlegi implied odds alapján.
    // Ha az oddsok javulnak (csökkennek), fair érték nő; ha romlanak, csökken.
    $safeImpliedOdds = max(1.0001, $impliedCurrentOdds);
    $fairValue = ($potentialWin / $safeImpliedOdds) * $ALPHA;

    // Konzervatív kockázati faktor (0.82 .. 1.00)
    $riskModifier = 0.82 + (0.18 * max(0.0, min(1.0, $avgOpenRisk)));
    $conservativeValue = $fairValue * $riskModifier;

    // Exponenciális, de stabil görbe: tiny értékeknél nem omlik össze, nagy értékeknél óvatosan gyorsul.
    $linearProgress = max(0.0, min(1.0, $conservativeValue / max(1.0, $maxCashout)));
    $lambda = 4.5;
    $expProgress = (exp($lambda * $linearProgress) - 1.0) / (exp($lambda) - 1.0);

    // A lineáris komponens miatt nem esik irreálisan alacsonyra, az exponenciális miatt feljebb gyorsul a görbe.
    $cashoutFraction = (0.72 * $linearProgress) + (0.28 * $expProgress);

    // Settlement boost: több lezárt nyerő lábnál emelkedhet, de csak kontrolláltan.
    if ($settlementProgress > 0) {
        $cashoutFraction *= (1.0 + (0.22 * $settlementProgress));
    }

    // Korai fázisban is legyen értelmes minimum (kb. tét*alpha környéke), de szigorúan plafon alatt.
    $baselineFloorFraction = max(0.0, min(0.20, ($stake * $ALPHA) / max(1.0, $maxCashout)));
    $cashoutFraction = max($cashoutFraction, $baselineFloorFraction);

    if ($allWon) {
        $cashoutFraction = 1.0;
    }

    $cashoutFraction = max(0.0, min(1.0, $cashoutFraction));
    $cashout = round($maxCashout * $cashoutFraction, 0);
    $cashout = min($cashout, $maxCashout);

    return [
        'status' => 'ok',
        'available' => true,
        'cashout_amount' => $cashout,
        'potential_win' => $potentialWin,
        'max_cashout' => $maxCashout,
        'settlement_progress' => $settlementProgress,
        'selection_updates' => $selectionUpdates,
        'updated_at' => gmdate('c'),
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
        static $apiDataCache = [];
        if (!array_key_exists($matchApiId, $apiDataCache)) {
            $apiDataCache[$matchApiId] = apiGet(EP_MATCH_DETAILS . '/' . $matchApiId);
        }
        $apiData = $apiDataCache[$matchApiId];

        if (!isset($apiData['markets']) || !is_array($apiData['markets'])) {
            return null;
        }

        $targetMarketNorm = normalizeCashoutText($marketName);
        $targetPickNorm = normalizeCashoutText($pickLabel);
        $targetLine = extractCashoutLineValue($pickLabel);
        if ($targetLine === null) {
            $targetLine = extractCashoutLineValue($marketName);
        }
        $targetDirection = extractOverUnderDirection($pickLabel . ' ' . $marketName);

        $bestOdd = null;
        $bestScore = -INF;

        foreach ($apiData['markets'] as $market) {
            $marketNameRaw = (string)($market['name'] ?? '');
            $marketNorm = normalizeCashoutText($marketNameRaw);

            $marketSimilarity = marketNameSimilarityScore($targetMarketNorm, $marketNorm);
            if (!marketTypeMatches($targetMarketNorm, $marketNorm) && $marketSimilarity < 0.35) {
                continue;
            }

            $marketLine = extractCashoutLineValue($marketNameRaw);

            foreach ($market['selections'] ?? [] as $selection) {
                $selectionNameRaw = (string)($selection['name'] ?? '');
                $selectionNorm = normalizeCashoutText($selectionNameRaw);
                $odd = (float)($selection['odd'] ?? 0);
                if ($odd <= 0) continue;

                $score = 0.0;

                if ($selectionNorm === $targetPickNorm) {
                    $score += 10.0;
                } elseif ($targetPickNorm !== '' && (strpos($selectionNorm, $targetPickNorm) !== false || strpos($targetPickNorm, $selectionNorm) !== false)) {
                    $score += 6.0;
                }

                $score += max(0.0, $marketSimilarity * 3.0);

                $selDir = extractOverUnderDirection($selectionNameRaw . ' ' . $marketNameRaw);
                if ($targetDirection !== null && $selDir !== null && $targetDirection === $selDir) {
                    $score += 3.0;
                }

                $selLine = extractCashoutLineValue($selectionNameRaw);
                if ($selLine === null) {
                    $selLine = $marketLine;
                }
                if ($targetLine !== null && $selLine !== null) {
                    $diff = abs($targetLine - $selLine);
                    if ($diff <= 0.11) {
                        $score += 6.0;
                    } elseif ($diff <= 0.51) {
                        $score += 4.0;
                    } elseif ($diff <= 1.01) {
                        $score += 2.0;
                    }
                }

                if (containsAnyKeyword($targetPickNorm, ['handicap', 'hendikep']) && containsAnyKeyword($selectionNorm . ' ' . $marketNorm, ['handicap', 'hendikep'])) {
                    $score += 1.5;
                }

                if (containsAnyKeyword($targetMarketNorm, ['exact score', 'pontos eredmeny']) && containsAnyKeyword($marketNorm, ['exact score', 'pontos eredmeny'])) {
                    $score += 2.0;
                }

                if (containsAnyKeyword($targetMarketNorm, ['odd even', 'paratlan', 'paros']) && containsAnyKeyword($marketNorm, ['odd even', 'paratlan', 'paros'])) {
                    $score += 2.0;
                }

                if (containsAnyKeyword($targetMarketNorm, ['map', 'terkep', 'kor', 'round', 'pisztolykor', 'goal', 'gol']) && containsAnyKeyword($marketNorm, ['map', 'terkep', 'kor', 'round', 'pisztolykor', 'goal', 'gol'])) {
                    $score += 1.2;
                }

                $targetTokens = preg_split('/\s+/u', $targetPickNorm);
                foreach ($targetTokens as $token) {
                    if (mb_strlen($token, 'UTF-8') >= 4 && strpos($selectionNorm, $token) !== false) {
                        $score += 0.5;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestOdd = $odd;
                }
            }
        }

        // 3+ pont már elég erős egyezés a lokalizált/nevezéktani eltérésekhez.
        if ($bestOdd !== null && $bestScore >= 3.0) {
            return (float)$bestOdd;
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
        ['1x2', 'winner', 'gyoztes', 'match result', 'moneyline'],
        ['over', 'under', 'tobb', 'kevesebb', 'total', 'pontok szama', 'golszam', 'goals'],
        ['handicap', 'hendikep', 'spread', 'asian'],
        ['team total', 'csapat pontok', 'pontok szama'],
        ['exact score', 'pontos eredmeny', 'correct score'],
        ['odd even', 'paratlan', 'paros', 'odd/even'],
        ['map winner', 'terkep gyoztese', 'map', 'terkep'],
        ['round winner', 'kor gyoztese', 'round', 'kor', 'pisztolykor'],
        ['goals', 'gol', 'goal'],
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

function normalizeCashoutText($text) {
    $text = mb_strtolower((string)$text, 'UTF-8');
    $text = str_replace([',', ';', ':', '(', ')', '[', ']', '{', '}', '"', "'", '+'], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', trim($text));

    $map = [
        'ő' => 'o', 'ű' => 'u', 'ö' => 'o', 'ü' => 'u', 'ó' => 'o', 'ú' => 'u',
        'á' => 'a', 'é' => 'e', 'í' => 'i'
    ];
    $text = strtr($text, $map);

    return $text;
}

function extractCashoutLineValue($text) {
    $text = (string)$text;
    if (preg_match('/([-+]?\d+(?:[\.,]\d+)?)/u', $text, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return null;
}

function extractOverUnderDirection($text) {
    $norm = normalizeCashoutText($text);

    $overKeys = [' over ', ' felett ', ' tobb ', ' tobb mint ', ' pontosan folott ', ' + '];
    $underKeys = [' under ', ' alatt ', ' kevesebb ', ' kevesebb mint ', ' pontosan alatt ', ' - '];

    foreach ($overKeys as $k) {
        if (strpos(' ' . $norm . ' ', $k) !== false) return 'over';
    }
    foreach ($underKeys as $k) {
        if (strpos(' ' . $norm . ' ', $k) !== false) return 'under';
    }

    return null;
}

function containsAnyKeyword($text, $keywords) {
    foreach ($keywords as $keyword) {
        if (strpos($text, normalizeCashoutText($keyword)) !== false) {
            return true;
        }
    }
    return false;
}

function marketNameSimilarityScore($a, $b) {
    $aNorm = normalizeCashoutText($a);
    $bNorm = normalizeCashoutText($b);

    if ($aNorm === '' || $bNorm === '') return 0.0;
    if ($aNorm === $bNorm) return 1.0;

    $aTokens = array_values(array_filter(explode(' ', $aNorm), fn($t) => mb_strlen($t, 'UTF-8') >= 3));
    $bTokens = array_values(array_filter(explode(' ', $bNorm), fn($t) => mb_strlen($t, 'UTF-8') >= 3));

    if (empty($aTokens) || empty($bTokens)) return 0.0;

    $aSet = array_unique($aTokens);
    $bSet = array_unique($bTokens);
    $inter = array_intersect($aSet, $bSet);
    $union = array_unique(array_merge($aSet, $bSet));

    if (count($union) === 0) return 0.0;
    return count($inter) / count($union);
}

function resolveMatchApiIdByTeams($conn, $homeTeam, $awayTeam) {
    static $cache = [];

    $homeKey = normalizeCashoutText((string)$homeTeam);
    $awayKey = normalizeCashoutText((string)$awayTeam);
    $cacheKey = $homeKey . '||' . $awayKey;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if ($homeKey === '' || $awayKey === '') {
        $cache[$cacheKey] = 0;
        return 0;
    }

    $stmt = $conn->prepare("\n        SELECT api_id\n        FROM Events\n        WHERE api_id IS NOT NULL\n          AND (\n            (LOWER(TRIM(home_team_name)) = ? AND LOWER(TRIM(away_team_name)) = ?)\n            OR (LOWER(TRIM(home_team_name)) = ? AND LOWER(TRIM(away_team_name)) = ?)\n            OR LOWER(name) LIKE ?\n          )\n        ORDER BY is_live DESC, start_time DESC\n        LIMIT 1\n    ");

    $nameLike = '%' . $homeKey . '%vs%' . $awayKey . '%';
    $stmt->bind_param('sssss', $homeKey, $awayKey, $awayKey, $homeKey, $nameLike);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $resolved = $row ? (int)$row['api_id'] : 0;
    $cache[$cacheKey] = $resolved;
    return $resolved;
}
