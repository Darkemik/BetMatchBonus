<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

// ===== live_helper.php függvények (beolvasztva) =====

/**
 * Élő meccsek score/live_time frissítése az adatbázisban.
 */
function syncLiveMatchScores($conn, $liveMatches) {
    if (!is_array($liveMatches) || empty($liveMatches)) return;

    $stmtUpdate = $conn->prepare("
        UPDATE Events 
        SET is_live = ?, live_time = ?, home_score = ?, away_score = ?,
            status_id = CASE WHEN ? = 1 THEN 2 ELSE status_id END
        WHERE api_id = ?
    ");
    if (!$stmtUpdate) return;

    foreach ($liveMatches as $match) {
        $matchId = $match['id'] ?? 0;
        if ($matchId <= 0) continue;

        $score = $match['score'] ?? [];
        $homeScore = isset($score[0]) ? (int)$score[0] : null;
        $awayScore = isset($score[1]) ? (int)$score[1] : null;
        $isLive = !empty($match['isLive']) ? 1 : 0;
        $liveTime = $match['liveTime'] ?? null;

        $stmtUpdate->bind_param("isiiii", $isLive, $liveTime, $homeScore, $awayScore, $isLive, $matchId);
        $stmtUpdate->execute();
    }
    $stmtUpdate->close();
}

/**
 * Korábban élőként jelölt meccsek befejezettnek jelölése.
 */
function markFinishedMatchesBySport($conn, $currentLiveMatches, $sportApiId) {
    $stmtSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
    $stmtSport->bind_param("i", $sportApiId);
    $stmtSport->execute();
    $sportRow = $stmtSport->get_result()->fetch_assoc();
    $stmtSport->close();
    if (!$sportRow) return;
    $internalSportId = (int)$sportRow['id'];

    $liveApiIds = [];
    if (is_array($currentLiveMatches)) {
        foreach ($currentLiveMatches as $m) {
            if (isset($m['id'])) {
                $liveApiIds[] = (int)$m['id'];
            }
        }
    }

    if (empty($liveApiIds)) {
        $stmt = $conn->prepare("
            UPDATE Events 
            SET is_live = 0, live_status = 'Ended', status_id = 3
            WHERE sport_id = ? AND is_live = 1 AND start_time < NOW()
        ");
        $stmt->bind_param("i", $internalSportId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $placeholders = implode(',', array_fill(0, count($liveApiIds), '?'));
    $types = str_repeat('i', count($liveApiIds));

    $sql = "UPDATE Events 
            SET is_live = 0, live_status = 'Ended', status_id = 3
            WHERE sport_id = ? 
              AND is_live = 1 
              AND api_id NOT IN ($placeholders)
              AND start_time < NOW()";

    $stmt = $conn->prepare($sql);
    $params = array_merge([$internalSportId], $liveApiIds);
    $typeStr = 'i' . $types;
    $stmt->bind_param($typeStr, ...$params);
    $stmt->execute();
    $stmt->close();
}

/**
 * Globális fallback: 2+ órája kezdődött és még is_live=1 → befejezett.
 */
function markOldLiveMatchesGlobal($conn) {
    $stmt = $conn->prepare("
        UPDATE Events 
        SET is_live = 0, live_status = 'Ended', status_id = 3
        WHERE is_live = 1 
          AND start_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    $stmt->close();
}

// ===== Eredeti get_matches_live.php logika =====

$apiBaseUrl = "http://localhost:5000/api";

// Sport ikon mapping (ismert sportok)
$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    146 => 'fa-futbol',           // e-Labdarúgás
    147 => 'fa-basketball-ball',  // e-Kosárlabda
    148 => 'fa-hockey-puck',     // e-Jégkorong
    77  => 'fa-table-tennis',
    76  => 'fa-running',       // Futsal
    90  => 'fa-hockey-puck',   // Floorball
    68  => 'fa-baseball-ball', // Baseball
    69  => 'fa-football-ball', // Amerikai foci
    71  => 'fa-volleyball-ball', // Röplabda
    72  => 'fa-golf-ball',     // Golf
    74  => 'fa-fist-raised',   // MMA/Küzdősport
    75  => 'fa-biking',        // Kerékpár
    79  => 'fa-skiing',        // Síelés
    80  => 'fa-snowflake',     // Téli sport
    84  => 'fa-table-tennis',  // Badminton
    85  => 'fa-chess',         // Sakk
    109 => 'fa-volleyball-ball', // Strandröplabda
    110 => 'fa-futbol',        // Futsal (alt)
    138 => 'fa-running',       // Krikett
    151 => 'fa-trophy',        // Snooker
];

// Sport név mapping (ismert sportok - magyar)
$sportNames = [
    66  => 'Labdarúgás',
    67  => 'Kosárlabda',
    78  => 'Darts',
    83  => 'Vízilabda',
    73  => 'Kézilabda',
    70  => 'Jégkorong',
    145 => 'E-sportok',
    146 => 'e-Labdarúgás',
    147 => 'e-Kosárlabda',
    148 => 'e-Jégkorong',
    77  => 'Pingpong',
    76  => 'Futsal',
    90  => 'Floorball',
    68  => 'Baseball',
    69  => 'Amerikai foci',
    71  => 'Röplabda',
    72  => 'Golf',
    74  => 'MMA',
    75  => 'Kerékpár',
    79  => 'Síelés',
    80  => 'Téli sport',
    84  => 'Badminton',
    85  => 'Sakk',
    109 => 'Strandröplabda',
    110 => 'Futsal',
    138 => 'Krikett',
    151 => 'Snooker',
];

// 1) Sportok lekérése
$sportsUrl = "$apiBaseUrl/sports";
$ch = curl_init($sportsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$sportsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$sportsResponse) {
    echo json_encode(['error' => 'Sportok lekérése sikertelen', 'sports' => [], 'sportDetails' => []]);
    exit;
}

$sports = json_decode($sportsResponse, true);
if (!is_array($sports)) {
    echo json_encode(['error' => 'Sportok parse hiba', 'sports' => [], 'sportDetails' => []]);
    exit;
}

$result = ['sports' => [], 'sportDetails' => []];

// 2) Minden sport esetén élő meccsek lekérése
foreach ($sports as $sport) {
    $sportId = $sport['id'] ?? 0;
    $sportName = $sport['name'] ?? 'Ismeretlen';
    $hasLive = $sport['hasLiveEvents'] ?? false;

    // Sport részletek mentése (név, ikon) - minden sporthoz, nem csak az élőkhöz
    $result['sportDetails'][$sportId] = [
        'name' => $sportNames[$sportId] ?? $sportName,
        'icon' => $sportIcons[$sportId] ?? 'fa-trophy'
    ];

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
        // Befejezett meccsek jelölése (üres lista esetén is: minden korábbi élő befejezett)
        markFinishedMatchesBySport($conn, $liveMatches ?? [], $sportId);
    } else {
        $result['sports'][$sportId] = 0;
    }
}

// Globális: MINDEN olyan meccs ami 2+ órája kezdődött és még is_live=1 → befejezett
markOldLiveMatchesGlobal($conn);

echo json_encode($result);

/**
 * Meccsek importálása az adatbázisba
 */
function importMatches($conn, $matches, $sportId) {
    if (!is_array($matches)) return;

    $apiBaseUrl = "http://localhost:5000/api";

    // Ország kód → magyar név mapping
    $countryNameMap = [
        'INT' => 'Nemzetközi',
        'HUN' => 'Magyarország',
        'GBR' => 'Egyesült Királyság',
        'ENG' => 'Anglia',
        'SCT' => 'Skócia',
        'WLS' => 'Wales',
        'NIR' => 'Észak-Írország',
        'DEU' => 'Németország',
        'GER' => 'Németország',
        'FRA' => 'Franciaország',
        'ESP' => 'Spanyolország',
        'ITA' => 'Olaszország',
        'PRT' => 'Portugália',
        'NLD' => 'Hollandia',
        'BEL' => 'Belgium',
        'AUT' => 'Ausztria',
        'CHE' => 'Svájc',
        'SUI' => 'Svájc',
        'POL' => 'Lengyelország',
        'CZE' => 'Csehország',
        'SVK' => 'Szlovákia',
        'HRV' => 'Horvátország',
        'CRO' => 'Horvátország',
        'SRB' => 'Szerbia',
        'ROU' => 'Románia',
        'BGR' => 'Bulgária',
        'BUL' => 'Bulgária',
        'GRC' => 'Görögország',
        'GRE' => 'Görögország',
        'TUR' => 'Törökország',
        'RUS' => 'Oroszország',
        'UKR' => 'Ukrajna',
        'SWE' => 'Svédország',
        'NOR' => 'Norvégia',
        'DNK' => 'Dánia',
        'DEN' => 'Dánia',
        'FIN' => 'Finnország',
        'ISL' => 'Izland',
        'IRL' => 'Írország',
        'USA' => 'Egyesült Államok',
        'CAN' => 'Kanada',
        'MEX' => 'Mexikó',
        'BRA' => 'Brazília',
        'ARG' => 'Argentína',
        'COL' => 'Kolumbia',
        'CHL' => 'Chile',
        'URY' => 'Uruguay',
        'PRY' => 'Paraguay',
        'PER' => 'Peru',
        'ECU' => 'Ecuador',
        'BOL' => 'Bolívia',
        'VEN' => 'Venezuela',
        'JPN' => 'Japán',
        'KOR' => 'Dél-Korea',
        'CHN' => 'Kína',
        'AUS' => 'Ausztrália',
        'NZL' => 'Új-Zéland',
        'ZAF' => 'Dél-Afrika',
        'RSA' => 'Dél-Afrika',
        'EGY' => 'Egyiptom',
        'MAR' => 'Marokkó',
        'NGA' => 'Nigéria',
        'GHA' => 'Ghána',
        'CMR' => 'Kamerun',
        'SEN' => 'Szenegál',
        'TUN' => 'Tunézia',
        'DZA' => 'Algéria',
        'ALG' => 'Algéria',
        'SAU' => 'Szaúd-Arábia',
        'KSA' => 'Szaúd-Arábia',
        'ARE' => 'Egyesült Arab Emírségek',
        'UAE' => 'Egyesült Arab Emírségek',
        'QAT' => 'Katar',
        'IRN' => 'Irán',
        'IRQ' => 'Irak',
        'IND' => 'India',
        'IDN' => 'Indonézia',
        'THA' => 'Thaiföld',
        'VNM' => 'Vietnam',
        'MYS' => 'Malajzia',
        'SGP' => 'Szingapúr',
        'PHL' => 'Fülöp-szigetek',
        'ISR' => 'Izrael',
        'CYP' => 'Ciprus',
        'GEO' => 'Grúzia',
        'ARM' => 'Örményország',
        'AZE' => 'Azerbajdzsán',
        'KAZ' => 'Kazahsztán',
        'UZB' => 'Üzbegisztán',
        'BLR' => 'Fehéroroszország',
        'MDA' => 'Moldova',
        'LTU' => 'Litvánia',
        'LVA' => 'Lettország',
        'EST' => 'Észtország',
        'SVN' => 'Szlovénia',
        'SLO' => 'Szlovénia',
        'BIH' => 'Bosznia-Hercegovina',
        'MNE' => 'Montenegró',
        'MKD' => 'Észak-Macedónia',
        'ALB' => 'Albánia',
        'KOS' => 'Koszovó',
        'XKX' => 'Koszovó',
        'LUX' => 'Luxemburg',
        'MLT' => 'Málta',
        'AND' => 'Andorra',
        'MCO' => 'Monaco',
        'LIE' => 'Liechtenstein',
        'SMR' => 'San Marino',
        'FRO' => 'Feröer-szigetek',
        'GIB' => 'Gibraltár',
        'ABW' => 'Aruba',
        'CRI' => 'Costa Rica',
        'PAN' => 'Panama',
        'JAM' => 'Jamaica',
        'HND' => 'Honduras',
        'SLV' => 'El Salvador',
        'GTM' => 'Guatemala',
        'CUB' => 'Kuba',
        'DOM' => 'Dominikai Köztársaság',
        'TTO' => 'Trinidad és Tobago',
    ];

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
    $stmtInsertCountry = $conn->prepare("INSERT INTO Countries (code, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    $stmtInsertChamp = $conn->prepare("
        INSERT INTO Competitions (api_id, sport_id, country_id, name, is_active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name = VALUES(name), country_id = VALUES(country_id)
    ");
    $stmtInsertMatch = $conn->prepare("
        INSERT INTO Events (api_id, sport_id, competition_id, name, home_team_name, away_team_name, start_time, is_live, live_time, status_id, home_score, away_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_live = VALUES(is_live),
            live_time = VALUES(live_time),
            home_score = VALUES(home_score),
            away_score = VALUES(away_score),
            status_id = CASE WHEN VALUES(is_live) = 1 THEN 2 ELSE status_id END
    ");

    // Bajnokságok lekérése az API-ból, hogy a valódi neveket kapjuk
    $championsUrl = "$apiBaseUrl/sports/championships?sportId=$sportId";
    $chCurl = curl_init($championsUrl);
    curl_setopt($chCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chCurl, CURLOPT_TIMEOUT, 10);
    $champResponse = curl_exec($chCurl);
    $champHttpCode = curl_getinfo($chCurl, CURLINFO_HTTP_CODE);
    curl_close($chCurl);

    $championshipsMap = [];
    if ($champHttpCode === 200 && $champResponse) {
        $champList = json_decode($champResponse, true);
        if (is_array($champList)) {
            foreach ($champList as $c) {
                $cId = (int)($c['id'] ?? 0);
                if ($cId > 0) {
                    $championshipsMap[$cId] = [
                        'name' => $c['name'] ?? "Bajnokság {$cId}",
                        'countryCode' => trim($c['countryCode'] ?? '')
                    ];
                }
            }
        }
    }

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

        // Csapatok kinyerése a name-ből (" vs. " és " - " elválasztó egyaránt)
        $teams = explode(' vs. ', $name);
        if (count($teams) < 2) {
            $teams = explode(' - ', $name);
        }
        $homeTeam = trim($teams[0] ?? $name);
        $awayTeam = trim($teams[1] ?? '');

        // startDateUtc → MySQL DATETIME (Budapest timezone)
        $startTimeMysql = '';
        if (!empty($startUtc)) {
            $dt = new DateTime($startUtc);
            $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
            $startTimeMysql = $dt->format('Y-m-d H:i:s');
        }

        // Championship keresése
        $stmtChamp->bind_param("i", $leagueId);
        $stmtChamp->execute();
        $resChamp = $stmtChamp->get_result();
        
        if ($resChamp->num_rows === 0) {
            // Championship nem létezik - API-ból kapjuk a nevet
            $countryId = null;
            
            if (isset($championshipsMap[$leagueId])) {
                $champName = $championshipsMap[$leagueId]['name'];
                $countryCode = $championshipsMap[$leagueId]['countryCode'];
            } else {
                $champName = "Bajnokság {$leagueId}";
                $countryCode = '';
            }

            // Ország kezelése
            if ($countryCode !== '') {
                $cName = $countryNameMap[$countryCode] ?? $countryCode;
                $stmtInsertCountry->bind_param("ss", $countryCode, $cName);
                $stmtInsertCountry->execute();
                $stmtCountry->bind_param("s", $countryCode);
                $stmtCountry->execute();
                $resCountry = $stmtCountry->get_result();
                $countryRow = $resCountry->fetch_assoc();
                if ($countryRow) {
                    $countryId = (int)$countryRow['id'];
                }
            }
            
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
            $startTimeMysql,
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
    $stmtInsertCountry->close();
    $stmtInsertChamp->close();
    $stmtInsertMatch->close();
}

?>