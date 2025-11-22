<?php
/**
 * Create SMS Tables for ShowBox Billing Panel
 * Version: 1.8.0
 *
 * Creates tables for SMS configuration, logs, and templates
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

echo "════════════════════════════════════════════════════════════════\n";
echo "  Creating SMS Tables for ShowBox Billing Panel\n";
echo "  Version: 1.8.0\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Create _sms_settings table
    echo "[1/3] Creating _sms_settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_sms_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `api_token` VARCHAR(500) DEFAULT NULL,
        `sender_number` VARCHAR(20) DEFAULT NULL,
        `base_url` VARCHAR(200) DEFAULT 'https://edge.ippanel.com/v1',
        `auto_send_enabled` TINYINT(1) DEFAULT 0,
        `days_before_expiry` INT(11) DEFAULT 7,
        `expiry_template` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _sms_settings table created\n";

    // Create _sms_logs table
    echo "[2/3] Creating _sms_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_sms_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) DEFAULT NULL,
        `mac` VARCHAR(17) DEFAULT NULL,
        `recipient_name` VARCHAR(200) DEFAULT NULL,
        `recipient_number` VARCHAR(20) NOT NULL,
        `message` TEXT NOT NULL,
        `message_type` ENUM('manual', 'expiry_reminder', 'renewal', 'new_account') DEFAULT 'manual',
        `sent_by` INT(11) NOT NULL,
        `sent_at` DATETIME NOT NULL,
        `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
        `api_response` TEXT,
        `bulk_id` VARCHAR(100) DEFAULT NULL,
        `error_message` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `account_id` (`account_id`),
        KEY `mac` (`mac`),
        KEY `recipient_number` (`recipient_number`),
        KEY `sent_by` (`sent_by`),
        KEY `sent_at` (`sent_at`),
        KEY `message_type` (`message_type`),
        KEY `status` (`status`),
        FOREIGN KEY (`sent_by`) REFERENCES `_users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _sms_logs table created\n";

    // Create _sms_templates table
    echo "[3/3] Creating _sms_templates table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_sms_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `name` VARCHAR(200) NOT NULL,
        `template` TEXT NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    $pdo->exec($sql);
    echo "    ✓ _sms_templates table created\n";

    // Insert default SMS settings for admin
    echo "\n[DEFAULTS] Creating default SMS settings...\n";
    $default_expiry_template = 'Dear {name}, your ShowBox subscription expires in {days} days on {expiry_date}. Please renew to continue enjoying our service. Contact: 00447736932888';

    $sql = "INSERT INTO _sms_settings
            (user_id, auto_send_enabled, days_before_expiry, expiry_template, created_at, updated_at)
            VALUES (1, 0, 7, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$default_expiry_template]);
    echo "    ✓ Default SMS settings created for admin\n";
    echo "      - Days before expiry: 7\n";
    echo "      - Auto-send: OFF\n";
    echo "      - Template: Default expiry message\n";

    // Insert default templates
    echo "\n[TEMPLATES] Creating default SMS templates...\n";

    $templates = [
        [
            'name' => 'Expiry Reminder',
            'template' => 'Dear {name}, your ShowBox subscription expires in {days} days on {expiry_date}. Please renew soon. Contact: 00447736932888',
            'description' => 'Sent automatically before account expiry'
        ],
        [
            'name' => 'New Account Welcome',
            'template' => 'Welcome to ShowBox, {name}! Your account is now active. MAC: {mac}, Expires: {expiry_date}. Support: 00447736932888',
            'description' => 'Sent when new account is created'
        ],
        [
            'name' => 'Renewal Confirmation',
            'template' => 'Dear {name}, your ShowBox subscription has been renewed successfully. New expiry date: {expiry_date}. Thank you!',
            'description' => 'Sent when account is renewed'
        ],
        [
            'name' => 'Payment Reminder',
            'template' => 'Dear {name}, your payment is due. Please renew your ShowBox subscription before {expiry_date}. Contact: 00447736932888',
            'description' => 'Payment reminder message'
        ]
    ];

    foreach ($templates as $template) {
        $sql = "INSERT INTO _sms_templates (user_id, name, template, description, created_at, updated_at)
                VALUES (1, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$template['name'], $template['template'], $template['description']]);
        echo "    ✓ Template created: {$template['name']}\n";
    }

    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "  ✅ SMS TABLES CREATED SUCCESSFULLY!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "Tables created:\n";
    echo "  ✓ _sms_settings (SMS API configuration)\n";
    echo "  ✓ _sms_logs (SMS history and tracking)\n";
    echo "  ✓ _sms_templates (Reusable message templates)\n\n";

    echo "📝 Next Steps:\n";
    echo "  1. Configure SMS API in Messaging tab\n";
    echo "  2. Add your Faraz SMS API token and sender number\n";
    echo "  3. Test sending SMS to verify configuration\n";
    echo "  4. Enable automatic expiry reminders\n\n";

} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
