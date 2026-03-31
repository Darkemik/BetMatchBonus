<?php
require_once dirname(__DIR__) . '/connect.php';

date_default_timezone_set('Europe/Budapest');
header('Content-Type: application/json; charset=utf-8');

const API_BASE_URL = 'http://localhost:5000';

const EP_SPORTS_LIST       = '/api/sports';
const EP_CHAMPIONSHIPS     = '/api/sports/championships';
const EP_MATCHES_LIVE      = '/api/matches/live';
const EP_MATCHES_DATE      = '/api/matches/date';
const EP_MATCH_DETAILS_BASE = '/api/matches'; // + /{eventId}

/* ------------------------- SEGÉDEK ------------------------- */

function apiGet(string $path, array $query = []): array {
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("API hiba (cURL): {$err}");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("API HTTP hiba: {$code} | URL: {$url} | Body: {$raw}");
    }

    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("API JSON parse hiba | URL: {$url}");
    }
    return $json;
}

function upsertSport(mysqli $conn, int $apiId, string $name): int {
    $stmt = $conn->prepare("
        INSERT INTO Sports (api_id, name)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");
    $stmt->bind_param('is', $apiId, $name);
    $stmt->execute();
    $stmt->close();

    $sel = $conn->prepare("SELECT id FROM Sports WHERE api_id = ? LIMIT 1");
    $sel->bind_param('i', $apiId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$row) {
        throw new RuntimeException("Sport mentés után nem található: {$apiId}");
    }
    return (int)$row['id'];
}
function countryNameFromCode(string $code): string {
    static $countryNameMap = [
        'INT' => 'Nemzetközi',
        'HUN' => 'Magyarország',
        'GBR' => 'Egyesült Királyság',
        'DEU' => 'Németország',
        'FRA' => 'Franciaország',
        'ESP' => 'Spanyolország',
        'ITA' => 'Olaszország',
        'PRT' => 'Portugália',
        'NLD' => 'Hollandia',
        'BEL' => 'Belgium',
        'AUT' => 'Ausztria',
        'CHE' => 'Svájc',
        'POL' => 'Lengyelország',
        'CZE' => 'Csehország',
        'SVK' => 'Szlovákia',
        'HRV' => 'Horvátország',
        'SRB' => 'Szerbia',
        'ROU' => 'Románia',
        'BGR' => 'Bulgária',
        'GRC' => 'Görögország',
        'TUR' => 'Törökország',
        'RUS' => 'Oroszország',
        'UKR' => 'Ukrajna',
        'SWE' => 'Svédország',
        'NOR' => 'Norvégia',
        'DNK' => 'Dánia',
        'FIN' => 'Finnország',
        'ISL' => 'Izland',
        'IRL' => 'Írország',
        'USA' => 'Egyesült Államok',
        'CAN' => 'Kanada',
        'MEX' => 'Mexikó',
        'BRA' => 'Brazília',
        'ARG' => 'Argentína',
        'JPN' => 'Japán',
        'KOR' => 'Dél-Korea',
        'CHN' => 'Kína',
        'AUS' => 'Ausztrália',
        'NZL' => 'Új-Zéland',
        'ZAF' => 'Dél-Afrika',
        'EGY' => 'Egyiptom',
        'MAR' => 'Marokkó',
        'IND' => 'India',
        'ARE' => 'Egyesült Arab Emírségek',
        'QAT' => 'Katar',
        'SAU' => 'Szaúd-Arábia',
        'ISR' => 'Izrael',
        'ALB' => 'Albánia',
        'SVN' => 'Szlovénia',
        'BIH' => 'Bosznia-Hercegovina',
        'MNE' => 'Montenegró',
        'MKD' => 'Észak-Macedónia',
        'LTU' => 'Litvánia',
        'LVA' => 'Lettország',
        'EST' => 'Észtország',
        'ABW' => 'Aruba',
    ];

    $code = strtoupper(trim($code));
    if ($code === '') return 'Nemzetközi';
    return $countryNameMap[$code] ?? $code;
}

function getCountryId(mysqli $conn, string $countryCode): ?int {
    $countryCode = strtoupper(trim($countryCode));
    if ($countryCode === '') return null;

    $name = countryNameFromCode($countryCode);

    $stmt = $conn->prepare("
        INSERT INTO Countries (code, name)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");
    $stmt->bind_param('ss', $countryCode, $name);
    $stmt->execute();
    $stmt->close();

    $sel = $conn->prepare("SELECT id FROM Countries WHERE code = ? LIMIT 1");
    $sel->bind_param('s', $countryCode);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    return $row ? (int)$row['id'] : null;
}

function upsertCompetition(mysqli $conn, int $leagueApiId, int $sportId, string $name, ?int $countryId): int {
    $stmt = $conn->prepare("
        INSERT INTO Competitions (api_id, sport_id, country_id, name)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sport_id = VALUES(sport_id),
            country_id = VALUES(country_id),
            name = VALUES(name)
    ");
    $stmt->bind_param('iiis', $leagueApiId, $sportId, $countryId, $name);
    $stmt->execute();
    $stmt->close();

    $sel = $conn->prepare("SELECT id FROM Competitions WHERE api_id = ? LIMIT 1");
    $sel->bind_param('i', $leagueApiId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$row) {
        throw new RuntimeException("Competition mentés után nem található: {$leagueApiId}");
    }
    return (int)$row['id'];
}

function upsertEvent(
    mysqli $conn,
    int $eventApiId,
    int $sportId,
    int $competitionId,
    int $statusId,
    string $name,
    string $startUtc,
    bool $isLive,
    ?string $liveTime,
    ?int $homeScore,
    ?int $awayScore
): void {
    $isLiveInt = $isLive ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO Events
            (api_id, sport_id, competition_id, status_id, name, start_time, is_live, live_time, home_score, away_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sport_id = VALUES(sport_id),
            competition_id = VALUES(competition_id),
            status_id = VALUES(status_id),
            name = VALUES(name),
            start_time = VALUES(start_time),
            is_live = VALUES(is_live),
            live_time = VALUES(live_time),
            home_score = VALUES(home_score),
            away_score = VALUES(away_score)
    ");
    $stmt->bind_param(
        'iiiissisii',
        $eventApiId,
        $sportId,
        $competitionId,
        $statusId,
        $name,
        $startUtc,
        $isLiveInt,
        $liveTime,
        $homeScore,
        $awayScore
    );
    $stmt->execute();
    $stmt->close();
}

function markMissingLiveMatches(mysqli $conn, int $sportLocalId, array $liveMatches): void {
    $liveIds = [];
    foreach ($liveMatches as $m) {
        $eid = (int)($m['id'] ?? 0);
        if ($eid > 0) $liveIds[] = $eid;
    }

    if (count($liveIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($liveIds), '?'));
        $types = 'i' . str_repeat('i', count($liveIds));
        $sql = "UPDATE Events
                SET is_live = 0, status_id = 3, live_status = 'Ended'
                WHERE sport_id = ? AND is_live = 1 AND start_time <= NOW() AND api_id NOT IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $params = array_merge([$sportLocalId], $liveIds);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE Events
                SET is_live = 0, status_id = 3, live_status = 'Ended'
                WHERE sport_id = ? AND is_live = 1 AND start_time <= NOW()");
        $stmt->bind_param('i', $sportLocalId);
        $stmt->execute();
        $stmt->close();
    }
}

/* ------------------------- IMPORT ------------------------- */

try {
    $conn->begin_transaction();

    $sports = apiGet(EP_SPORTS_LIST);
    $sportLocalMap   = []; // sportApiId => local sport.id
    $leagueNameMap   = []; // leagueApiId => leagueName
    $leagueCountryMap = []; // leagueApiId => countryId

    // 1) Sportok + bajnokságok
    foreach ($sports as $s) {
        $sportApiId = (int)($s['id'] ?? 0);
        $sportName  = trim((string)($s['name'] ?? ''));

        if ($sportApiId <= 0 || $sportName === '') continue;

        $sportLocalId = upsertSport($conn, $sportApiId, $sportName);
        $sportLocalMap[$sportApiId] = $sportLocalId;

        $leagues = apiGet(EP_CHAMPIONSHIPS, ['sportId' => $sportApiId]);
        foreach ($leagues as $l) {
            $leagueApiId = (int)($l['id'] ?? 0);
            $leagueName  = trim((string)($l['name'] ?? ''));
            $countryCode = trim((string)($l['countryCode'] ?? ''));

            if ($leagueApiId <= 0 || $leagueName === '') continue;

            $countryId = getCountryId($conn, $countryCode);
            if ($countryId !== null) { $leagueCountryMap[$leagueApiId] = $countryId; }
            upsertCompetition($conn, $leagueApiId, $sportLocalId, $leagueName, $countryId);
            $leagueNameMap[$leagueApiId] = $leagueName;
        }
    }

    // 2) Meccsek (live + mai dátum)
    $date = (new DateTime('today'))->format('Y-m-d');

    foreach ($sportLocalMap as $sportApiId => $sportLocalId) {
        $allMatches = [];

        $liveMatches = apiGet(EP_MATCHES_LIVE, ['sportId' => $sportApiId]);
        if (is_array($liveMatches)) $allMatches = array_merge($allMatches, $liveMatches);

        // If matches drop from live, mark them finished for this sport
        if (is_array($liveMatches)) {
            markMissingLiveMatches($conn, $sportLocalId, $liveMatches);
        }

        $dateMatches = apiGet(EP_MATCHES_DATE, ['sportId' => $sportApiId, 'date' => $date]);
        if (is_array($dateMatches)) $allMatches = array_merge($allMatches, $dateMatches);

        // deduplikálás event ID alapján
        $byId = [];
        foreach ($allMatches as $m) {
            $eid = (int)($m['id'] ?? 0);
            if ($eid > 0) $byId[$eid] = $m;
        }

        foreach ($byId as $m) {
            $eventApiId  = (int)($m['id'] ?? 0);
            $leagueApiId = (int)($m['leagueId'] ?? 0);
            $name        = trim((string)($m['name'] ?? ''));
            $startUtc    = trim((string)($m['startDateUtc'] ?? ''));
            $isLive      = (bool)($m['isLive'] ?? false);
            $liveTime    = isset($m['liveTime']) ? (string)$m['liveTime'] : null;

            if ($eventApiId <= 0 || $leagueApiId <= 0 || $name === '' || $startUtc === '') {
                continue;
            }

            $homeScore = null;
            $awayScore = null;
            if (isset($m['score']) && is_array($m['score']) && count($m['score']) >= 2) {
                $homeScore = is_numeric($m['score'][0]) ? (int)$m['score'][0] : null;
                $awayScore = is_numeric($m['score'][1]) ? (int)$m['score'][1] : null;
            }

            // League név biztosítás
            $leagueName = $leagueNameMap[$leagueApiId] ?? null;

            if ($leagueName === null || $leagueName === '') {
                try {
                    $detail = apiGet(EP_MATCH_DETAILS_BASE . '/' . $eventApiId);
                    $detailLeagueId = (int)($detail['leagueId'] ?? 0);
                    $detailLeagueName = trim((string)($detail['leagueName'] ?? ''));

                    if ($detailLeagueId === $leagueApiId && $detailLeagueName !== '') {
                        $leagueName = $detailLeagueName;
                        $leagueNameMap[$leagueApiId] = $leagueName;
                    }
                } catch (Throwable $e) {
                    error_log("Match details fallback hiba eventId={$eventApiId}: " . $e->getMessage());
                }
            }

            if ($leagueName === null || $leagueName === '') {
                // NINCS "Bajnokság 42008" fallback
                $leagueName = "UNKNOWN_LEAGUE_{$leagueApiId}";
                error_log("Hiányzó league név: leagueId={$leagueApiId}, eventId={$eventApiId}");
            }

            $countryIdForLeague = null;

// ha volt country a championships importból, használd újra:
if (isset($leagueCountryMap[$leagueApiId])) {
    $countryIdForLeague = $leagueCountryMap[$leagueApiId];
}

// ha nincs, fallback: match detailből próbáljuk countryCode-ot kinyerni (ha ad ilyet az API)
if ($countryIdForLeague === null) {
    try {
        $detail = apiGet(EP_MATCH_DETAILS_BASE . '/' . $eventApiId);
        $detailCountryCode = strtoupper(trim((string)($detail['countryCode'] ?? '')));
        if ($detailCountryCode !== '') {
            $countryIdForLeague = getCountryId($conn, $detailCountryCode);
            $leagueCountryMap[$leagueApiId] = $countryIdForLeague;
        }
    } catch (Throwable $e) {
        // no-op
    }
}

$competitionId = upsertCompetition(
    $conn,
    $leagueApiId,
    $sportLocalId,
    $leagueName,
    $countryIdForLeague
);

            // Status mapping: 1=NOT_STARTED, 2=LIVE, 3=FINISHED
            if ($isLive) {
                $statusId = 2;
            } else {
                $stmtOldStatus = $conn->prepare("SELECT status_id FROM Events WHERE api_id = ? LIMIT 1");
                $stmtOldStatus->bind_param("i", $eventApiId);
                $stmtOldStatus->execute();
                $oldRow = $stmtOldStatus->get_result()->fetch_assoc();
                $stmtOldStatus->close();

                if ($oldRow && (int)$oldRow["status_id"] === 3) {
                    $statusId = 3; // Mar FINISHED volt, ne valtoztassuk vissza
                } elseif ($oldRow && (int)$oldRow["status_id"] === 2) {
                    $statusId = 3; // Korabban LIVE volt, most mar nem -> befejezett
                } else {
                    $statusId = 1; // Meg nem kezdodott el
                }
            }

            upsertEvent(
                $conn,
                $eventApiId,
                $sportLocalId,
                $competitionId,
                $statusId,
                $name,
                $startUtc,
                $isLive,
                $liveTime,
                $homeScore,
                $awayScore
            );
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sports + Competitions + Events import kész'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
}

