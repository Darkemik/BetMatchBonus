<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input)) {
    echo json_encode(['status' => 'ok', 'bets' => []]);
    exit;
}

$results = [];

foreach ($input as $bet) {
    $betId = $bet['id'] ?? 0;
    $items = $bet['items'] ?? [];
    $currentStatus = $bet['status'] ?? 'pending';

    // Ha már végleges, nem ellenőrizzük újra
    if ($currentStatus === 'won' || $currentStatus === 'lost') {
        $results[] = [
            'id' => $betId,
            'status' => $currentStatus,
            'items' => $items
        ];
        continue;
    }

    $allFinished = true;      // Kezdetben feltételezzük hogy minden eldőlt
    $allPending = true;       // Nyomon követjük ha van-e bármilyen nem-pending item
    $anyLost = false;
    $updatedItems = [];

    foreach ($items as $item) {
        $matchId = $item['matchId'] ?? 0;
        $pick = $item['pick'] ?? '';
        $market = $item['market'] ?? '';
        $itemStatus = $item['status'] ?? 'pending';

        // Ha ez a tétel már eldőlt
        if ($itemStatus === 'won' || $itemStatus === 'lost') {
            $allPending = false;
            if ($itemStatus === 'lost') $anyLost = true;
            $updatedItems[] = $item;
            continue;
        }

        // Ez a tétel még "pending"
        if ($matchId <= 0) {
            $allFinished = false;
            $updatedItems[] = $item;
            continue;
        }

        // API-ból lekérjük a meccs adatait
        $url = "http://localhost:5000/api/matches/" . intval($matchId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $allFinished = false;
            $updatedItems[] = $item;
            continue;
        }

        $matchData = json_decode($response, true);
        if (!is_array($matchData)) {
            $allFinished = false;
            $updatedItems[] = $item;
            continue;
        }

        $isLive = !empty($matchData['isLive']);
        $isFinished = false;

        // --- Meccs vége detektálás ---
        // 1) liveStatus mező ellenőrzése (ha van)
        $liveStatus = $matchData['liveStatus'] ?? $matchData['status'] ?? '';
        $liveStatusLower = strtolower(trim($liveStatus));
        $finishedStatuses = ['ended', 'finished', 'final', 'ft', 'aet', 'ap', 'closed', 
                             'retired', 'walkover', 'cancelled', 'abandoned', 'after penalties',
                             'after extra time', 'full-time', 'result'];
        
        foreach ($finishedStatuses as $fs) {
            if (strpos($liveStatusLower, $fs) !== false) {
                $isFinished = true;
                break;
            }
        }

        // 2) Ha nincs élőben ÉS van eredmény (score tömb legalább 2 elemű) → vége
        $score = $matchData['score'] ?? [];
        $hasScore = is_array($score) && count($score) >= 2 && ($score[0] > 0 || $score[1] > 0);
        
        if (!$isLive && $hasScore) {
            $isFinished = true;
        }

        // 3) Ha a meccs isStarted=true és isLive=false → valószínűleg vége
        if (!$isLive && !empty($matchData['isStarted'])) {
            $isFinished = true;
        }

        // 4) Ha a liveTime tartalmaz "Ended" / "FT" / "Vége" szöveget
        $liveTime = $matchData['liveTime'] ?? '';
        $liveTimeLower = strtolower(trim($liveTime));
        if (strpos($liveTimeLower, 'ended') !== false || 
            strpos($liveTimeLower, 'ft') !== false ||
            strpos($liveTimeLower, 'vége') !== false ||
            strpos($liveTimeLower, 'final') !== false) {
            $isFinished = true;
        }

        if (!$isFinished) {
            $allFinished = false;
            $updatedItems[] = $item;
            continue;
        }

        // Meccs véget ért - eredmény kiolvasása
        $homeScore = isset($score[0]) ? (int)$score[0] : 0;
        $awayScore = isset($score[1]) ? (int)$score[1] : 0;

        $homeTeam = $matchData['homeTeam'] ?? '';
        $awayTeam = $matchData['awayTeam'] ?? '';
        
        // Ha nincs homeTeam/awayTeam az API-ban, a name-ből kinyerjük
        if (empty($homeTeam) && !empty($matchData['name'])) {
            $parts = explode(' - ', $matchData['name'], 2);
            $homeTeam = trim($parts[0] ?? '');
            $awayTeam = trim($parts[1] ?? '');
        }

        $betWon = checkIfPickWon($pick, $market, $homeScore, $awayScore, $homeTeam, $awayTeam);

        $item['status'] = $betWon ? 'won' : 'lost';
        $item['finalScore'] = $homeScore . ' - ' . $awayScore;
        if (!$betWon) $anyLost = true;
        $allPending = false;
        $updatedItems[] = $item;
    }

    // Szelvény státusz meghatározása
    $betStatus = 'pending';
    if ($allPending) {
        // Nincs egy sem eldőlt
        $betStatus = 'pending';
    } elseif ($anyLost) {
        // Legalább egy vesztes
        $betStatus = 'lost';
    } elseif ($allFinished) {
        // Minden eldőlt ÉS nincs vesztes
        $betStatus = 'won';
    } else {
        // Van eldőlt (és nincs vesztes) DE van még pending
        $betStatus = 'pending';
    }

    $results[] = [
        'id' => $betId,
        'status' => $betStatus,
        'items' => $updatedItems
    ];
}

echo json_encode(['status' => 'ok', 'bets' => $results]);

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
        strpos($marketLower, 'total') !== false) {
        
        $totalGoals = $homeScore + $awayScore;
        
        preg_match('/\((\d+\.?\d*)\)/', $market, $matches);
        $line = isset($matches[1]) ? floatval($matches[1]) : 0;
        
        if ($line > 0) {
            if ($pickLower === 'over' || strpos($pickLower, 'több') !== false || strpos($pickLower, 'over') !== false) {
                return $totalGoals > $line;
            }
            if ($pickLower === 'under' || strpos($pickLower, 'kevesebb') !== false || strpos($pickLower, 'under') !== false) {
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

    // ===== Double Chance =====
    if (strpos($marketLower, 'double chance') !== false || strpos($marketLower, 'dupla') !== false) {
        if ($pickLower === '1x' || $pickLower === 'home or draw') return $homeScore >= $awayScore;
        if ($pickLower === 'x2' || $pickLower === 'draw or away') return $awayScore >= $homeScore;
        if ($pickLower === '12' || $pickLower === 'home or away') return $homeScore !== $awayScore;
    }

    // ===== Handicap =====
    if (strpos($marketLower, 'handicap') !== false) {
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

    // ===== Csapatnév alapú match (ha a pick a csapat neve) =====
    // Ez sok API-nál előfordul: a pick = csapat neve, a market = "Winner" stb.
    if ($pickLower === $homeTeamLower && !empty($homeTeamLower)) {
        return $homeScore > $awayScore;
    }
    if ($pickLower === $awayTeamLower && !empty($awayTeamLower)) {
        return $awayScore > $homeScore;
    }

    // Ha nem tudtuk megállapítani → vesztes (nem hagyjuk függőben örökre)
    // Megjegyzés: ha a meccsnek TÉNYLEG vége van de nem tudjuk kiértékelni,
    // jobb ha vesztesnek jelöljük mint ha örökké függőben marad
    return false;
}