<?php
/**
 * Rendszerbeállítások helper.
 * Használat:
 *   require_once __DIR__ . '/../Auth/settings_helper.php';
 *   $minDeposit = get_setting('min_deposit', 3000);
 */

function get_setting(string $key, $default = null) {
    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }

    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("SELECT setting_value FROM SystemSettings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $val = $row ? $row['setting_value'] : $default;
    $cache[$key] = $val;

    return $val;
}

function get_setting_int(string $key, int $default = 0): int {
    return (int) get_setting($key, $default);
}

function get_setting_float(string $key, float $default = 0.0): float {
    return (float) get_setting($key, $default);
}
