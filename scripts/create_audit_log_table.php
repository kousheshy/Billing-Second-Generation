<?php
/**
 * Create Audit Log Table
 * Version: 1.12.0
 *
 * This script creates the _audit_log table for tracking ALL user actions.
 * IMPORTANT: This table has NO DELETE capability - entries are permanent.
 * Run this once on the server to set up the table.
 */

include(__DIR__ . '/../config.php');

$host = $ub_db_host;
$db = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Create _audit_log table
    $sql = "CREATE TABLE IF NOT EXISTS `_audit_log` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique audit entry ID',
        `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action occurred',
        `user_id` INT(11) NOT NULL COMMENT 'User who performed the action',
        `username` VARCHAR(255) NOT NULL COMMENT 'Username at time of action',
        `user_type` VARCHAR(50) DEFAULT NULL COMMENT 'super_admin, reseller, reseller_admin',
        `action` VARCHAR(100) NOT NULL COMMENT 'Action type: create, update, delete, login, etc.',
        `target_type` VARCHAR(100) NOT NULL COMMENT 'What was affected: account, user, reseller, settings, etc.',
        `target_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID of affected item (if applicable)',
        `target_name` VARCHAR(255) DEFAULT NULL COMMENT 'Name/identifier of affected item',
        `old_value` JSON DEFAULT NULL COMMENT 'Previous value (for updates)',
        `new_value` JSON DEFAULT NULL COMMENT 'New value (for creates/updates)',
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Client IP address (IPv4/IPv6)',
        `user_agent` TEXT DEFAULT NULL COMMENT 'Browser/device info',
        `details` TEXT DEFAULT NULL COMMENT 'Additional details or notes',
        PRIMARY KEY (`id`),
        KEY `idx_timestamp` (`timestamp`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action` (`action`),
        KEY `idx_target_type` (`target_type`),
        KEY `idx_target_id` (`target_id`(191)),
        KEY `idx_user_timestamp` (`user_id`, `timestamp` DESC),
        KEY `idx_action_timestamp` (`action`, `timestamp` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Permanent audit log - NO DELETE ALLOWED - v1.12.0'";

    $pdo->exec($sql);

    echo "SUCCESS: _audit_log table created successfully!\n";
    echo "\n===========================================\n";
    echo "SECURITY NOTE: This table is PERMANENT.\n";
    echo "No one can delete audit entries - not even super admin.\n";
    echo "===========================================\n";
    echo "\nTable structure:\n";
    echo "- id: Auto-increment primary key (BIGINT for large volume)\n";
    echo "- timestamp: When action occurred\n";
    echo "- user_id: Who performed the action\n";
    echo "- username: Username at action time\n";
    echo "- user_type: super_admin, reseller, reseller_admin\n";
    echo "- action: create, update, delete, login, logout, etc.\n";
    echo "- target_type: account, user, reseller, settings, etc.\n";
    echo "- target_id: ID of affected item\n";
    echo "- target_name: Name of affected item\n";
    echo "- old_value: Previous value (JSON)\n";
    echo "- new_value: New value (JSON)\n";
    echo "- ip_address: Client IP\n";
    echo "- user_agent: Browser info\n";
    echo "- details: Additional notes\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
