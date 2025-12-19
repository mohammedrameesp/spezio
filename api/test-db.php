<?php
/**
 * Database Connection Test - DELETE THIS FILE AFTER TESTING
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h2>Database Connection Test</h2>";
echo "<pre>";

echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_PASS: " . (DB_PASS ? '[SET]' : '[EMPTY]') . "\n\n";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✓ Database connection successful!\n\n";

    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    // Check rooms
    echo "\n";
    $rooms = $pdo->query("SELECT id, name, is_active FROM rooms")->fetchAll(PDO::FETCH_ASSOC);
    echo "Rooms found: " . count($rooms) . "\n";
    foreach ($rooms as $room) {
        echo "  - [{$room['id']}] {$room['name']} (active: {$room['is_active']})\n";
    }

} catch (PDOException $e) {
    echo "✗ Connection FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p style='color:red;font-weight:bold;'>DELETE THIS FILE AFTER TESTING!</p>";
