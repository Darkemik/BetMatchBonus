<?php
/**
 * GET_ESPORT_GAME_TAGS.PHP — eSport játék-tag számláló
 * 
 * Output: JSON { tags: { "lol": { name, icon, liveCount, todayCount }, ... }, "other": { ... } }
 * Csak sport_api_id=145 bajnokságokra vonatkozik.
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $tags = [];

    // Inicializálás a config-ból
    foreach (ESPORT_GAME_TAGS as $tag => $conf) {
        $tags[$tag] = [
            'name' => $conf['name'],
            'icon' => $conf['icon'],
            'liveCount' => 0,
            'todayCount' => 0,
        ];
    }
    $tags['other'] = [
        'name' => 'Egyéb',
        'icon' => 'fa-gamepad',
        'liveCount' => 0,
        'todayCount' => 0,
    ];

    $fromUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $toUtc   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    // Élő meccsek game_tag-enként
    $stmtLive = $conn->prepare("
        SELECT COALESCE(NULLIF(ch.game_tag, ''), 'other') AS gtag, COUNT(*) AS cnt
        FROM Events e
        JOIN Sports s ON e.sport_id = s.id
        JOIN Competitions ch ON e.competition_id = ch.id
        WHERE s.api_id = 145
          AND e.is_live = 1
        GROUP BY gtag
    ");
    $stmtLive->execute();
    $resLive = $stmtLive->get_result();
    while ($row = $resLive->fetch_assoc()) {
        $g = $row['gtag'];
        if (!isset($tags[$g])) $g = 'other';
        $tags[$g]['liveCount'] += (int)$row['cnt'];
    }
    $stmtLive->close();

    // Mai/közelgő meccsek game_tag-enként
    $stmtToday = $conn->prepare("
        SELECT COALESCE(NULLIF(ch.game_tag, ''), 'other') AS gtag, COUNT(*) AS cnt
        FROM Events e
        JOIN Sports s ON e.sport_id = s.id
        JOIN Competitions ch ON e.competition_id = ch.id
        WHERE s.api_id = 145
          AND e.start_time BETWEEN ? AND ?
          AND e.name IS NOT NULL
          AND TRIM(e.name) != ''
        GROUP BY gtag
    ");
    $stmtToday->bind_param('ss', $fromUtc, $toUtc);
    $stmtToday->execute();
    $resToday = $stmtToday->get_result();
    while ($row = $resToday->fetch_assoc()) {
        $g = $row['gtag'];
        if (!isset($tags[$g])) $g = 'other';
        $tags[$g]['todayCount'] += (int)$row['cnt'];
    }
    $stmtToday->close();

    echo json_encode(['tags' => $tags], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'tags' => []], JSON_UNESCAPED_UNICODE);
}
