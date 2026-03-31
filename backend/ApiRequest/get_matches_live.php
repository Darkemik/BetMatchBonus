<?php
/**
 * GET_MATCHES_LIVE.PHP — Élő meccsek sport szűrőhöz (CSAK DB-ből)
 * 
 * Output: JSON { sports: {sportApiId: liveCount}, sportDetails: {sportApiId: {name, icon}} }
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $result = [
        'sports' => [],
        'sportDetails' => [],
    ];

    // 1) Minden sport részlet (név+ikon)
    $stmtAllSports = $conn->prepare("SELECT api_id, name FROM Sports WHERE api_id IS NOT NULL");
    $stmtAllSports->execute();
    $resAllSports = $stmtAllSports->get_result();

    while ($s = $resAllSports->fetch_assoc()) {
        $sid = (int)$s['api_id'];
        $result['sportDetails'][(string)$sid] = [
            'name' => $s['name'] ?: ("Sport #".$sid),
            'icon' => getSportIcon($sid)
        ];
        $result['sports'][(string)$sid] = 0;
    }
    $stmtAllSports->close();

    // 2) Élő meccs darabszám sportonként (DB-ből)
    $stmtLiveCounts = $conn->prepare("
        SELECT s.api_id AS sport_api_id, COUNT(*) AS live_count
        FROM Events e
        INNER JOIN Sports s ON s.id = e.sport_id
        WHERE e.is_live = 1
        GROUP BY s.api_id
    ");
    $stmtLiveCounts->execute();
    $resLiveCounts = $stmtLiveCounts->get_result();

    while ($r = $resLiveCounts->fetch_assoc()) {
        $sid = (int)$r['sport_api_id'];
        $result['sports'][(string)$sid] = (int)$r['live_count'];

        if (!isset($result['sportDetails'][(string)$sid])) {
            $result['sportDetails'][(string)$sid] = [
                'name' => 'Sport #'.$sid,
                'icon' => getSportIcon($sid)
            ];
        }
    }
    $stmtLiveCounts->close();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'sports' => [],
        'sportDetails' => []
    ], JSON_UNESCAPED_UNICODE);
}