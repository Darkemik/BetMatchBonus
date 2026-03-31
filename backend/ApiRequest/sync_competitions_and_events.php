<?php
/**
 * SYNC_COMPETITIONS_AND_EVENTS.PHP
 * 
 * ⭐ AZ EGYETLEN FÁJL ami külső API-t hív és DB-be ír sportadatokat.
 * Minden más fájl CSAK a DB-ből olvas.
 * 
 * Lépések:
 *   1) Sportok szinkronizálása       (GET /api/sports → Sports tábla)
 *   2) Bajnokságok szinkronizálása   (GET /api/sports/championships → Competitions + Countries)
 *   3) Élő meccsek szinkronizálása   (GET /api/matches/live → Events tábla)
 *   4) Napi meccsek szinkronizálása   (GET /api/matches/date → Events tábla)
 * 
 * Hívás:
 *   - refresh_all.php által (CRON / kézi)
 *   - Közvetlenül: http://localhost/backend/ApiRequest/sync_competitions_and_events.php
 * 
 * Odds-okat NEM tárolja! Azokat a get_match_details.php kéri real-time az API-ból.
 */

require_once dirname(__DIR__) . '/connect.php';
require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Europe/Budapest');
header('Content-Type: application/json; charset=utf-8');

/* ========================= SEGÉDFÜGGVÉNYEK ========================= */

/**
 * Sport upsert → visszaadja a lokális ID-t
 */
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
        throw new RuntimeException("Sport upsert sikertelen: api_id={$apiId}");
    }
    return (int)$row['id'];
}

/**
 * Ország upsert → visszaadja a lokális ID-t
 */
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

/**
 * Bajnokság upsert → visszaadja a lokális ID-t
 */
function upsertCompetition(mysqli $conn, int $leagueApiId, int $sportLocalId, string $name, ?int $countryId): int {
    $stmt = $conn->prepare("
        INSERT INTO Competitions (api_id, sport_id, country_id, name)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sport_id   = VALUES(sport_id),
            country_id = VALUES(country_id),
            name       = VALUES(name)
    ");
    $stmt->bind_param('iiis', $leagueApiId, $sportLocalId, $countryId, $name);
    $stmt->execute();
    $stmt->close();

    $sel = $conn->prepare("SELECT id FROM Competitions WHERE api_id = ? LIMIT 1");
    $sel->bind_param('i', $leagueApiId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$row) {
        throw new RuntimeException("Competition upsert sikertelen: api_id={$leagueApiId}");
    }
    return (int)$row['id'];
}

/**
 * Event (meccs) upsert
 */
function upsertEvent(
    mysqli $conn, int $eventApiId, int $sportId, int $competitionId,
    int $statusId, string $name, string $startUtc,
    bool $isLive, ?string $liveTime, ?int $homeScore, ?int $awayScore
): void {
    $isLiveInt = $isLive ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO Events
            (api_id, sport_id, competition_id, status_id, name, start_time, is_live, live_time, home_score, away_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sport_id       = VALUES(sport_id),
            competition_id = VALUES(competition_id),
            status_id      = VALUES(status_id),
            name           = VALUES(name),
            start_time     = VALUES(start_time),
            is_live        = VALUES(is_live),
            live_time      = VALUES(live_time),
            home_score     = VALUES(home_score),
            away_score     = VALUES(away_score)
    ");
    $stmt->bind_param(
        'iiiissisii',
        $eventApiId, $sportId, $competitionId, $statusId,
        $name, $startUtc, $isLiveInt, $liveTime,
        $homeScore, $awayScore
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Meccs status_id meghatározása az API live jelzés és a korábbi DB állapot alapján
 * 1 = NOT_STARTED, 2 = LIVE, 3 = FINISHED
 */
function resolveStatusId(mysqli $conn, int $eventApiId, bool $isLive): int {
    if ($isLive) return 2;

    $stmt = $conn->prepare("SELECT status_id FROM Events WHERE api_id = ? LIMIT 1");
    $stmt->bind_param("i", $eventApiId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return 1; // Új meccs, még nem volt DB-ben

    $oldStatus = (int)$row['status_id'];
    if ($oldStatus === 3) return 3; // Már FINISHED, ne állítsuk vissza
    if ($oldStatus === 2) return 3; // Korábban LIVE volt, most nem → FINISHED

    return 1; // NOT_STARTED
}

/**
 * Score kinyerése az API match objektumból
 */
function extractScore(array $match): array {
    $homeScore = null;
    $awayScore = null;
    if (isset($match['score']) && is_array($match['score']) && count($match['score']) >= 2) {
        $homeScore = is_numeric($match['score'][0]) ? (int)$match['score'][0] : null;
        $awayScore = is_numeric($match['score'][1]) ? (int)$match['score'][1] : null;
    }
    return [$homeScore, $awayScore];
}

/* ========================= FŐ IMPORT LOGIKA ========================= */

try {
    $conn->begin_transaction();

    $sportLocalMap    = []; // sportApiId => local sport.id
    $leagueNameMap    = []; // leagueApiId => leagueName
    $leagueCountryMap = []; // leagueApiId => countryId

    // ── 1) SPORTOK ────────────────────────────────────────────
    $sports = apiGet(EP_SPORTS_LIST);
    foreach ($sports as $s) {
        $sportApiId = (int)($s['id'] ?? 0);
        $sportName  = trim((string)($s['name'] ?? ''));
        if ($sportApiId <= 0 || $sportName === '') continue;

        $sportLocalMap[$sportApiId] = upsertSport($conn, $sportApiId, $sportName);
    }

    // ── 2) BAJNOKSÁGOK + ORSZÁGOK ─────────────────────────────
    foreach ($sportLocalMap as $sportApiId => $sportLocalId) {
        $leagues = apiGet(EP_CHAMPIONSHIPS, ['sportId' => $sportApiId]);

        foreach ($leagues as $l) {
            $leagueApiId = (int)($l['id'] ?? 0);
            $leagueName  = trim((string)($l['name'] ?? ''));
            $countryCode = trim((string)($l['countryCode'] ?? ''));
            if ($leagueApiId <= 0 || $leagueName === '') continue;

            $countryId = getCountryId($conn, $countryCode);
            upsertCompetition($conn, $leagueApiId, $sportLocalId, $leagueName, $countryId);

            $leagueNameMap[$leagueApiId]    = $leagueName;
            if ($countryId !== null) {
                $leagueCountryMap[$leagueApiId] = $countryId;
            }
        }
    }

    // ── 3) MECCSEK (élő + napi) ──────────────────────────────
    $today = (new DateTime('today'))->format('Y-m-d');
    $allLiveApiIds = []; // Összegyűjtjük az összes API-ból kapott live event ID-t

    foreach ($sportLocalMap as $sportApiId => $sportLocalId) {
        // Élő meccsek
        $liveMatches = apiGet(EP_MATCHES_LIVE, ['sportId' => $sportApiId]);
        // Mai meccsek
        $dateMatches = apiGet(EP_MATCHES_DATE, ['sportId' => $sportApiId, 'date' => $today]);

        // Élő meccsek ID-it gyűjtjük
        foreach ($liveMatches as $m) {
            $eid = (int)($m['id'] ?? 0);
            if ($eid > 0 && !empty($m['isLive'])) {
                $allLiveApiIds[] = $eid;
            }
        }

        // Összefésülés + deduplikáció event ID alapján (élő felülírja a datát)
        $allById = [];
        foreach (array_merge($dateMatches, $liveMatches) as $m) {
            $eid = (int)($m['id'] ?? 0);
            if ($eid > 0) $allById[$eid] = $m;
        }

        foreach ($allById as $m) {
            $eventApiId  = (int)$m['id'];
            $leagueApiId = (int)($m['leagueId'] ?? 0);
            $name        = trim((string)($m['name'] ?? ''));
            $startUtc    = trim((string)($m['startDateUtc'] ?? ''));
            $isLive      = (bool)($m['isLive'] ?? false);
            $liveTime    = isset($m['liveTime']) ? (string)$m['liveTime'] : null;

            if ($eventApiId <= 0 || $leagueApiId <= 0 || $name === '' || $startUtc === '') continue;

            [$homeScore, $awayScore] = extractScore($m);

            // Bajnokság biztosítása (ha az API-ból jött meccs olyan league-hez tartozik
            // amit a championships endpoint nem adott vissza)
            $leagueName = $leagueNameMap[$leagueApiId] ?? null;
            if ($leagueName === null || $leagueName === '') {
                try {
                    $detail = apiGet(EP_MATCH_DETAILS . '/' . $eventApiId);
                    $detailName = trim((string)($detail['leagueName'] ?? ''));
                    if ($detailName !== '') {
                        $leagueName = $detailName;
                        $leagueNameMap[$leagueApiId] = $leagueName;
                    }
                } catch (Throwable $e) {
                    error_log("League fallback hiba eventId={$eventApiId}: " . $e->getMessage());
                }
            }
            if (empty($leagueName)) {
                $leagueName = "UNKNOWN_LEAGUE_{$leagueApiId}";
                error_log("Hiányzó league név: leagueId={$leagueApiId}, eventId={$eventApiId}");
            }

            // Ország biztosítása
            $countryId = $leagueCountryMap[$leagueApiId] ?? null;

            $competitionId = upsertCompetition($conn, $leagueApiId, $sportLocalId, $leagueName, $countryId);
            $statusId      = resolveStatusId($conn, $eventApiId, $isLive);

            upsertEvent(
                $conn, $eventApiId, $sportLocalId, $competitionId, $statusId,
                $name, $startUtc, $isLive, $liveTime, $homeScore, $awayScore
            );
        }
    }

    // ── 4) BEFEJEZETT MECCSEK: DB-ben LIVE, de API-ban már nem ──
    if (!empty($allLiveApiIds)) {
        $placeholders = implode(',', array_fill(0, count($allLiveApiIds), '?'));
        $types = str_repeat('i', count($allLiveApiIds));
        $stmt = $conn->prepare("
            UPDATE Events SET is_live = 0, status_id = 3
            WHERE is_live = 1 AND api_id NOT IN ({$placeholders})
        ");
        $stmt->bind_param($types, ...$allLiveApiIds);
        $stmt->execute();
        $finishedCount = $stmt->affected_rows;
        $stmt->close();
    } else {
        // Ha egyetlen live meccs sincs az API-ban, MINDEN LIVE-ot lezárunk
        $conn->query("UPDATE Events SET is_live = 0, status_id = 3 WHERE is_live = 1");
        $finishedCount = $conn->affected_rows;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sportok + Bajnokságok + Meccsek szinkronizálva',
        'stats'   => [
            'sports'       => count($sportLocalMap),
            'competitions' => count($leagueNameMap),
            'finished'     => $finishedCount ?? 0,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

