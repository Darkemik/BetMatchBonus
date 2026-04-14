<?php
require_once __DIR__ . '/../connect.php';

// Session timeout: 30 -> 60 perc
$conn->query("UPDATE SystemSettings SET setting_value = '60', description = 'Munkamenet időkorlát (perc)', label = 'Munkamenet időkorlát (perc)' WHERE setting_key = 'session_timeout_minutes'");
echo "session_timeout_minutes -> 60: " . $conn->affected_rows . " sor frissitve\n";

// Login lockout: 60 -> 30 perc
$conn->query("UPDATE SystemSettings SET setting_value = '30' WHERE setting_key = 'login_lockout_minutes'");
echo "login_lockout_minutes -> 30: " . $conn->affected_rows . " sor frissitve\n";

echo "Done!\n";
