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
    ORDER BY comp.name ASC, e.start_time ASC
");

if (!$stmt) {
    echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
    exit;
}

$stmt->bind_param("i", $sport_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő meccs ehhez a sporthoz.</div>';
    $stmt->close();
    exit;
}

$matches = [];
while ($row = $res->fetch_assoc()) {
    $matches[] = $row;
}
$stmt->close();
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

            // Kezdés időpont
            $startFormatted = '';
            if (!empty($row['start_time'])) {
                $startFormatted = date('H:i', strtotime($row['start_time']));
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
                        <button class="btn-add-bet" data-match-id="<?php echo $matchId; ?>" title="Fogadások megtekintése">
                            <i class="fas fa-plus"></i> Fogadás
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>