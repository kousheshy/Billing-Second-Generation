<?php
/**
 * Database Migration: Add Expiry Reminder Tracking
 *
 * Creates table to track sent expiry reminders and prevent duplicates
 * Version: 1.7.8
 */

require_once('config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    echo "Starting migration: Creating _expiry_reminders table...\n\n";

    // Create expiry reminders tracking table
    $sql = "CREATE TABLE IF NOT EXISTS `_expiry_reminders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `mac` VARCHAR(17) NOT NULL,
        `username` VARCHAR(100) NOT NULL,
        `full_name` VARCHAR(200) DEFAULT NULL,
        `end_date` DATE NOT NULL,
        `days_before` INT(11) NOT NULL,
        `reminder_date` DATE NOT NULL,
        `sent_at` DATETIME NOT NULL,
        `sent_by` INT(11) NOT NULL,
        `message` TEXT,
        `status` ENUM('sent', 'failed') DEFAULT 'sent',
        `error_message` TEXT,
        PRIMARY KEY (`id`),
        KEY `account_id` (`account_id`),
        KEY `mac` (`mac`),
        KEY `reminder_date` (`reminder_date`),
        KEY `sent_at` (`sent_at`),
        UNIQUE KEY `unique_reminder` (`account_id`, `end_date`, `days_before`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $pdo->exec($sql);
    echo "✓ Table '_expiry_reminders' created successfully\n";

    // Create reminder settings table
    $sql = "CREATE TABLE IF NOT EXISTS `_reminder_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `days_before_expiry` INT(11) NOT NULL DEFAULT 7,
        `message_template` TEXT,
        `auto_send_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `last_sweep_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $pdo->exec($sql);
    echo "✓ Table '_reminder_settings' created successfully\n";

    // Insert default settings for super admin
    $sql = "INSERT INTO `_reminder_settings`
            (`user_id`, `days_before_expiry`, `message_template`, `auto_send_enabled`, `created_at`, `updated_at`)
            VALUES (1, 7, 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.', 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()";

    $pdo->exec($sql);
    echo "✓ Default reminder settings created for admin\n";

    echo "\n✅ Migration completed successfully!\n\n";
    echo "Tables created:\n";
    echo "  - _expiry_reminders: Tracks all sent reminders with deduplication\n";
    echo "  - _reminder_settings: Stores user-specific reminder configuration\n\n";
    echo "Next steps:\n";
    echo "  1. Access Settings tab in dashboard\n";
    echo "  2. Configure 'Days Before Expiry' (default: 7)\n";
    echo "  3. Customize message template with {days}, {name}, {username} variables\n";
    echo "  4. Use 'Send Reminders' button to manually trigger sweep\n\n";

} catch(PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
