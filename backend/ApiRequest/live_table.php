<?php
require_once __DIR__ . "/connect.php";

// ===== live_helper.php függvények (beolvasztva) =====
// Csak akkor definiáljuk, ha még nem léteznek (ha get_matches_live.php már definiálta)
if (!function_exists('syncLiveMatchScores')) {
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
}

if (!function_exists('markFinishedMatchesBySport')) {
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
}

if (!function_exists('markOldLiveMatchesGlobal')) {
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
}

// ===== Eredeti live_table.php logika =====

$sport_id = isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 66;

$apiBaseUrl = "http://localhost:5000/api";

// Élő meccsek lekérése az API-ból
$liveUrl = "$apiBaseUrl/matches/live?sportId=$sport_id";
$ch = curl_init($liveUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő meccs ehhez a sporthoz.</div>';
    exit;
}

$matches = json_decode($response, true);
if (!is_array($matches)) $matches = [];

// Adatbázis szinkronizálás - élő meccsek frissítése, befejezettek jelölése
syncLiveMatchScores($conn, $matches);
markFinishedMatchesBySport($conn, $matches, $sport_id);
markOldLiveMatchesGlobal($conn);

if (empty($matches)) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő meccs ehhez a sporthoz.</div>';
    exit;
}

$sportIcons = [
    66 => 'fa-futbol',
    67 => 'fa-basketball-ball',
    78 => 'fa-bullseye',
    83 => 'fa-swimmer',
    73 => 'fa-hand-rock',
    70 => 'fa-hockey-puck',
    77 => 'fa-table-tennis',
    145 => 'fa-gamepad'
];

$sportIcon = $sportIcons[$sport_id] ?? 'fa-futbol';
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Bajnokság</th>
            <th><i class="fas <?php echo $sportIcon; ?>"></i> Meccs</th>
            <th><i class="fas fa-star"></i> Állás</th>
            <th><i class="fas fa-clock"></i> Kezdés</th>
            <th><i class="fas fa-stopwatch"></i> Státusz</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($matches as $match):
            $matchId = $match['id'] ?? 0;
            $leagueName = $match['leagueName'] ?? 'Ismeretlen';
            $name = $match['name'] ?? '';
            $startUtc = $match['startDateUtc'] ?? '';
            $isLive = !empty($match['isLive']) ? 1 : 0;
            $liveTime = $match['liveTime'] ?? '-';
            $score = $match['score'] ?? [];
            
            $scoreDisplay = (is_array($score) && count($score) >= 2) 
                ? htmlspecialchars($score[0] . ' - ' . $score[1])
                : '-';

            // Csapatok kinyerése
            $teams = explode(' vs. ', $name);
            if (count($teams) < 2) {
                $teams = explode(' - ', $name);
            }
            $home = htmlspecialchars(trim($teams[0] ?? $name));
            $away = htmlspecialchars(trim($teams[1] ?? ''));

            // Kezdés időpontja
            $startDateTime = new DateTime($startUtc);
            $startDateTime->setTimezone(new DateTimeZone('Europe/Budapest'));
            $startFormatted = $startDateTime->format('H:i');
        ?>
            <tr class="match-row clickable" data-match-id="<?php echo $matchId; ?>">
                <td>
                    <span class="league-name"><?php echo htmlspecialchars($leagueName); ?></span>
                </td>
                <td class="match-cell">
                    <?php if ($away !== ''): ?>
                        <span class="team home-team"><?php echo $home; ?></span>
                        <span class="vs">vs</span>
                        <span class="team away-team"><?php echo $away; ?></span>
                    <?php else: ?>
                        <span class="team"><?php echo $home; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="match-score"><?php echo $scoreDisplay; ?></span>
                </td>
                <td>
                    <span class="start-time"><?php echo $startFormatted; ?></span>
                </td>
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
<?php
?>