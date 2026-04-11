<?php
/**
 * LIVE_TABLE.PHP — Élő meccsek táblázat (CSAK DB-ből)
 * 
 * API-t NEM hív! Az adatok a sync_competitions_and_events.php által kerülnek DB-be.
 * 
 * Query: ?sport_id=66
 * Output: HTML tábla
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

// ===== syncLiveMatchScores fuggveny =====
// Frissiti az API altal visszaadott meccsek allasat a DB-ben.
// Ha egy meccs az API-ban mar NEM elo (isLive=false) DE korabban elo volt a DB-ben,
// VAGY a liveStatus befejezest jelez -> status_id = 3 (FINISHED) lesz.
if (!function_exists('syncLiveMatchScores')) {
    function syncLiveMatchScores($conn, $liveMatches) {
        if (!is_array($liveMatches) || empty($liveMatches)) return;

        $finishedKeywords = ['ended', 'finished', 'final', 'ft', 'aet', 'ap', 'closed',
                             'retired', 'walkover', 'cancelled', 'abandoned',
                             'after penalties', 'after extra time', 'full-time', 'result'];

        $stmtUpdate = $conn->prepare("
            UPDATE Events 
            SET is_live = ?, live_time = ?, home_score = ?, away_score = ?,
                status_id = ?, live_status = COALESCE(?, live_status)
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
            $liveStatus = $match['liveStatus'] ?? $match['status'] ?? null;

            // status_id meghatarozasa:
            // - Ha elo -> 2 (LIVE)
            // - Ha NEM elo ES a liveStatus befejezest jelez -> 3 (FINISHED)
            // - Ha NEM elo de nincs befejezesi jelzes -> ne valtoztassunk (marad ami volt)
            if ($isLive) {
                $newStatusId = 2; // LIVE
            } else {
                // Ellenorizzuk a liveStatus szoveget
                $isFinishedByStatus = false;
                if ($liveStatus) {
                    $statusLower = strtolower(trim($liveStatus));
                    foreach ($finishedKeywords as $kw) {
                        if (strpos($statusLower, $kw) !== false) {
                            $isFinishedByStatus = true;
                            break;
                        }
                    }
                }
                // Ellenorizzuk a liveTime szoveget is
                if (!$isFinishedByStatus && $liveTime) {
                    $timeLower = strtolower(trim($liveTime));
                    foreach (['ended', 'ft', 'finished', 'final'] as $kw) {
                        if (strpos($timeLower, $kw) !== false) {
                            $isFinishedByStatus = true;
                            break;
                        }
                    }
                }

                if ($isFinishedByStatus) {
                    $newStatusId = 3; // FINISHED
                } else {
                    // Nem elo, de nincs egyertelmu befejezesi jelzes
                    // Nezzuk meg a DB-ben korabban elo volt-e
                    $stmtCheck = $conn->prepare("SELECT is_live, status_id FROM Events WHERE api_id = ? LIMIT 1");
                    $stmtCheck->bind_param("i", $matchId);
                    $stmtCheck->execute();
                    $dbRow = $stmtCheck->get_result()->fetch_assoc();
                    $stmtCheck->close();

                    if ($dbRow && (int)$dbRow['is_live'] === 1) {
                        // Korabban elo volt, most mar nem -> valoszinuleg befejezett
                        $newStatusId = 3; // FINISHED
                    } else {
                        // Nem volt elo korabban sem -> ne valtoztassunk
                        $newStatusId = (int)($dbRow['status_id'] ?? 1);
                    }
                }
            }

            $stmtUpdate->bind_param("isiiisi", $isLive, $liveTime, $homeScore, $awayScore, $newStatusId, $liveStatus, $matchId);
            $stmtUpdate->execute();
        }
        $stmtUpdate->close();
    }
}

// Mark live matches as finished when they disappear from the live feed (sport-scoped)
if (!function_exists('markMissingLiveMatchesBySport')) {
    function markMissingLiveMatchesBySport($conn, $sportApiId, $liveMatches) {
        $stmtSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ? LIMIT 1");
        $stmtSport->bind_param("i", $sportApiId);
        $stmtSport->execute();
        $sportRow = $stmtSport->get_result()->fetch_assoc();
        $stmtSport->close();

        if (!$sportRow) return;

        $sportLocalId = (int)$sportRow['id'];
        $liveIds = [];
        if (is_array($liveMatches)) {
            foreach ($liveMatches as $m) {
                $mid = (int)($m['id'] ?? 0);
                if ($mid > 0) $liveIds[] = $mid;
            }
        }

        if (count($liveIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($liveIds), '?'));
            $types = 'i' . str_repeat('i', count($liveIds));
            $sql = "UPDATE Events
                    SET is_live = 0, status_id = 3, live_status = 'Ended'
                    WHERE sport_id = ? AND is_live = 1 AND start_time <= NOW() AND api_id NOT IN ($placeholders)";
            $stmtUpd = $conn->prepare($sql);
            $params = array_merge([$sportLocalId], $liveIds);
            $stmtUpd->bind_param($types, ...$params);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtUpd = $conn->prepare("UPDATE Events
                    SET is_live = 0, status_id = 3, live_status = 'Ended'
                    WHERE sport_id = ? AND is_live = 1 AND start_time <= NOW()" );
            $stmtUpd->bind_param('i', $sportLocalId);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }
}

// FONTOS: markFinishedMatchesBySport es markOldLiveMatchesGlobal KIKERULT innen.
// Ok: live_table.php EGYETLEN sport elo meccseit keri le az API-bol,
// a markFinished fuggvenyek pedig a DB-ben is_live=0-ra allitjak azokat a meccseket
// amik NINCSENEK az API valaszban - vagyis mas sportok meccseit is torlik.
// Ez okozta, hogy sportok eltuntek a navigaciobol fogadas leadasa utan.

date_default_timezone_set('Europe/Budapest');

$sport_id = isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 66;
$sportIcon = getSportIcon($sport_id);

// Élő meccsek lekérése DB-ből
$stmt = $conn->prepare("
    SELECT 
        e.api_id,
        e.name,
        e.start_time,
        e.is_live,
        e.live_time,
        e.home_score,
        e.away_score,
        comp.name AS league_name,
        comp.api_id AS league_api_id
    FROM Events e
    JOIN Sports s ON e.sport_id = s.id
    JOIN Competitions comp ON e.competition_id = comp.id
    WHERE s.api_id = ?
      AND e.is_live = 1
      AND (e.live_time IS NULL OR LOWER(TRIM(e.live_time)) NOT IN ('nem kezdődött el', 'not started', '', 'unknown'))
    ORDER BY comp.name ASC, e.start_time ASC
");

if (!$stmt) {
    echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
    exit;
}

$stmt->bind_param("i", $sport_id);
$stmt->execute();
$res = $stmt->get_result();

$matches = [];
while ($row = $res->fetch_assoc()) {
    $matches[] = $row;
}
$stmt->close();

if (empty($matches)) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs elo meccs ehhez a sporthoz.</div>';
    exit;
}
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Bajnokság</th>
            <th><i class="fas <?php echo htmlspecialchars($sportIcon); ?>"></i> Meccs</th>
            <th><i class="fas fa-star"></i> Állás</th>
            <th><i class="fas fa-clock"></i> Kezdés</th>
            <th><i class="fas fa-stopwatch"></i> Státusz</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($matches as $row):
            $matchId    = (int)$row['api_id'];
            $leagueName = $row['league_name'] ?? 'Ismeretlen';
            $name       = $row['name'] ?? '';
            $isLive     = (int)$row['is_live'];
            $liveTime   = $row['live_time'] ?? '-';

            $homeScore = $row['home_score'];
            $awayScore = $row['away_score'];
            $scoreDisplay = ($homeScore !== null && $awayScore !== null)
                ? htmlspecialchars($homeScore . ' - ' . $awayScore) : '-';

            // Csapatnevek bontása
            $teams = explode(' vs. ', $name);
            if (count($teams) < 2) $teams = explode(' - ', $name);
            $home = htmlspecialchars(trim($teams[0] ?? $name));
            $away = htmlspecialchars(trim($teams[1] ?? ''));

            // Kezdés időpont (DB-ben UTC → Budapest konverzió)
            $startFormatted = '-';
            if (!empty($row['start_time'])) {
                $dtStart = new DateTime($row['start_time'], new DateTimeZone('UTC'));
                $dtStart->setTimezone(new DateTimeZone('Europe/Budapest'));
                $startFormatted = $dtStart->format('H:i');
            }
        ?>
            <tr class="match-row clickable" data-match-id="<?php echo $matchId; ?>">
                <td><span class="league-name"><?php echo htmlspecialchars($leagueName); ?></span></td>
                <td class="match-cell">
                    <?php if ($away !== ''): ?>
                        <span class="team home-team"><?php echo $home; ?></span>
                        <span class="vs">vs</span>
                        <span class="team away-team"><?php echo $away; ?></span>
                    <?php else: ?>
                        <span class="team"><?php echo $home; ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="match-score"><?php echo $scoreDisplay; ?></span></td>
                <td><span class="start-time"><?php echo $startFormatted; ?></span></td>
                <td>
                    <?php if ($isLive): ?>
                        <div class="live-time-cell">
                            <span class="live-dot"></span>
                            <span class="live-time-value"><?php echo htmlspecialchars($liveTime); ?></span>
                        </div>
                    <?php else: ?>
                        <button class="btn-add-bet" data-match-id="<?php echo $matchId; ?>" title="Fogadások megtekintése">
                            <i class="fas fa-plus"></i> Fogadás
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>