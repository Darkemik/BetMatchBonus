<?php
/**
 * seed_admins.php
 * Inserts one test admin user for each role (MOD, ADMIN, SUPERADMIN).
 * Safe to run multiple times – skips rows that already exist.
 *
 * Credentials:
 *   mod_user   / password123  (role_id = 1 – MOD)
 *   admin_user / password123  (role_id = 2 – ADMIN)
 *   super_user / password123  (role_id = 3 – SUPERADMIN)
 */

require_once __DIR__ . '/../ApiRequest/connect.php';

$admins = [
    [
        'username' => 'mod_user',
        'email'    => 'mod@betmatchbonus.hu',
        'password' => 'password123',
        'role_id'  => 1,
    ],
    [
        'username' => 'admin_user',
        'email'    => 'admin@betmatchbonus.hu',
        'password' => 'password123',
        'role_id'  => 2,
    ],
    [
        'username' => 'super_user',
        'email'    => 'super@betmatchbonus.hu',
        'password' => 'password123',
        'role_id'  => 3,
    ],
];

$checkStmt  = $conn->prepare("SELECT id FROM AdminUsers WHERE username = ? OR email = ? LIMIT 1");
$insertStmt = $conn->prepare(
    "INSERT INTO AdminUsers (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)"
);

$inserted = 0;
$skipped  = 0;

foreach ($admins as $a) {
    // Check for duplicates before inserting
    $checkStmt->bind_param('ss', $a['username'], $a['email']);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->free_result();
        echo "⏭ Skipped (already exists): {$a['username']}\n";
        $skipped++;
        continue;
    }
    $checkStmt->free_result();

    $hash = password_hash($a['password'], PASSWORD_DEFAULT);
    $insertStmt->bind_param('sssi', $a['username'], $a['email'], $hash, $a['role_id']);
    $insertStmt->execute();

    echo "✅ Inserted: {$a['username']} (role_id={$a['role_id']})\n";
    $inserted++;
}

$checkStmt->close();
$insertStmt->close();
$conn->close();

echo "\nDone. Inserted: $inserted, Skipped: $skipped\n";
