<?php
/**
 * Admin rendszerbeállítások API
 * GET:  összes beállítás lekérdezése
 * POST: beállítások mentése
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('SUPERADMIN');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/audit_helper.php';

header('Content-Type: application/json');

/* ━━━━━ GET ━━━━━ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Reset to defaults
    if (isset($_GET['action']) && $_GET['action'] === 'reset_defaults') {
        $result = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SystemSettings' AND COLUMN_NAME = 'default_value'");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            echo json_encode(['success' => false, 'message' => 'Nincsenek alapértékek mentve.']);
            exit;
        }

        $conn->query("UPDATE SystemSettings SET setting_value = default_value WHERE default_value IS NOT NULL");
        $affected = $conn->affected_rows;

        log_audit('settings_reset', 'system', null, "Összes beállítás visszaállítva alapértékre ($affected módosítás)");

        echo json_encode(['success' => true, 'message' => 'Összes beállítás visszaállítva alapértékre!']);
        exit;
    }

    $result = $conn->query("SELECT setting_key, setting_value, category, label, description, input_type, updated_at FROM SystemSettings ORDER BY FIELD(category, 'deposit', 'withdrawal', 'betting', 'security', 'registration'), setting_key");

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[] = $row;
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

/* ━━━━━ POST ━━━━━ */
$input = json_decode(file_get_contents('php://input'), true);
$updates = $input['settings'] ?? [];

if (empty($updates) || !is_array($updates)) {
    echo json_encode(['success' => false, 'message' => 'Nincs mentendő beállítás.']);
    exit;
}

$validKeys = $conn->query("SELECT setting_key FROM SystemSettings");
$allowed = [];
while ($r = $validKeys->fetch_assoc()) {
    $allowed[] = $r['setting_key'];
}

$changed = 0;
$details = [];

$stmt = $conn->prepare("UPDATE SystemSettings SET setting_value = ? WHERE setting_key = ?");

foreach ($updates as $key => $value) {
    if (!in_array($key, $allowed, true)) continue;

    $val = trim((string)$value);

    // Validálás
    if (!is_numeric($val)) continue;
    $numVal = (float)$val;
    if ($numVal < 0) continue;

    $stmt->bind_param("ss", $val, $key);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $changed++;
        $details[] = "$key=$val";
    }
}
$stmt->close();

if ($changed > 0) {
    log_audit('settings_update', 'system', null, 'Beállítások módosítva (' . $changed . '): ' . implode(', ', $details));
}

echo json_encode(['success' => true, 'message' => $changed . ' beállítás frissítve!']);
