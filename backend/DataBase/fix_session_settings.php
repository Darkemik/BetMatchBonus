<?php
require_once __DIR__ . '/../connect.php';

// Visszaállítás: session_timeout = 30 (inaktivitás), lockout = 60
$conn->query("UPDATE SystemSettings SET setting_value = '30', description = 'Inaktivitási időkorlát (perc)', label = 'Inaktivitási időkorlát (perc)' WHERE setting_key = 'session_timeout_minutes'");
echo "session_timeout_minutes -> 30: " . $conn->affected_rows . " sor\n";

$conn->query("UPDATE SystemSettings SET setting_value = '60' WHERE setting_key = 'login_lockout_minutes'");
echo "login_lockout_minutes -> 60: " . $conn->affected_rows . " sor\n";

// Új beállítás: teljes munkamenet időkorlát (60 perc)
$conn->query("INSERT IGNORE INTO SystemSettings (setting_key, setting_value, description, category, label, input_type) VALUES ('session_max_duration_minutes', '60', 'Teljes munkamenet időkorlát (perc)', 'security', 'Munkamenet időkorlát (perc)', 'number')");
echo "session_max_duration_minutes: " . $conn->affected_rows . " sor\n";

echo "Done!\n";
