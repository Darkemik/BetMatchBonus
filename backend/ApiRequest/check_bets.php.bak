<?php
/**
 * CHECK_BETS.PHP - Nyitott szelvények kiértékelése az adatbázisból
 * 
 * 1) Először az ADATBÁZIS Events táblájából próbálja kiolvasni az eredményt
 * 2) Ha ott nincs → API-ból próbálja lekérni
 * 3) Ha az API sem ad vissza adatot ÉS a meccs 2+ órája kezdődött → befejezettnek tekinti
 */

if (!isset($conn)) {
    require_once __DIR__ . "/connect.php";
}

/**
 * Nyitott ticketek kiértékelése a bejelentkezett felhasználónál
 */
function evaluateOpenTickets($conn, $userId) {
    // 1. Nyitott ticketek lekérése
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

    // 2. Minden nyitott ticket OPEN selectionjeit kiértékeljük
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

        // 3. Minden OPEN selection-höz lekérjük a meccs adatokat
        foreach ($selectionsToCheck as $sel) {
            $matchId = (int)$sel['match_id'];
            $eventId = $sel['event_id'] ? (int)$sel['event_id'] : null;
            if ($matchId <= 0 && !$eventId) continue;

            // === LÉPÉS 1: Adatbázisból próbáljuk kiolvasni ===
            $matchData = fetchMatchDataFromDB($conn, $matchId, $eventId);
            
            // === LÉPÉS 2: Ha az adatbázisban nincs elég adat → API ===
            if (!$matchData || !isMatchFinished($matchData)) {
                $apiData = fetchMatchDataFromAPI($matchId);
                if ($apiData && isset($apiData['id']) && $apiData['id'] > 0) {
                    $matchData = $apiData;
                }
            }

            // === LÉPÉS 3: Ha sem DB sem API nem ad eredményt → időalapú fallback ===
            if (!$matchData || !isMatchFinished($matchData)) {
                // Ha a meccs 2+ órája kezdődött és nem élő → befejezettnek tekintjük
                $dbEvent = getEventFromDB($conn, $matchId, $eventId);
                if ($dbEvent) {
                    $startTime = strtotime($dbEvent['start_time']);
                    $now = time();
                    $isLive = (int)($dbEvent['is_live'] ?? 0);
                    $hoursElapsed = ($now - $startTime) / 3600;
                    
                    if ($hoursElapsed >= 2 && !$isLive && $startTime > 0) {
                        // 2+ óra eltelt, nem élő → befejezett
                        // Ha van score az adatbázisban, használjuk azt
                        if ($dbEvent['home_score'] !== null && $dbEvent['away_score'] !== null) {
                            $matchData = [
                                'isLive' => false,
                                'liveStatus' => 'ended',
                                'score' => [(int)$dbEvent['home_score'], (int)$dbEvent['away_score']],
                                'homeTeam' => $dbEvent['home_team_name'],
                                'awayTeam' => $dbEvent['away_team_name'],
                                'isStarted' => true,
                                '_source' => 'time_fallback_with_score'
                            ];
                        } else {
                            // Nincs score → 0-0 fallbackként (jobb mint örökre OPEN maradjon)
                            $matchData = [
                                'isLive' => false,
                                'liveStatus' => 'ended',
                                'score' => [0, 0],
                                'homeTeam' => $dbEvent['home_team_name'],
                                'awayTeam' => $dbEvent['away_team_name'],
                                'isStarted' => true,
                                '_source' => 'time_fallback_no_score'
                            ];
                        }
                        
                        // Frissítsük az Events tábla live_status-át és status_id-ját is
                        $stmtFixStatus = $conn->prepare("UPDATE Events SET is_live = 0, live_status = 'Ended', status_id = 3 WHERE api_id = ? AND is_live = 1");
                        $stmtFixStatus->bind_param("i", $matchId);
                        $stmtFixStatus->execute();
                        $stmtFixStatus->close();
                    }
                }
            }

            if (!$matchData) continue;

            $isFinished = isMatchFinished($matchData);
            if (!$isFinished) continue;

            // Meccs véget ért - eredmény kiolvasása
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

            // Selection státusz frissítése
            $stmtUpdate = $conn->prepare("UPDATE TicketSelections SET status = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $newStatus, $sel['id']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        // 4. Ticket összesített státusz újraszámolása
        updateTicketStatus($conn, $ticketId, $userId);
    }
}

/**
 * Meccs adatok lekérése az ADATBÁZIS Events táblájából
 */
function fetchMatchDataFromDB($conn, $matchApiId, $eventId = null) {
    $event = getEventFromDB($conn, $matchApiId, $eventId);
    if (!$event) return null;

    $isLive = (int)($event['is_live'] ?? 0);
    $liveStatus = $event['live_status'] ?? '';
    $homeScore = $event['home_score'];
    $awayScore = $event['away_score'];

    return [
        'id' => (int)$event['api_id'],
        'homeTeam' => $event['home_team_name'],
        'awayTeam' => $event['away_team_name'],
        'isLive' => (bool)$isLive,
        'liveStatus' => $liveStatus,
        'liveTime' => $event['live_time'] ?? '',
        'score' => ($homeScore !== null && $awayScore !== null) ? [(int)$homeScore, (int)$awayScore] : [],
        'isStarted' => (strtotime($event['start_time']) <= time()),
        'startTime' => $event['start_time'],
        '_source' => 'database'
    ];
}

/**
 * Nyers Events sor lekérése az adatbázisból
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
 * AZ ÖSSZES USER ÖSSZES NYITOTT TICKETJÉT kiértékeli.
 * Cron jobból vagy admin dashboardról hívható.
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
 * Ticket státusz frissítése a selectionjei alapján
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

    // Csak akkor frissítünk ha változott
    $stmtCheck = $conn->prepare("SELECT status, stake, potential_win FROM Tickets WHERE id = ?");
    $stmtCheck->bind_param("i", $ticketId);
    $stmtCheck->execute();
    $ticketRow = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$ticketRow || $ticketRow['status'] === $newStatus) return;

    $stmtUpd = $conn->prepare("UPDATE Tickets SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpd->bind_param("si", $newStatus, $ticketId);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Ha NYERTES → nyeremény jóváírása a wallet-be
    if ($newStatus === 'WON') {
        $potentialWin = (float)$ticketRow['potential_win'];

        $stmtWallet = $conn->prepare("UPDATE Wallets SET balance = balance + ? WHERE user_id = ?");
        $stmtWallet->bind_param("di", $potentialWin, $userId);
        $stmtWallet->execute();
        $stmtWallet->close();

        // Users.balance frissítése (UserProfile rendszer)
        $stmtUserBal = $conn->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
        $stmtUserBal->bind_param("di", $potentialWin, $userId);
        $stmtUserBal->execute();
        $stmtUserBal->close();

        // Wallet tranzakció rögzítése (type_id = 4 = WIN)
        $stmtTx = $conn->prepare("
            INSERT INTO WalletTransactions (wallet_id, amount, type_id, related_type, related_id, created_at)
            SELECT id, ?, 4, 'Ticket', ?, NOW() FROM Wallets WHERE user_id = ?
        ");
        $stmtTx->bind_param("dii", $potentialWin, $ticketId, $userId);
        $stmtTx->execute();
        $stmtTx->close();
    }
}

/**
 * Meccs adatok lekérése az API-ból (fallback)
 */
function fetchMatchDataFromAPI($matchId) {
    $apiBaseUrl = "http://localhost:5000/api";
    
    // Próba 1: /matches/event?eventId=
    $url = "$apiBaseUrl/matches/event?eventId=$matchId";
    $data = curlGetJson($url);
    if ($data && isset($data['id']) && $data['id'] > 0) return $data;

    // Próba 2: /matches/{id}
    $url = "$apiBaseUrl/matches/$matchId";
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
 * Ellenőrzi, hogy a meccs véget ért-e
 */
function isMatchFinished($matchData) {
    $isLive = !empty($matchData['isLive']);

    // 1) liveStatus mező ellenőrzése
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

    // 2) Ha nincs élőben ÉS van eredmény ÉS a meccs már elkezdődött → vége
    $score = $matchData['score'] ?? [];
    $hasScore = is_array($score) && count($score) >= 2;
    $hasNonZeroScore = $hasScore && ($score[0] > 0 || $score[1] > 0);
    $isStarted = !empty($matchData['isStarted']);
    
    if (!$isLive && $hasNonZeroScore && $isStarted) {
        return true;
    }

    // 3) Ha isStarted=true és isLive=false és a kezdés óta 2+ óra eltelt → vége
    if (!$isLive && $isStarted) {
        $startTime = $matchData['startTime'] ?? null;
        if ($startTime) {
            $startTs = strtotime($startTime);
            $hoursElapsed = (time() - $startTs) / 3600;
            if ($hoursElapsed >= 2) {
                return true;
            }
        }
    }

    // 4) liveTime tartalmaz befejezést jelző szöveget
    $liveTime = $matchData['liveTime'] ?? '';
    $liveTimeLower = strtolower(trim($liveTime));
    if ($liveTimeLower !== '' && (
        strpos($liveTimeLower, 'ended') !== false || 
        strpos($liveTimeLower, 'ft') !== false ||
        strpos($liveTimeLower, 'vége') !== false ||
        strpos($liveTimeLower, 'final') !== false)) {
        return true;
    }

    // 5) _source = time_fallback → biztosan befejezett
    if (isset($matchData['_source']) && strpos($matchData['_source'], 'time_fallback') !== false) {
        return true;
    }

    return false;
}

/**
 * Ellenőrzi, hogy egy adott fogadási pick nyert-e az eredmény alapján
 */
function checkIfPickWon($pick, $market, $homeScore, $awayScore, $homeTeam, $awayTeam) {
    $pickLower = strtolower(trim($pick));
    $marketLower = strtolower(trim($market));
    $homeTeamLower = strtolower(trim($homeTeam));
    $awayTeamLower = strtolower(trim($awayTeam));

    // ===== 1X2 / Match Winner =====
    if (strpos($marketLower, '1x2') !== false || 
        strpos($marketLower, 'winner') !== false || 
        strpos($marketLower, 'győztes') !== false ||
        strpos($marketLower, 'match result') !== false ||
        strpos($marketLower, 'full time result') !== false ||
        strpos($marketLower, 'moneyline') !== false) {
        
        if ($pickLower === '1' || $pickLower === 'home' || $pickLower === $homeTeamLower) {
            return $homeScore > $awayScore;
        }
        if ($pickLower === '2' || $pickLower === 'away' || $pickLower === $awayTeamLower) {
            return $awayScore > $homeScore;
        }
        if ($pickLower === 'x' || $pickLower === 'draw' || $pickLower === 'döntetlen') {
            return $homeScore === $awayScore;
        }
    }

    // ===== Over/Under =====
    if (strpos($marketLower, 'over') !== false || strpos($marketLower, 'under') !== false ||
        strpos($marketLower, 'több') !== false || strpos($marketLower, 'kevesebb') !== false ||
        strpos($marketLower, 'total') !== false || strpos($marketLower, 'gólszám') !== false ||
        strpos($marketLower, 'gólok száma') !== false) {
        
        $totalGoals = $homeScore + $awayScore;
        
        // Keressük a vonalat a piac nevéből: "(2.5)" vagy "(4.5)" stb.
        preg_match('/\((\d+\.?\d*)\)/', $market, $matches);
        $line = isset($matches[1]) ? floatval($matches[1]) : 0;
        
        // Ha a pick tartalmazza a számot: "5,5 alatt" → line = 5.5
        if ($line == 0) {
            preg_match('/(\d+[,.]?\d*)/', $pick, $pickMatches);
            if (isset($pickMatches[1])) {
                $line = floatval(str_replace(',', '.', $pickMatches[1]));
            }
        }
        
        if ($line > 0) {
            if (strpos($pickLower, 'over') !== false || strpos($pickLower, 'több') !== false || 
                strpos($pickLower, 'fölött') !== false || strpos($pickLower, 'felett') !== false) {
                return $totalGoals > $line;
            }
            if (strpos($pickLower, 'under') !== false || strpos($pickLower, 'kevesebb') !== false || 
                strpos($pickLower, 'alatt') !== false) {
                return $totalGoals < $line;
            }
        }
    }

    // ===== Both Teams To Score =====
    if (strpos($marketLower, 'both teams') !== false || strpos($marketLower, 'mindkét') !== false ||
        strpos($marketLower, 'btts') !== false) {
        $bothScored = ($homeScore > 0 && $awayScore > 0);
        if ($pickLower === 'yes' || $pickLower === 'igen') return $bothScored;
        if ($pickLower === 'no' || $pickLower === 'nem') return !$bothScored;
    }

    // ===== Double Chance / Kétesély =====
    if (strpos($marketLower, 'double chance') !== false || strpos($marketLower, 'dupla') !== false ||
        strpos($marketLower, 'kétesély') !== false) {
        // "Döntetlen vagy Kingston City" → X2
        // "Nunawading City vagy Döntetlen" → 1X
        // "Nunawading City vagy Kingston City" → 12
        if ($pickLower === '1x' || $pickLower === 'home or draw') return $homeScore >= $awayScore;
        if ($pickLower === 'x2' || $pickLower === 'draw or away') return $awayScore >= $homeScore;
        if ($pickLower === '12' || $pickLower === 'home or away') return $homeScore !== $awayScore;
        
        // Magyar szöveges formátum: "Döntetlen vagy [csapat]" / "[csapat] vagy Döntetlen"
        $hasDraw = strpos($pickLower, 'döntetlen') !== false || strpos($pickLower, 'draw') !== false;
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
        strpos($marketLower, 'páros') !== false || strpos($marketLower, 'páratlan') !== false) {
        $total = $homeScore + $awayScore;
        if ($pickLower === 'odd' || $pickLower === 'páratlan') return ($total % 2) !== 0;
        if ($pickLower === 'even' || $pickLower === 'páros') return ($total % 2) === 0;
    }

    // ===== Csapatnév alapú match (1X2 piacon kívül is) =====
    if ($pickLower === $homeTeamLower && !empty($homeTeamLower)) {
        return $homeScore > $awayScore;
    }
    if ($pickLower === $awayTeamLower && !empty($awayTeamLower)) {
        return $awayScore > $homeScore;
    }

    // Ha nem tudtuk megállapítani → vesztes
    return false;
}

// Ha közvetlenül hívják (nem include), futtassuk a bejelentkezett user ticketjeit
// VAGY ?action=evaluate_all → az ÖSSZES user ÖSSZES nyitott ticketjét kiértékeli (cron/admin)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'evaluate_all') {
        // Cron job / admin: összes user kiértékelése
        header('Content-Type: application/json; charset=utf-8');
        $startTime = microtime(true);
        $evaluatedUsers = evaluateAllOpenTickets($conn);
        $elapsed = round((microtime(true) - $startTime) * 1000);
        echo json_encode([
            'status' => 'ok',
            'message' => "Kiértékelés kész: $evaluatedUsers user szelvényei ellenőrizve.",
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
        echo json_encode(['status' => 'ok', 'message' => 'Kiértékelés kész']);
    }
}