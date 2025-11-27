<?php
/**
 * Migration Script: Add Transaction Corrections
 * Version: 1.16.0
 *
 * Adds correction columns to _transactions table for immutable financial records.
 * Transactions are never deleted - only corrected with mandatory comments.
 *
 * Run this script once to add the columns:
 * php scripts/add_transaction_corrections.php
 */

require_once(__DIR__ . '/../config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);
    echo "Connected to database successfully.\n\n";

    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE _transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $columnsToAdd = [
        'correction_amount' => "DECIMAL(10,2) DEFAULT NULL COMMENT 'Correction amount (positive=increase, negative=decrease)'",
        'correction_note' => "TEXT DEFAULT NULL COMMENT 'Mandatory note explaining the correction'",
        'corrected_by' => "INT DEFAULT NULL COMMENT 'User ID who made the correction'",
        'corrected_by_username' => "VARCHAR(100) DEFAULT NULL COMMENT 'Username who made the correction'",
        'corrected_at' => "DATETIME DEFAULT NULL COMMENT 'When the correction was made'",
        'status' => "ENUM('active','corrected','voided') DEFAULT 'active' COMMENT 'Transaction status'"
    ];

    $added = 0;
    $skipped = 0;

    foreach ($columnsToAdd as $column => $definition) {
        if (in_array($column, $columns)) {
            echo "Column '$column' already exists - skipping\n";
            $skipped++;
        } else {
            $sql = "ALTER TABLE _transactions ADD COLUMN $column $definition";
            $pdo->exec($sql);
            echo "Added column '$column'\n";
            $added++;
        }
    }

    // Add index on status for performance
    $indexCheck = $pdo->query("SHOW INDEX FROM _transactions WHERE Key_name = 'idx_status'");
    if ($indexCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE _transactions ADD INDEX idx_status (status)");
        echo "Added index 'idx_status'\n";
    } else {
        echo "Index 'idx_status' already exists - skipping\n";
    }

    // Add index on corrected_at for querying recent corrections
    $indexCheck = $pdo->query("SHOW INDEX FROM _transactions WHERE Key_name = 'idx_corrected_at'");
    if ($indexCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE _transactions ADD INDEX idx_corrected_at (corrected_at)");
        echo "Added index 'idx_corrected_at'\n";
    } else {
        echo "Index 'idx_corrected_at' already exists - skipping\n";
    }

    echo "\n========================================\n";
    echo "Migration complete!\n";
    echo "Added: $added columns\n";
    echo "Skipped: $skipped columns (already exist)\n";
    echo "========================================\n\n";

    echo "New columns:\n";
    echo "- correction_amount: The amount to add/subtract from original amount\n";
    echo "- correction_note: MANDATORY explanation for the correction\n";
    echo "- corrected_by: User ID who made the correction\n";
    echo "- corrected_by_username: Username for display purposes\n";
    echo "- corrected_at: Timestamp of correction\n";
    echo "- status: active (normal), corrected (has correction), voided (nullified)\n";
    echo "\n";
    echo "Net amount = original amount + correction_amount\n";
    echo "If status = 'voided', net amount should be treated as 0\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
