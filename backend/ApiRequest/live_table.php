<?php
require_once "connect.php";

$sql = "
SELECT 
    m.name AS match_name,
    m.start_utc,
    m.live_time,
    c.name AS country_name,
    ch.name AS championship_name
FROM Matches m
JOIN Championships ch ON m.championship_id = ch.id
JOIN Countries c ON ch.country_code = c.code
WHERE m.sport_id = 66
  AND m.is_live = 1
ORDER BY m.start_utc
";

$res = $conn->query($sql);

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;"></i><br>Jelenleg nincs élő mérkőzés.</div>';
    exit;
}
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Ország</th>
            <th><i class="fas fa-trophy"></i> Bajnokság</th>
            <th><i class="fas fa-futbol"></i> Meccs</th>
            <th><i class="fas fa-clock"></i> Kezdés</th>
            <th><i class="fas fa-stopwatch"></i> Élő idő</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $res->fetch_assoc()):
            $liveTimeRaw = $row['live_time'];
            $timeDisplay = ($liveTimeRaw !== null && $liveTimeRaw !== '') ? htmlspecialchars($liveTimeRaw) : '-';

            $matchParts = explode(' - ', $row['match_name'], 2);
            $home = htmlspecialchars($matchParts[0] ?? $row['match_name']);
            $away = isset($matchParts[1]) ? htmlspecialchars($matchParts[1]) : '';

            $startFormatted = date('H:i', strtotime($row['start_utc']));
        ?>
            <tr>
                <td>
                    <span class="country-name"><?= htmlspecialchars($row['country_name']) ?></span>
                </td>
                <td>
                    <span class="league-name"><?= htmlspecialchars($row['championship_name']) ?></span>
                </td>
                <td class="match-cell">
                    <?php if ($away !== ''): ?>
                        <span class="team home-team"><?= $home ?></span>
                        <span class="vs">vs</span>
                        <span class="team away-team"><?= $away ?></span>
                    <?php else: ?>
                        <span class="team"><?= $home ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="start-time"><?= $startFormatted ?></span>
                </td>
                <td class="live-time-cell">
                    <span class="live-dot"></span>
                    <span class="live-time-value"><?= $timeDisplay ?></span>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>