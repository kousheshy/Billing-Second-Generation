<?php
/**
 * Add Phone Column to _accounts Table
 *
 * This script adds a phone_number column to the _accounts table if it doesn't already exist.
 * Run this once to update the database schema.
 */

include('config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ADD PHONE COLUMN TO _accounts TABLE ===\n\n";

    // Check if column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM _accounts LIKE 'phone_number'");
    $stmt->execute();

    if($stmt->rowCount() > 0) {
        echo "✓ phone_number column already exists in _accounts table\n";
        echo "No changes needed.\n";
    } else {
        echo "Adding phone_number column to _accounts table...\n";

        // Add the column
        $pdo->exec("ALTER TABLE _accounts ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL AFTER email");

        echo "✓ Successfully added phone_number column\n";
        echo "The column has been added after the 'email' column\n";
        echo "Data type: VARCHAR(50), allows NULL values\n";
    }

    echo "\n=== COMPLETE ===\n";

} catch(PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
