<?php
require_once __DIR__ . "/connect.php";

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

// FONTOS: markFinishedMatchesBySport es markOldLiveMatchesGlobal KIKERULT innen.
// Ok: live_table.php EGYETLEN sport elo meccseit keri le az API-bol,
// a markFinished fuggvenyek pedig a DB-ben is_live=0-ra allitjak azokat a meccseket
// amik NINCSENEK az API valaszban - vagyis mas sportok meccseit is torlik.
// Ez okozta, hogy sportok eltuntek a navigaciobol fogadas leadasa utan.

date_default_timezone_set('Europe/Budapest');

$sport_id = isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 66;
$apiBaseUrl = "http://localhost:5000/api";

$liveUrl = "$apiBaseUrl/matches/live?sportId=$sport_id";
$ch = curl_init($liveUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);


if ($httpCode !== 200 || !$response) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs elo meccs ehhez a sporthoz.</div>';
    exit;
}

$matches = json_decode($response, true);
if (!is_array($matches)) $matches = [];

// CSAK az API altal visszaadott meccsek score/live frissitese - mas sportokat NEM bantunk
syncLiveMatchScores($conn, $matches);

if (empty($matches)) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs elo meccs ehhez a sporthoz.</div>';
    exit;
}

// Bajnoksag nevek + DB start_time-ok lekerese
$leagueIds = [];
$matchApiIds = [];
foreach ($matches as $m) {
    $lid = (int)($m['leagueId'] ?? 0);
    if ($lid > 0) $leagueIds[$lid] = true;
    $mid = (int)($m['id'] ?? 0);
    if ($mid > 0) $matchApiIds[] = $mid;
}
$leagueNamesMap = [];
if (!empty($leagueIds)) {
    $placeholders = implode(',', array_fill(0, count($leagueIds), '?'));
    $types = str_repeat('i', count($leagueIds));
    $stmtLeague = $conn->prepare("SELECT api_id, name FROM Competitions WHERE api_id IN ($placeholders)");
    $ids = array_keys($leagueIds);
    $stmtLeague->bind_param($types, ...$ids);
    $stmtLeague->execute();
    $resLeague = $stmtLeague->get_result();
    while ($row = $resLeague->fetch_assoc()) {
        $leagueNamesMap[(int)$row['api_id']] = $row['name'];
    }
    $stmtLeague->close();
}

// DB start_time lekerese fallbackhez (ha az API nem ad startDateUtc-t)
$dbStartTimes = [];
if (!empty($matchApiIds)) {
    $placeholders = implode(',', array_fill(0, count($matchApiIds), '?'));
    $types = str_repeat('i', count($matchApiIds));
    $stmtStart = $conn->prepare("SELECT api_id, start_time FROM Events WHERE api_id IN ($placeholders)");
    $stmtStart->bind_param($types, ...$matchApiIds);
    $stmtStart->execute();
    $resStart = $stmtStart->get_result();
    while ($row = $resStart->fetch_assoc()) {
        $dbStartTimes[(int)$row['api_id']] = $row['start_time'];
    }
    $stmtStart->close();
}

$sportIcons = [
    66 => 'fa-futbol', 67 => 'fa-basketball-ball', 78 => 'fa-bullseye',
    83 => 'fa-swimmer', 73 => 'fa-hand-rock', 70 => 'fa-hockey-puck',
    77 => 'fa-table-tennis', 145 => 'fa-gamepad', 76 => 'fa-running',
    90 => 'fa-hockey-puck', 68 => 'fa-baseball-ball', 69 => 'fa-football-ball',
    71 => 'fa-volleyball-ball', 72 => 'fa-golf-ball', 74 => 'fa-fist-raised',
    75 => 'fa-biking', 79 => 'fa-skiing', 80 => 'fa-snowflake',
    84 => 'fa-table-tennis', 85 => 'fa-chess', 109 => 'fa-volleyball-ball',
    110 => 'fa-futbol', 138 => 'fa-running', 151 => 'fa-trophy',
];
$sportIcon = $sportIcons[$sport_id] ?? 'fa-trophy';
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Bajnoksag</th>
            <th><i class="fas <?php echo $sportIcon; ?>"></i> Meccs</th>
            <th><i class="fas fa-star"></i> Allas</th>
            <th><i class="fas fa-clock"></i> Kezdes</th>
            <th><i class="fas fa-stopwatch"></i> Statusz</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($matches as $match):
            $matchId = $match['id'] ?? 0;
            $leagueId = (int)($match['leagueId'] ?? 0);
            $leagueName = $leagueNamesMap[$leagueId] ?? ($match['leagueName'] ?? 'Ismeretlen');
            $name = $match['name'] ?? '';
            $startUtc = $match['startDateUtc'] ?? '';
            $isLive = !empty($match['isLive']) ? 1 : 0;
            $liveTime = $match['liveTime'] ?? '-';
            $score = $match['score'] ?? [];
            $scoreDisplay = (is_array($score) && count($score) >= 2)
                ? htmlspecialchars($score[0] . ' - ' . $score[1]) : '-';

            $teams = explode(' vs. ', $name);
            if (count($teams) < 2) $teams = explode(' - ', $name);
            $home = htmlspecialchars(trim($teams[0] ?? $name));
            $away = htmlspecialchars(trim($teams[1] ?? ''));

            // Kezdes idopontja - UTC konverzio + DB fallback
            $startFormatted = '';
            if (!empty($startUtc)) {
                try {
                    $dt = new DateTime($startUtc, new DateTimeZone('UTC'));
                    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
                    $startFormatted = $dt->format('H:i');
                } catch (Exception $e) { $startFormatted = ''; }
            }
            if (empty($startFormatted) && isset($dbStartTimes[(int)$matchId])) {
                $dbTime = $dbStartTimes[(int)$matchId];
                if (!empty($dbTime)) $startFormatted = date('H:i', strtotime($dbTime));
            }
            if (empty($startFormatted)) $startFormatted = '-';
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
                        <button class="btn-add-bet" data-match-id="<?php echo $matchId; ?>" title="Fogadasok megtekintese">
                            <i class="fas fa-plus"></i> Fogadas
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php ?>

