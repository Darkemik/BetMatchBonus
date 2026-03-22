<?php
/**
 * LIVE_HELPER.PHP - Közös segédfüggvények az élő meccsek adatbázis-kezeléséhez
 * 
 * Használja: live_table.php, get_matches_live.php
 * Célja: duplikált kód elkerülése, egységes DB szinkronizálás
 */

if (!isset($conn)) {
    require_once __DIR__ . "/connect.php";
}

/**
 * Élő meccsek score/live_time frissítése az adatbázisban.
 * Csak meglévő Events sorokat módosít, nem hoz létre újakat.
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
 * Korábban élőként jelölt meccsek befejezettnek jelölése,
 * ha már nincsenek az API aktuális élő listájában.
 * 
 * Ha $currentLiveMatches üres → az adott sport ÖSSZES korábban élő meccse befejezett.
 */
function markFinishedMatchesBySport($conn, $currentLiveMatches, $sportApiId) {
    // Sport belső ID
    $stmtSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
    $stmtSport->bind_param("i", $sportApiId);
    $stmtSport->execute();
    $sportRow = $stmtSport->get_result()->fetch_assoc();
    $stmtSport->close();
    if (!$sportRow) return;
    $internalSportId = (int)$sportRow['id'];

    // API-ban jelenleg élő meccsek ID-jei
    $liveApiIds = [];
    if (is_array($currentLiveMatches)) {
        foreach ($currentLiveMatches as $m) {
            if (isset($m['id'])) {
                $liveApiIds[] = (int)$m['id'];
            }
        }
    }

    if (empty($liveApiIds)) {
        // Nincs egyetlen élő meccs sem ebben a sportban → mind befejezett
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
 * Minden sportban érvényes biztonsági háló.
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
