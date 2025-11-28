<?php
/**
 * Create STB Action Logs Table (v1.17.4)
 *
 * This script creates the _stb_action_logs table for permanent logging
 * of all STB device control actions (events and messages)
 *
 * Run once: php scripts/create_stb_action_logs_table.php
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4",
        $ub_db_username,
        $ub_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Creating _stb_action_logs table...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `_stb_action_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `username` VARCHAR(100) NOT NULL,
        `action_type` ENUM('event', 'message') NOT NULL,
        `mac_address` VARCHAR(17) NOT NULL,
        `action_detail` VARCHAR(255) NOT NULL COMMENT 'Event type or message content preview',
        `full_message` TEXT NULL COMMENT 'Full message content for messages',
        `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
        `error_message` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_mac` (`mac_address`),
        INDEX `idx_action_type` (`action_type`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_user_created` (`user_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);

    echo "Table _stb_action_logs created successfully!\n";

    // Check if table has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM _stb_action_logs");
    $result = $stmt->fetch();
    echo "Current records: " . $result['count'] . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone!\n";
?>
