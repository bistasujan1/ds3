<?php
/**
 * DS3 One-time schema setup script.
 * Access via browser: http://local.ds3.com/setup-schema.php
 * DELETE this file after running.
 */
declare(strict_types=1);

// Block remote access
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/config.php';

$dbHost = DB_HOST_NAME;
$dbUser = ADMIN_DB_USER_NAME;
$dbPass = ADMIN_DB_PASSWORD;

echo "<pre>\n";
echo "Connecting to MySQL as {$dbUser}@{$dbHost}...\n";

$conn = new mysqli($dbHost, $dbUser, $dbPass, null, 3306);
if ($conn->connect_error) {
    die("Connection FAILED: " . $conn->connect_error . "\n");
}
echo "Connected OK.\n\n";

// Drop and recreate ds3
$conn->query("DROP DATABASE IF EXISTS ds3");
echo "Dropped ds3.\n";
$conn->query("CREATE DATABASE ds3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Created ds3.\n";
$conn->select_db('ds3');

// Apply schema
echo "Applying 001_initial_schema.sql...\n";
$sql = file_get_contents(__DIR__ . '/database-migrate/001_initial_schema.sql');
if (!$sql) {
    die("ERROR: Could not read schema file.\n");
}

$conn->multi_query($sql);
do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->errno) {
    echo "SQL Error: " . $conn->error . "\n";
}

// Count tables
$res = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='ds3'");
$row = $res->fetch_assoc();
echo "\nTables in ds3: " . $row['cnt'] . "\n";

// List tables
$res2 = $conn->query("SHOW TABLES FROM ds3");
while ($t = $res2->fetch_row()) {
    echo "  - " . $t[0] . "\n";
}

// Check users
echo "\nUsers:\n";
$res3 = $conn->query("SELECT id, email, role, status FROM ds3.users");
if ($res3) {
    while ($u = $res3->fetch_assoc()) {
        echo "  id={$u['id']} email={$u['email']} role={$u['role']} status={$u['status']}\n";
    }
} else {
    echo "  (no users or query failed: " . $conn->error . ")\n";
}

// Ensure audit db
$conn->query("CREATE DATABASE IF NOT EXISTS ds3_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "\nds3_audit database ensured.\n";

$conn->close();
echo "\nDONE. Delete this file: setup-schema.php\n";
echo "</pre>\n";
