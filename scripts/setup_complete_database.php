<?php
/**
 * ============================================================================
 * ShowBox Billing Panel - Complete Database Setup Script
 * ============================================================================
 * Version: 1.14.0
 * Last Updated: 2025-11-27
 *
 * This script creates ALL required tables for a fresh installation.
 * Run this ONCE on a new system to set up the complete database.
 *
 * Usage:
 *   php scripts/setup_complete_database.php
 *
 * Prerequisites:
 *   1. MySQL 5.7+ or MariaDB 10.3+
 *   2. Empty database created (showboxt_panel)
 *   3. config.php properly configured with database credentials
 * ============================================================================
 */

// Ensure we're running from command line or with proper permissions
if (php_sapi_name() !== 'cli') {
    // If running from web, require authentication
    session_start();
    if (!isset($_SESSION['login']) || $_SESSION['super_user'] != 1) {
        die('Access denied. Run from CLI or login as super admin.');
    }
}

require_once(__DIR__ . '/../config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Output helper functions
function success($msg) { echo "    ✓ $msg\n"; }
function info($msg) { echo "    ℹ $msg\n"; }
function warn($msg) { echo "    ⚠ $msg\n"; }
function err($msg) { echo "    ✗ $msg\n"; }
function header_line($msg) { echo "\n════════════════════════════════════════════════════════════════\n  $msg\n════════════════════════════════════════════════════════════════\n\n"; }
function step($num, $total, $msg) { echo "[$num/$total] $msg\n"; }

header_line("ShowBox Billing Panel - Complete Database Setup\nVersion: 1.14.0");

try {
    // First connect without database to create it if needed
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Create database if not exists
    step(1, 19, "Creating database '$db' if not exists...");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    success("Database '$db' ready");

    // Select the database
    $pdo->exec("USE `$db`");

    // =========================================================================
    // CORE TABLES
    // =========================================================================

    step(2, 19, "Creating _users table (resellers & admins)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL COMMENT 'MD5 hash',
        `name` VARCHAR(200) DEFAULT NULL COMMENT 'Display name (legacy field)',
        `full_name` VARCHAR(200) DEFAULT NULL COMMENT 'Full name',
        `email` VARCHAR(200) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `super_user` TINYINT(1) DEFAULT 0 COMMENT '0=reseller, 1=admin',
        `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0|0|0' COMMENT 'can_edit|can_add|is_reseller_admin|can_delete|can_send_stb|can_view_stb|reserved',
        `is_reseller_admin` TINYINT(1) DEFAULT 0 COMMENT 'Can manage other resellers',
        `is_observer` TINYINT(1) DEFAULT 0 COMMENT 'Read-only mode',
        `balance` DECIMAL(10,2) DEFAULT 0.00,
        `currency` VARCHAR(10) DEFAULT 'GBP',
        `max_users` INT(11) DEFAULT 0 COMMENT 'Max accounts this reseller can create',
        `theme` VARCHAR(50) DEFAULT 'dark' COMMENT 'UI theme preference',
        `timestamp` INT(11) DEFAULT NULL COMMENT 'Legacy timestamp field',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        INDEX `idx_super_user` (`super_user`),
        INDEX `idx_is_reseller_admin` (`is_reseller_admin`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table - v1.14.0'");
    success("_users table created");

    step(3, 19, "Creating _accounts table (IPTV customer accounts)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_accounts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `mac` VARCHAR(17) NOT NULL COMMENT 'MAC address (XX:XX:XX:XX:XX:XX)',
        `username` VARCHAR(100) DEFAULT NULL COMMENT 'STB username',
        `password` VARCHAR(255) DEFAULT NULL COMMENT 'STB password',
        `full_name` VARCHAR(200) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL COMMENT 'Phone number for SMS',
        `email` VARCHAR(200) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `end_date` DATE DEFAULT NULL COMMENT 'Subscription expiry date',
        `status` TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
        `reseller` INT(11) DEFAULT NULL COMMENT 'FK to _users.id',
        `plan_id` INT(11) DEFAULT NULL COMMENT 'FK to _plans.id',
        `tariff_plan` VARCHAR(200) DEFAULT NULL COMMENT 'Plan name from Stalker',
        `stb_type` VARCHAR(50) DEFAULT NULL,
        `server` TINYINT(1) DEFAULT 1 COMMENT '1=Server1, 2=Server2',
        `timestamp` INT(11) DEFAULT NULL COMMENT 'Legacy timestamp',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `mac` (`mac`),
        INDEX `idx_reseller` (`reseller`),
        INDEX `idx_end_date` (`end_date`),
        INDEX `idx_status` (`status`),
        INDEX `idx_plan_id` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IPTV accounts - v1.14.0'");
    success("_accounts table created");

    step(4, 19, "Creating _plans table (subscription plans)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_plans` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(200) NOT NULL,
        `tariff_id` INT(11) DEFAULT NULL COMMENT 'Stalker Portal tariff ID',
        `duration_days` INT(11) NOT NULL DEFAULT 30,
        `price_gbp` DECIMAL(10,2) DEFAULT 0.00,
        `price_usd` DECIMAL(10,2) DEFAULT 0.00,
        `price_eur` DECIMAL(10,2) DEFAULT 0.00,
        `price_irr` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Iranian Rial (larger values)',
        `category` VARCHAR(100) DEFAULT NULL COMMENT 'Plan category for filtering',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_tariff_id` (`tariff_id`),
        INDEX `idx_category` (`category`),
        INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Subscription plans - v1.14.0'");
    success("_plans table created");

    step(5, 19, "Creating _transactions table (financial transactions)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `for_user` INT(11) NOT NULL COMMENT 'FK to _users.id (reseller)',
        `type` ENUM('credit', 'debit') NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'GBP',
        `description` TEXT,
        `related_account` INT(11) DEFAULT NULL COMMENT 'FK to _accounts.id if applicable',
        `created_by` INT(11) DEFAULT NULL COMMENT 'Who created this transaction',
        `timestamp` INT(11) DEFAULT NULL COMMENT 'Legacy timestamp',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_for_user` (`for_user`),
        INDEX `idx_type` (`type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Financial transactions - v1.14.0'");
    success("_transactions table created");

    step(6, 19, "Creating _currencies table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_currencies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(10) NOT NULL,
        `symbol` VARCHAR(10) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `enabled` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Currency definitions - v1.14.0'");
    // Insert default currencies
    $pdo->exec("INSERT IGNORE INTO _currencies (code, symbol, name, enabled) VALUES
        ('GBP', '£', 'British Pound', 1),
        ('USD', '\$', 'US Dollar', 1),
        ('EUR', '€', 'Euro', 1),
        ('IRR', '﷼', 'Iranian Rial', 1)");
    success("_currencies table created with defaults");

    // =========================================================================
    // STB REMINDER TABLES
    // =========================================================================

    step(7, 19, "Creating _expiry_reminders table (STB reminder history)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_expiry_reminders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `mac` VARCHAR(17) NOT NULL,
        `username` VARCHAR(100) DEFAULT NULL,
        `full_name` VARCHAR(200) DEFAULT NULL,
        `end_date` DATE NOT NULL,
        `days_before` INT(11) NOT NULL,
        `reminder_date` DATE NOT NULL,
        `sent_at` DATETIME NOT NULL,
        `sent_by` INT(11) NOT NULL COMMENT 'User who triggered',
        `message` TEXT,
        `status` ENUM('sent', 'failed') DEFAULT 'sent',
        `error_message` TEXT,
        PRIMARY KEY (`id`),
        INDEX `idx_account_id` (`account_id`),
        INDEX `idx_mac` (`mac`),
        INDEX `idx_reminder_date` (`reminder_date`),
        INDEX `idx_sent_at` (`sent_at`),
        UNIQUE KEY `unique_reminder` (`mac`, `end_date`, `days_before`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='STB reminder history - v1.14.0'");
    success("_expiry_reminders table created");

    step(8, 19, "Creating _reminder_settings table (per-user reminder config)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_reminder_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `days_before_expiry` INT(11) NOT NULL DEFAULT 7,
        `message_template` TEXT,
        `auto_send_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `last_sweep_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reminder settings per user - v1.14.0'");
    success("_reminder_settings table created");

    // =========================================================================
    // SMS TABLES
    // =========================================================================

    step(9, 19, "Creating _sms_settings table (SMS API configuration)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_sms_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `api_token` VARCHAR(500) DEFAULT NULL COMMENT 'Faraz SMS API token',
        `sender_number` VARCHAR(20) DEFAULT NULL COMMENT 'SMS sender number',
        `base_url` VARCHAR(200) DEFAULT 'https://edge.ippanel.com/v1',
        `auto_send_enabled` TINYINT(1) DEFAULT 0,
        `enable_multistage_reminders` TINYINT(1) DEFAULT 1 COMMENT 'Enable 7/3/1 day reminders',
        `days_before_expiry` INT(11) DEFAULT 7,
        `expiry_template` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS settings per user - v1.14.0'");
    success("_sms_settings table created");

    step(10, 19, "Creating _sms_logs table (SMS history)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_sms_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) DEFAULT NULL,
        `mac` VARCHAR(17) DEFAULT NULL,
        `recipient_name` VARCHAR(200) DEFAULT NULL,
        `recipient_number` VARCHAR(20) NOT NULL,
        `message` TEXT NOT NULL,
        `message_type` ENUM('manual', 'expiry_reminder', 'renewal', 'new_account', 'welcome') DEFAULT 'manual',
        `sent_by` INT(11) NOT NULL,
        `sent_at` DATETIME NOT NULL,
        `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
        `api_response` TEXT,
        `bulk_id` VARCHAR(100) DEFAULT NULL,
        `error_message` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_account_id` (`account_id`),
        INDEX `idx_mac` (`mac`),
        INDEX `idx_recipient` (`recipient_number`),
        INDEX `idx_sent_by` (`sent_by`),
        INDEX `idx_sent_at` (`sent_at`),
        INDEX `idx_message_type` (`message_type`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS sending history - v1.14.0'");
    success("_sms_logs table created");

    step(11, 19, "Creating _sms_templates table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_sms_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `name` VARCHAR(200) NOT NULL,
        `template` TEXT NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS message templates - v1.14.0'");
    success("_sms_templates table created");

    step(12, 19, "Creating _sms_reminder_tracking table (multi-stage tracking)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_sms_reminder_tracking` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `account_id` INT NOT NULL,
        `mac` VARCHAR(20) DEFAULT NULL,
        `reminder_stage` ENUM('7days', '3days', '1day', 'expired') NOT NULL,
        `sent_at` DATETIME NOT NULL,
        `end_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_account_stage` (`account_id`, `reminder_stage`),
        INDEX `idx_mac_stage` (`mac`, `reminder_stage`),
        INDEX `idx_end_date` (`end_date`),
        UNIQUE KEY `unique_reminder` (`account_id`, `reminder_stage`, `end_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multi-stage SMS reminder tracking - v1.14.0'");
    success("_sms_reminder_tracking table created");

    // =========================================================================
    // STALKER PORTAL SETTINGS
    // =========================================================================

    step(13, 19, "Creating _stalker_settings table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_stalker_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT DEFAULT NULL,
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stalker Portal settings - v1.14.0'");
    // Insert default Stalker settings
    $defaults = [
        'server_address' => $SERVER_1_ADDRESS ?? 'http://your-stalker-server',
        'server_2_address' => $SERVER_2_ADDRESS ?? 'http://your-stalker-server',
        'api_username' => $WEBSERVICE_USERNAME ?? 'admin',
        'api_password' => $WEBSERVICE_PASSWORD ?? '',
        'api_base_url' => $WEBSERVICE_BASE_URL ?? 'http://your-stalker-server/stalker_portal/api/',
        'api_2_base_url' => $WEBSERVICE_2_BASE_URL ?? 'http://your-stalker-server/stalker_portal/api/'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO _stalker_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    success("_stalker_settings table created with defaults");

    // =========================================================================
    // AUTHENTICATION & SECURITY TABLES
    // =========================================================================

    step(14, 19, "Creating _webauthn_credentials table (biometric login)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_webauthn_credentials` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `credential_id` TEXT NOT NULL COMMENT 'Base64 credential ID',
        `public_key` TEXT NOT NULL COMMENT 'Base64 public key',
        `counter` INT DEFAULT 0,
        `device_name` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_used` TIMESTAMP NULL,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WebAuthn/Biometric credentials - v1.14.0'");
    success("_webauthn_credentials table created");

    step(15, 19, "Creating _login_history table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_login_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `login_time` DATETIME NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Supports IPv6',
        `user_agent` TEXT DEFAULT NULL,
        `login_method` VARCHAR(50) DEFAULT 'password' COMMENT 'password, biometric',
        `status` ENUM('success', 'failed') DEFAULT 'success',
        `failure_reason` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_login_time` (`login_time`),
        INDEX `idx_username` (`username`),
        INDEX `idx_user_login_time` (`user_id`, `login_time` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login history tracking - v1.14.0'");
    success("_login_history table created");

    step(16, 19, "Creating _audit_log table (permanent action log)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_audit_log` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `user_id` INT(11) NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `user_type` VARCHAR(50) DEFAULT NULL COMMENT 'super_admin, reseller, reseller_admin',
        `action` VARCHAR(100) NOT NULL COMMENT 'create, update, delete, send, etc.',
        `target_type` VARCHAR(100) NOT NULL COMMENT 'account, user, stb_message, credit, etc.',
        `target_id` VARCHAR(255) DEFAULT NULL,
        `target_name` VARCHAR(255) DEFAULT NULL,
        `old_value` JSON DEFAULT NULL,
        `new_value` JSON DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `details` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_timestamp` (`timestamp`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_action` (`action`),
        INDEX `idx_target_type` (`target_type`),
        INDEX `idx_target_id` (`target_id`(191)),
        INDEX `idx_user_timestamp` (`user_id`, `timestamp` DESC),
        INDEX `idx_action_timestamp` (`action`, `timestamp` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permanent audit log - NO DELETE - v1.14.0'");
    success("_audit_log table created");

    // =========================================================================
    // PUSH NOTIFICATION TABLES
    // =========================================================================

    step(17, 19, "Creating _push_subscriptions table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_push_subscriptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `endpoint` TEXT NOT NULL COMMENT 'Push service endpoint URL',
        `p256dh` TEXT NOT NULL COMMENT 'Public key for encryption',
        `auth` TEXT NOT NULL COMMENT 'Auth secret',
        `user_agent` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_endpoint` (`endpoint`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web push subscriptions - v1.14.0'");
    success("_push_subscriptions table created");

    step(18, 19, "Creating _push_expiry_tracking table...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_push_expiry_tracking` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `expiry_date` DATE NOT NULL,
        `notified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_account_expiry` (`account_id`, `expiry_date`),
        INDEX `idx_notified_at` (`notified_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Push expiry notification tracking - v1.14.0'");
    success("_push_expiry_tracking table created");

    // =========================================================================
    // APP SETTINGS TABLE
    // =========================================================================

    step(19, 19, "Creating _app_settings table (global settings)...");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_app_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Global app settings - v1.14.0'");
    // Insert default settings
    $pdo->exec("INSERT IGNORE INTO _app_settings (setting_key, setting_value) VALUES
        ('auto_logout_timeout', '5'),
        ('app_version', '1.14.0')");
    success("_app_settings table created with defaults");

    // =========================================================================
    // CREATE DEFAULT ADMIN USER
    // =========================================================================

    header_line("Creating Default Admin User");

    $admin_password = md5('admin');
    $pdo->exec("INSERT IGNORE INTO _users
        (id, username, password, full_name, name, email, super_user, permissions, balance, created_at)
        VALUES
        (1, 'admin', '$admin_password', 'System Administrator', 'Admin', 'admin@showbox.local', 1, '1|1|1|1|1|1|1', 0.00, NOW())");

    success("Admin user created");
    echo "    Username: admin\n";
    echo "    Password: admin\n";
    warn("CHANGE THIS PASSWORD IMMEDIATELY!\n");

    // Create default reminder settings for admin
    $default_template = 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service.';
    $stmt = $pdo->prepare("INSERT IGNORE INTO _reminder_settings (user_id, days_before_expiry, message_template, auto_send_enabled, created_at, updated_at) VALUES (1, 7, ?, 0, NOW(), NOW())");
    $stmt->execute([$default_template]);
    success("Default reminder settings created");

    // Create default SMS settings for admin
    $default_sms_template = 'Dear {name}, your ShowBox subscription expires in {days} days on {expiry_date}. Please renew soon.';
    $stmt = $pdo->prepare("INSERT IGNORE INTO _sms_settings (user_id, auto_send_enabled, days_before_expiry, expiry_template, created_at, updated_at) VALUES (1, 0, 7, ?, NOW(), NOW())");
    $stmt->execute([$default_sms_template]);
    success("Default SMS settings created");

    // =========================================================================
    // SUMMARY
    // =========================================================================

    header_line("DATABASE SETUP COMPLETE!");

    echo "Tables created:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }

    echo "\n" . count($tables) . " tables created successfully.\n";

    echo "\n📝 Next Steps:\n";
    echo "  1. Access the panel at your server URL\n";
    echo "  2. Login: admin / admin\n";
    echo "  3. CHANGE PASSWORD immediately!\n";
    echo "  4. Configure Stalker Portal settings in Settings tab\n";
    echo "  5. Configure SMS settings if needed\n";
    echo "  6. Sync accounts from Stalker Portal\n\n";

} catch(PDOException $e) {
    err("Database Error: " . $e->getMessage());
    echo "\nTroubleshooting:\n";
    echo "  - Check config.php has correct database credentials\n";
    echo "  - Ensure MySQL/MariaDB is running\n";
    echo "  - Verify database user has CREATE TABLE privileges\n";
    echo "  - Check if database '$db' exists\n";
    exit(1);
}
?>
