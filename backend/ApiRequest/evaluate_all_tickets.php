<?php
/**
 * EVALUATE_ALL_TICKETS.PHP - Az ÖSSZES user ÖSSZES nyitott szelvényét kiértékeli.
 * 
 * Használat:
 * - Cron job: php evaluate_all_tickets.php
 * - Browser/Admin: GET http://localhost/BetMatchBonus/backend/ApiRequest/evaluate_all_tickets.php
 * 
 * Ajánlott cron beállítás (Windows Task Scheduler vagy Linux cron):
 * Minden 2 percben: * /2 * * * * php C:\xampp\htdocs\BetMatchBonus\backend\ApiRequest\evaluate_all_tickets.php
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
