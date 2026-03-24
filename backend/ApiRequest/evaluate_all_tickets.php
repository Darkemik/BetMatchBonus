<?php
/**
 * EVALUATE_ALL_TICKETS.PHP - Átirányítás a check_bets.php?action=evaluate_all -ra
 * Ez a fájl megmaradt a visszafelé kompatibilitás miatt (cron job, admin link).
 * A tényleges logika a check_bets.php-ban van.
 */
require_once __DIR__ . "/connect.php";
require_once __DIR__ . "/check_bets.php";

header('Content-Type: application/json; charset=utf-8');

$startTime = microtime(true);
$evaluatedUsers = evaluateAllOpenTickets($conn);
$elapsed = round((microtime(true) - $startTime) * 1000);

echo json_encode([
    'status' => 'ok',
    'message' => "Kiértékelés kész: $evaluatedUsers user szelvényei ellenőrizve.",
    'evaluated_users' => $evaluatedUsers,
    'elapsed_ms' => $elapsed,
    'timestamp' => date('Y-m-d H:i:s')
]);
