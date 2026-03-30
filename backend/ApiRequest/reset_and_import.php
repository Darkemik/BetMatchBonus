<?php
require_once dirname(__DIR__) . "/connect.php";

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

try {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    $conn->query("TRUNCATE TABLE Events;");
    $conn->query("TRUNCATE TABLE Competitions;");
    $conn->query("TRUNCATE TABLE Countries;");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

    echo "Táblák ürítve.\n";

    ob_start();
    require __DIR__ . "/sync_competitions_and_events.php";
    $out = trim(ob_get_clean());

    echo $out . "\n";

    $json = json_decode($out, true);
    if (is_array($json) && isset($json['success']) && $json['success'] === false) {
        throw new RuntimeException($json['error'] ?? 'Ismeretlen import hiba');
    }

    echo "Reset + import sikeres.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "HIBA: " . $e->getMessage() . "\n";
}