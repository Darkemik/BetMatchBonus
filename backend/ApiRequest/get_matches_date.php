<?php
require_once "connect.php";

$apiBaseUrl = "http://localhost:5000/api";
$date = date('Y-m-d');

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
    'ETH' => 'Etiópia',
    'KEN' => 'Kenya',
    'TZA' => 'Tanzánia',
    'UGA' => 'Uganda',
    'COD' => 'Kongói DK',
    'CIV' => 'Elefántcsontpart',
    'MLI' => 'Mali',
    'BFA' => 'Burkina Faso',
    'MOZ' => 'Mozambik',
    'ZMB' => 'Zambia',
    'ZWE' => 'Zimbabwe',
    'BWA' => 'Botswana',
    'NAM' => 'Namíbia',
    'MWI' => 'Malawi',
    'RWA' => 'Ruanda',
    'GAB' => 'Gabon',
    'BEN' => 'Benin',
    'TGO' => 'Togó',
    'NER' => 'Niger',
    'GIN' => 'Guinea',
    'SLE' => 'Sierra Leone',
    'LBR' => 'Libéria',
    'MRT' => 'Mauritánia',
    'MDG' => 'Madagaszkár',
    'LBY' => 'Líbia',
    'SDN' => 'Szudán',
    'JOR' => 'Jordánia',
    'LBN' => 'Libanon',
    'OMN' => 'Omán',
    'BHR' => 'Bahrein',
    'KWT' => 'Kuvait',
    'YEM' => 'Jemen',
    'SYR' => 'Szíria',
    'PAK' => 'Pakisztán',
    'BGD' => 'Banglades',
    'LKA' => 'Srí Lanka',
    'MMR' => 'Myanmar',
    'KHM' => 'Kambodzsa',
    'LAO' => 'Laosz',
    'MNG' => 'Mongólia',
    'TWN' => 'Tajvan',
    'HKG' => 'Hongkong',
    'MAC' => 'Makaó',
    'PRK' => 'Észak-Korea',
    'NPL' => 'Nepál',
    'AFG' => 'Afganisztán',
    'FJI' => 'Fidzsi',
    'PNG' => 'Pápua Új-Guinea',
];

// Helper: cURL GET → associative array, or null on failure
function curlGetJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$response) return null;
    return json_decode($response, true);
}

// 1) Sportok lekérése
$sports = curlGetJson("$apiBaseUrl/sports");
if (!is_array($sports)) {
    die("Sportok lekérése sikertelen.\n");
}

// Prepared statements
$stmtFindSport    = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
$stmtInsertSport  = $conn->prepare("INSERT INTO Sports (api_id, name, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE name = VALUES(name)");
$stmtFindCountry  = $conn->prepare("SELECT id FROM Countries WHERE code = ?");
$stmtUpsertCountry = $conn->prepare("INSERT IGNORE INTO Countries (code, name) VALUES (?, ?)");
$stmtFindComp     = $conn->prepare("SELECT id FROM Competitions WHERE api_id = ?");
$stmtUpsertComp   = $conn->prepare("
    INSERT INTO Competitions (api_id, sport_id, country_id, name, is_active)
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");
$stmtUpsertMatch  = $conn->prepare("
    INSERT INTO Events (api_id, sport_id, competition_id, name, home_team_name, away_team_name, start_time, is_live, live_time, status_id, home_score, away_score)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      sport_id = VALUES(sport_id),
      competition_id = VALUES(competition_id),
      name = VALUES(name),
      home_team_name = VALUES(home_team_name),
      away_team_name = VALUES(away_team_name),
      start_time = VALUES(start_time),
      is_live = VALUES(is_live),
      live_time = VALUES(live_time),
      status_id = VALUES(status_id),
      home_score = VALUES(home_score),
      away_score = VALUES(away_score)
");

$totalImported = 0;

foreach ($sports as $sport) {
    $sportApiId = (int)($sport['id'] ?? 0);
    $sportName  = $sport['name'] ?? "Sport {$sportApiId}";
    if ($sportApiId <= 0) continue;

    // Sport belső id keresése / létrehozása
    $stmtFindSport->bind_param("i", $sportApiId);
    $stmtFindSport->execute();
    $resSport = $stmtFindSport->get_result();
    if ($rowSport = $resSport->fetch_assoc()) {
        $internalSportId = (int)$rowSport['id'];
    } else {
        $stmtInsertSport->bind_param("is", $sportApiId, $sportName);
        $stmtInsertSport->execute();
        $internalSportId = (int)$stmtInsertSport->insert_id;
    }

    // 2) Bajnokságok lekérése és mentése
    $championshipsMap = []; // leagueId → name mapping a fallback-hez
    $championships = curlGetJson("$apiBaseUrl/sports/championships?sportId=$sportApiId");
    if (is_array($championships)) {
        foreach ($championships as $champ) {
            $champApiId  = (int)($champ['id'] ?? 0);
            $champName   = $champ['name'] ?? "Bajnokság {$champApiId}";
            $countryCode = trim($champ['countryCode'] ?? '');
            if ($champApiId <= 0) continue;

            // Mentjük a map-be a fallback-hez
            $championshipsMap[$champApiId] = [
                'name' => $champName,
                'countryCode' => $countryCode
            ];

            $cCode = $countryCode !== '' ? $countryCode : 'INT';
            // Ország név: mapping-ből vesszük, ha van
            $cName = $countryNameMap[$cCode] ?? ($countryCode !== '' ? $countryCode : 'Nemzetközi');

            $stmtUpsertCountry->bind_param("ss", $cCode, $cName);
            $stmtUpsertCountry->execute();

            $stmtFindCountry->bind_param("s", $cCode);
            $stmtFindCountry->execute();
            $resCountry = $stmtFindCountry->get_result();
            $countryRow = $resCountry->fetch_assoc();
            $countryId  = $countryRow ? (int)$countryRow['id'] : null;

            $stmtUpsertComp->bind_param("iiis", $champApiId, $internalSportId, $countryId, $champName);
            $stmtUpsertComp->execute();
        }
    }

    // 3) Mai meccsek lekérése
    $matches = curlGetJson("$apiBaseUrl/matches/date?sportId=$sportApiId&date=$date");
    if (!is_array($matches)) continue;

    foreach ($matches as $match) {
        $apiMatchId  = (int)($match['id'] ?? 0);
        $leagueId    = (int)($match['leagueId'] ?? 0);
        $name        = $match['name'] ?? '';
        $startUtcStr = $match['startDateUtc'] ?? '';
        $isLive      = !empty($match['isLive']) ? 1 : 0;
        $liveTime    = $match['liveTime'] ?? null;
        $statusId    = $isLive ? 2 : 1;

        $scoreArr  = $match['score'] ?? [];
        $homeScore = (is_array($scoreArr) && isset($scoreArr[0]) && is_numeric($scoreArr[0])) ? (int)$scoreArr[0] : null;
        $awayScore = (is_array($scoreArr) && isset($scoreArr[1]) && is_numeric($scoreArr[1])) ? (int)$scoreArr[1] : null;

        // Csapatok kinyerése (" vs. " és " - " elválasztó egyaránt)
        $teams = explode(' vs. ', $name);
        if (count($teams) < 2) {
            $teams = explode(' - ', $name);
        }
        $homeTeamName = trim($teams[0] ?? $name);
        $awayTeamName = trim($teams[1] ?? '');

        // Competition ID lekérése
        $stmtFindComp->bind_param("i", $leagueId);
        $stmtFindComp->execute();
        $resComp = $stmtFindComp->get_result();
        $compRow = $resComp->fetch_assoc();

        if (!$compRow) {
            // Bajnokság nem található - először az API championshipsMap-ből próbáljuk
            if (isset($championshipsMap[$leagueId])) {
                $champName = $championshipsMap[$leagueId]['name'];
                $champCountryCode = $championshipsMap[$leagueId]['countryCode'];
            } else {
                $champName = "Bajnokság {$leagueId}";
                $champCountryCode = '';
            }

            $cCode = $champCountryCode !== '' ? $champCountryCode : 'INT';
            $cName = $countryNameMap[$cCode] ?? ($champCountryCode !== '' ? $champCountryCode : 'Nemzetközi');

            $stmtUpsertCountry->bind_param("ss", $cCode, $cName);
            $stmtUpsertCountry->execute();

            $stmtFindCountry->bind_param("s", $cCode);
            $stmtFindCountry->execute();
            $resCountry = $stmtFindCountry->get_result();
            $countryRow = $resCountry->fetch_assoc();
            $countryId  = $countryRow ? (int)$countryRow['id'] : null;

            $stmtUpsertComp->bind_param("iiis", $leagueId, $internalSportId, $countryId, $champName);
            $stmtUpsertComp->execute();

            $stmtFindComp->bind_param("i", $leagueId);
            $stmtFindComp->execute();
            $resComp = $stmtFindComp->get_result();
            $compRow  = $resComp->fetch_assoc();
            if (!$compRow) continue;
        }

        $competitionId = (int)$compRow['id'];

        // startDateUtc → MySQL DATETIME (Budapest timezone)
        $dt = new DateTime($startUtcStr);
        $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
        $startTimeMysql = $dt->format('Y-m-d H:i:s');

        $stmtUpsertMatch->bind_param(
            "iiissssisiii",
            $apiMatchId,
            $internalSportId,
            $competitionId,
            $name,
            $homeTeamName,
            $awayTeamName,
            $startTimeMysql,
            $isLive,
            $liveTime,
            $statusId,
            $homeScore,
            $awayScore
        );
        $stmtUpsertMatch->execute();
        $totalImported++;
    }
}

$stmtFindSport->close();
$stmtInsertSport->close();
$stmtFindCountry->close();
$stmtUpsertCountry->close();
$stmtFindComp->close();
$stmtUpsertComp->close();
$stmtUpsertMatch->close();

echo "Napi meccsek importja kész. Összesen {$totalImported} meccs importálva.\n";
?>