<?php
/**
 * Create Complete Database Schema for ShowBox Billing Panel
 * Version: 1.7.9
 */

require_once(__DIR__ . '/../config.php');

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

echo "════════════════════════════════════════════════════════════════\n";
echo "  Creating ShowBox Billing Panel Database Schema\n";
echo "  Version: 1.7.9\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Create _users table
    echo "[1/7] Creating _users table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(200) DEFAULT NULL,
        `email` VARCHAR(200) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `super_user` TINYINT(1) DEFAULT 0,
        `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0|0|0',
        `is_reseller_admin` TINYINT(1) DEFAULT 0,
        `balance` DECIMAL(10,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _users table created\n";

    // Create _accounts table
    echo "[2/7] Creating _accounts table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_accounts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `mac` VARCHAR(17) NOT NULL,
        `username` VARCHAR(100) DEFAULT NULL,
        `full_name` VARCHAR(200) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `reseller` INT(11) DEFAULT NULL,
        `plan_id` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `mac` (`mac`),
        KEY `reseller` (`reseller`),
        KEY `end_date` (`end_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _accounts table created\n";

    // Create _plans table
    echo "[3/7] Creating _plans table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_plans` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(200) NOT NULL,
        `tariff_id` INT(11) DEFAULT NULL,
        `duration_days` INT(11) NOT NULL,
        `price_gbp` DECIMAL(10,2) DEFAULT 0.00,
        `price_usd` DECIMAL(10,2) DEFAULT 0.00,
        `price_eur` DECIMAL(10,2) DEFAULT 0.00,
        `price_irr` DECIMAL(10,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _plans table created\n";

    // Create _transactions table
    echo "[4/7] Creating _transactions table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `type` ENUM('credit', 'debit') NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'GBP',
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _transactions table created\n";

    // Create _currencies table
    echo "[5/7] Creating _currencies table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_currencies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(10) NOT NULL,
        `symbol` VARCHAR(10) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `enabled` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _currencies table created\n";

    // Insert default currencies
    $pdo->exec("INSERT IGNORE INTO _currencies (code, symbol, name, enabled) VALUES
        ('GBP', '£', 'British Pound', 1),
        ('USD', '$', 'US Dollar', 1),
        ('EUR', '€', 'Euro', 1),
        ('IRR', '﷼', 'Iranian Rial', 1)");
    echo "    ✓ Default currencies inserted\n";

    // Create _expiry_reminders table (already exists but ensure it's there)
    echo "[6/7] Creating _expiry_reminders table...\n";
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
        UNIQUE KEY `unique_reminder_mac` (`mac`, `end_date`, `days_before`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _expiry_reminders table created\n";

    // Create _reminder_settings table (already exists but ensure it's there)
    echo "[7/7] Creating _reminder_settings table...\n";
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
    echo "    ✓ _reminder_settings table created\n";

    // Insert default admin user (password: admin)
    echo "\n[ADMIN] Creating default admin user...\n";
    $admin_password = md5('admin');
    $sql = "INSERT IGNORE INTO _users
            (id, username, password, full_name, email, super_user, permissions, balance, created_at)
            VALUES
            (1, 'admin', ?, 'System Administrator', 'admin@showbox.local', 1, '1|1|1|1|1|1|1', 0.00, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_password]);
    echo "    ✓ Admin user created\n";
    echo "    Username: admin\n";
    echo "    Password: admin\n";
    echo "    ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!\n";

    // Insert default reminder settings for admin
    echo "\n[SETTINGS] Creating default reminder settings...\n";
    $default_template = 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.';
    $sql = "INSERT IGNORE INTO _reminder_settings
            (user_id, days_before_expiry, message_template, auto_send_enabled, created_at, updated_at)
            VALUES (1, 7, ?, 0, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$default_template]);
    echo "    ✓ Default reminder settings created\n";

    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "  ✅ DATABASE SCHEMA CREATED SUCCESSFULLY!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "Tables created:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }

    echo "\n📝 Next Steps:\n";
    echo "  1. Access: http://192.168.15.230\n";
    echo "  2. Login: admin / admin\n";
    echo "  3. Change password immediately!\n";
    echo "  4. Configure Stalker Portal sync in Settings\n\n";

} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
