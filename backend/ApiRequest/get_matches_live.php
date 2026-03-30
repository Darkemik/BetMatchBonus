<?php
require_once dirname(__DIR__) . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

try {
    // Ezt várja a live.js:
    // {
    //   sports: { sportApiId: liveCount, ... },
    //   sportDetails: { sportApiId: {name, icon}, ... }
    // }

    $sportIcons = [
        66  => 'fa-futbol',
        67  => 'fa-basketball-ball',
        78  => 'fa-bullseye',
        83  => 'fa-swimmer',
        73  => 'fa-hand-rock',
        70  => 'fa-hockey-puck',
        145 => 'fa-gamepad',
        146 => 'fa-futbol',
        147 => 'fa-basketball-ball',
        148 => 'fa-hockey-puck',
        77  => 'fa-table-tennis',
        76  => 'fa-running',
        90  => 'fa-hockey-puck',
        68  => 'fa-baseball-ball',
        69  => 'fa-football-ball',
        71  => 'fa-volleyball-ball',
        72  => 'fa-golf-ball',
        74  => 'fa-fist-raised',
        75  => 'fa-biking',
        79  => 'fa-skiing',
        80  => 'fa-snowflake',
        84  => 'fa-table-tennis',
        85  => 'fa-chess',
        109 => 'fa-volleyball-ball',
        110 => 'fa-futbol',
        138 => 'fa-running',
        151 => 'fa-trophy',
    ];

    $result = [
        'sports' => [],
        'sportDetails' => [],
    ];

    // 1) Minden sport részlet (név+ikon), hogy legyen fallback a frontendnek
    $stmtAllSports = $conn->prepare("SELECT api_id, name FROM Sports WHERE api_id IS NOT NULL");
    $stmtAllSports->execute();
    $resAllSports = $stmtAllSports->get_result();

    while ($s = $resAllSports->fetch_assoc()) {
        $sid = (int)$s['api_id'];
        $result['sportDetails'][(string)$sid] = [
            'name' => $s['name'] ?: ("Sport #".$sid),
            'icon' => $sportIcons[$sid] ?? 'fa-trophy'
        ];
        $result['sports'][(string)$sid] = 0; // alapból 0
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

        // ha valamiért nem volt a sportDetails-ben
        if (!isset($result['sportDetails'][(string)$sid])) {
            $result['sportDetails'][(string)$sid] = [
                'name' => 'Sport #'.$sid,
                'icon' => $sportIcons[$sid] ?? 'fa-trophy'
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