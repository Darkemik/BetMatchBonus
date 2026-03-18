<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

$apiBaseUrl = "http://localhost:5000/api";

// 1) Sportok lekérése
$sportsUrl = "$apiBaseUrl/sports";
$ch = curl_init($sportsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$sportsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$sportsResponse) {
    echo json_encode(['error' => 'Sportok lekérése sikertelen', 'sports' => []]);
    exit;
}

$sports = json_decode($sportsResponse, true);
if (!is_array($sports)) {
    echo json_encode(['error' => 'Sportok parse hiba', 'sports' => []]);
    exit;
}

$result = ['sports' => []];

// 2) Minden sport esetén élő meccsek lekérése
foreach ($sports as $sport) {
    $sportId = $sport['id'] ?? 0;
    $sportName = $sport['name'] ?? 'Ismeretlen';
    $hasLive = $sport['hasLiveEvents'] ?? false;

    if (!$hasLive) {
        $result['sports'][$sportId] = 0;
        continue;
    }

    // Élő meccsek az adott sporthoz
    $liveUrl = "$apiBaseUrl/matches/live?sportId=$sportId";
    $ch = curl_init($liveUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $liveResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $liveResponse) {
        $liveMatches = json_decode($liveResponse, true);
        $liveCount = is_array($liveMatches) ? count($liveMatches) : 0;
        $result['sports'][$sportId] = $liveCount;

        // Élő meccsek importálása az adatbázisba
        if ($liveCount > 0) {
            importMatches($conn, $liveMatches, $sportId);
        }
    } else {
        $result['sports'][$sportId] = 0;
    }
}

echo json_encode($result);

/**
 * Meccsek importálása az adatbázisba
 */
function importMatches($conn, $matches, $sportId) {
    if (!is_array($matches)) return;

    // Sport belső id keresése
    $stmtSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
    $stmtSport->bind_param("i", $sportId);
    $stmtSport->execute();
    $resSport = $stmtSport->get_result();
    
    if ($resSport->num_rows === 0) {
        // Sport nem létezik, létrehozzuk
        $stmtInsertSport = $conn->prepare("INSERT INTO Sports (api_id, name, is_active) VALUES (?, ?, 1)");
        $sportName = "Sport {$sportId}";
        $stmtInsertSport->bind_param("is", $sportId, $sportName);
        $stmtInsertSport->execute();
        $internalSportId = $stmtInsertSport->insert_id;
        $stmtInsertSport->close();
    } else {
        $rowSport = $resSport->fetch_assoc();
        $internalSportId = (int)$rowSport['id'];
    }
    $stmtSport->close();

    // Championship/Competition keresése vagy létrehozása
    $stmtChamp = $conn->prepare("SELECT id FROM Competitions WHERE api_id = ?");
    $stmtCountry = $conn->prepare("SELECT id FROM Countries WHERE code = ?");
    $stmtInsertChamp = $conn->prepare("
        INSERT INTO Competitions (api_id, sport_id, country_id, name, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmtInsertMatch = $conn->prepare("
        INSERT INTO Events (api_id, sport_id, competition_id, name, home_team_name, away_team_name, start_time, is_live, live_time, status_id, home_score, away_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_live = VALUES(is_live),
            live_time = VALUES(live_time),
            home_score = VALUES(home_score),
            away_score = VALUES(away_score)
    ");

    foreach ($matches as $match) {
        $matchId = $match['id'] ?? 0;
        $leagueId = $match['leagueId'] ?? 0;
        $name = $match['name'] ?? '';
        $startUtc = $match['startDateUtc'] ?? '';
        $isLive = !empty($match['isLive']) ? 1 : 0;
        $liveTime = $match['liveTime'] ?? null;
        $score = $match['score'] ?? [];
        $homeScore = isset($score[0]) ? (int)$score[0] : null;
        $awayScore = isset($score[1]) ? (int)$score[1] : null;

        // Csapatok kinyerése a name-ből
        $teams = explode(' vs. ', $name);
        if (count($teams) < 2) {
            $teams = explode(' - ', $name);
        }
        $homeTeam = trim($teams[0] ?? $name);
        $awayTeam = trim($teams[1] ?? '');

        // Championship keresése
        $stmtChamp->bind_param("i", $leagueId);
        $stmtChamp->execute();
        $resChamp = $stmtChamp->get_result();
        
        if ($resChamp->num_rows === 0) {
            // Championship nem létezik, létrehozzuk
            $countryId = null;
            $champName = "Bajnokság {$leagueId}";
            
            $stmtInsertChamp->bind_param("iiis", $leagueId, $internalSportId, $countryId, $champName);
            $stmtInsertChamp->execute();
            $competitionId = $stmtInsertChamp->insert_id;
        } else {
            $rowChamp = $resChamp->fetch_assoc();
            $competitionId = (int)$rowChamp['id'];
        }

        // Meccs beillesztése
        $statusId = $isLive ? 2 : 1; // 2 = LIVE, 1 = NOT_STARTED
        
        $stmtInsertMatch->bind_param(
            "iiissssisiii",
            $matchId,
            $internalSportId,
            $competitionId,
            $name,
            $homeTeam,
            $awayTeam,
            $startUtc,
            $isLive,
            $liveTime,
            $statusId,
            $homeScore,
            $awayScore
        );
        $stmtInsertMatch->execute();
    }

    $stmtChamp->close();
    $stmtCountry->close();
    $stmtInsertChamp->close();
    $stmtInsertMatch->close();
}
?>