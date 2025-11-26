<?php
/**
 * Create Login History Table
 * Version: 1.12.0
 *
 * This script creates the _login_history table for tracking user login events.
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

    // Create _login_history table
    $sql = "CREATE TABLE IF NOT EXISTS `_login_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL COMMENT 'FK to _users.id',
        `username` VARCHAR(255) NOT NULL COMMENT 'Username at time of login',
        `login_time` DATETIME NOT NULL COMMENT 'When the login occurred',
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address (supports IPv6)',
        `user_agent` TEXT DEFAULT NULL COMMENT 'Browser/device user agent string',
        `login_method` VARCHAR(50) DEFAULT 'password' COMMENT 'password, biometric, etc.',
        `status` ENUM('success', 'failed') DEFAULT 'success' COMMENT 'Login attempt status',
        `failure_reason` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for failed login',
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_login_time` (`login_time`),
        KEY `idx_username` (`username`),
        KEY `idx_user_login_time` (`user_id`, `login_time` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login history tracking - v1.12.0'";

    $pdo->exec($sql);

    echo "SUCCESS: _login_history table created successfully!\n";
    echo "\nTable structure:\n";
    echo "- id: Auto-increment primary key\n";
    echo "- user_id: Foreign key to _users table\n";
    echo "- username: Username at login time\n";
    echo "- login_time: Timestamp of login\n";
    echo "- ip_address: Client IP (IPv4/IPv6)\n";
    echo "- user_agent: Browser/device info\n";
    echo "- login_method: password, biometric, etc.\n";
    echo "- status: success or failed\n";
    echo "- failure_reason: Reason if failed\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
