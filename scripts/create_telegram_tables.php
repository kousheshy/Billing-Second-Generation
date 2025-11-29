<?php
/**
 * Create Telegram Tables for ShowBox Billing Panel
 * Version: 1.18.0
 *
 * Creates tables for Telegram bot configuration, logs, templates, and notification settings
 *
 * Access Control:
 * - Only super_admin and reseller_admin have access to Telegram tab
 * - Resellers receive notifications only for their own accounts
 * - Reseller admins receive notifications for ALL accounts in the system
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

echo "════════════════════════════════════════════════════════════════\n";
echo "  Creating Telegram Tables for ShowBox Billing Panel\n";
echo "  Version: 1.18.0\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Step 1: Add telegram_chat_id column to _users table
    echo "[1/5] Adding telegram_chat_id to _users table...\n";

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `_users` LIKE 'telegram_chat_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `_users` ADD COLUMN `telegram_chat_id` BIGINT NULL DEFAULT NULL AFTER `theme`");
        $pdo->exec("ALTER TABLE `_users` ADD COLUMN `telegram_linked_at` DATETIME NULL DEFAULT NULL AFTER `telegram_chat_id`");
        echo "    ✓ telegram_chat_id column added to _users\n";
        echo "    ✓ telegram_linked_at column added to _users\n";
    } else {
        echo "    ℹ telegram_chat_id column already exists\n";
    }

    // Step 2: Create _telegram_settings table (Global bot settings)
    echo "[2/5] Creating _telegram_settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_telegram_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bot_token` VARCHAR(100) DEFAULT NULL,
        `bot_username` VARCHAR(100) DEFAULT NULL,
        `webhook_url` VARCHAR(500) DEFAULT NULL,
        `webhook_secret` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "    ✓ _telegram_settings table created\n";

    // Step 3: Create _telegram_notification_settings table (Per-user notification preferences)
    echo "[3/5] Creating _telegram_notification_settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_telegram_notification_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `notify_new_account` TINYINT(1) DEFAULT 1,
        `notify_renewal` TINYINT(1) DEFAULT 1,
        `notify_expiry` TINYINT(1) DEFAULT 1,
        `notify_expired` TINYINT(1) DEFAULT 1,
        `notify_low_balance` TINYINT(1) DEFAULT 1,
        `notify_new_payment` TINYINT(1) DEFAULT 1,
        `notify_login` TINYINT(1) DEFAULT 0,
        `notify_daily_report` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "    ✓ _telegram_notification_settings table created\n";

    // Step 4: Create _telegram_logs table
    echo "[4/5] Creating _telegram_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_telegram_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `chat_id` BIGINT NOT NULL,
        `message` TEXT NOT NULL,
        `message_type` ENUM('manual', 'new_account', 'renewal', 'expiry_reminder', 'expired', 'low_balance', 'new_payment', 'login_alert', 'daily_report', 'broadcast') DEFAULT 'manual',
        `related_account_id` INT(11) DEFAULT NULL,
        `related_account_mac` VARCHAR(17) DEFAULT NULL,
        `telegram_message_id` BIGINT DEFAULT NULL,
        `sent_at` DATETIME NOT NULL,
        `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
        `error_message` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `chat_id` (`chat_id`),
        KEY `sent_at` (`sent_at`),
        KEY `message_type` (`message_type`),
        KEY `status` (`status`),
        FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "    ✓ _telegram_logs table created\n";

    // Step 5: Create _telegram_templates table
    echo "[5/5] Creating _telegram_templates table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `_telegram_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(200) NOT NULL,
        `template_key` VARCHAR(50) NOT NULL,
        `template` TEXT NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `is_system` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `template_key` (`template_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "    ✓ _telegram_templates table created\n";

    // Insert default templates
    echo "\n[TEMPLATES] Creating default Telegram templates...\n";

    $templates = [
        [
            'name' => 'New Account Created',
            'template_key' => 'new_account',
            'template' => "🆕 *حساب جدید ایجاد شد*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 انقضا: {expiry_date}\n💰 پلن: {plan_name}\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent when a new account is created',
            'is_system' => 1
        ],
        [
            'name' => 'Account Renewed',
            'template_key' => 'renewal',
            'template' => "🔄 *حساب تمدید شد*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 انقضای جدید: {expiry_date}\n💰 پلن: {plan_name}\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent when an account is renewed',
            'is_system' => 1
        ],
        [
            'name' => 'Expiry Warning (7 Days)',
            'template_key' => 'expiry_7days',
            'template' => "⚠️ *هشدار انقضا - 7 روز*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 انقضا: {expiry_date}\n⏰ باقی‌مانده: {days} روز\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent 7 days before account expires',
            'is_system' => 1
        ],
        [
            'name' => 'Expiry Warning (3 Days)',
            'template_key' => 'expiry_3days',
            'template' => "🔔 *هشدار فوری انقضا - 3 روز*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 انقضا: {expiry_date}\n⏰ باقی‌مانده: {days} روز\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent 3 days before account expires',
            'is_system' => 1
        ],
        [
            'name' => 'Expiry Warning (1 Day)',
            'template_key' => 'expiry_1day',
            'template' => "🚨 *آخرین هشدار - فردا منقضی می‌شود!*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 انقضا: {expiry_date}\n⏰ باقی‌مانده: {days} روز\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent 1 day before account expires',
            'is_system' => 1
        ],
        [
            'name' => 'Account Expired',
            'template_key' => 'expired',
            'template' => "❌ *حساب منقضی شد*\n\n👤 نام: {name}\n📺 MAC: `{mac}`\n📅 تاریخ انقضا: {expiry_date}\n\n👨‍💼 ریسلر: {reseller_name}",
            'description' => 'Sent when account expires',
            'is_system' => 1
        ],
        [
            'name' => 'Low Balance Warning',
            'template_key' => 'low_balance',
            'template' => "⚠️ *هشدار موجودی کم*\n\n💰 موجودی فعلی: {balance} {currency}\n📊 این مبلغ برای {accounts_possible} حساب کافی است.\n\nلطفاً موجودی خود را شارژ کنید.",
            'description' => 'Sent when reseller balance is low',
            'is_system' => 1
        ],
        [
            'name' => 'New Payment Received',
            'template_key' => 'new_payment',
            'template' => "💰 *پرداخت جدید دریافت شد*\n\n👤 ریسلر: {reseller_name}\n💵 مبلغ: {amount} {currency}\n🏦 بانک: {bank_name}\n📝 توضیحات: {description}\n\n💰 موجودی جدید: {new_balance} {currency}",
            'description' => 'Sent when a reseller payment is added',
            'is_system' => 1
        ],
        [
            'name' => 'Daily Report',
            'template_key' => 'daily_report',
            'template' => "📊 *گزارش روزانه - {date}*\n\n✅ حساب‌های فعال: {active_accounts}\n⚠️ در حال انقضا (7 روز): {expiring_soon}\n❌ منقضی شده امروز: {expired_today}\n🆕 ثبت‌نام جدید: {new_today}\n\n💰 موجودی: {balance} {currency}",
            'description' => 'Daily summary report',
            'is_system' => 1
        ],
        [
            'name' => 'Login Alert',
            'template_key' => 'login_alert',
            'template' => "🔐 *ورود جدید*\n\n👤 کاربر: {username}\n🌐 IP: {ip_address}\n📱 دستگاه: {device}\n🕐 زمان: {login_time}",
            'description' => 'Sent on new login',
            'is_system' => 1
        ],
        [
            'name' => 'Broadcast Message',
            'template_key' => 'broadcast',
            'template' => "📢 *اطلاعیه*\n\n{message}",
            'description' => 'Manual broadcast message template',
            'is_system' => 1
        ]
    ];

    foreach ($templates as $template) {
        // Check if template exists
        $stmt = $pdo->prepare('SELECT id FROM _telegram_templates WHERE template_key = ?');
        $stmt->execute([$template['template_key']]);
        if (!$stmt->fetch()) {
            $sql = "INSERT INTO _telegram_templates (name, template_key, template, description, is_system, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $template['name'],
                $template['template_key'],
                $template['template'],
                $template['description'],
                $template['is_system']
            ]);
            echo "    ✓ Template created: {$template['name']}\n";
        } else {
            echo "    ℹ Template exists: {$template['name']}\n";
        }
    }

    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "  ✅ TELEGRAM TABLES CREATED SUCCESSFULLY!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "Tables created/modified:\n";
    echo "  ✓ _users (added telegram_chat_id, telegram_linked_at)\n";
    echo "  ✓ _telegram_settings (Global bot configuration)\n";
    echo "  ✓ _telegram_notification_settings (Per-user notification preferences)\n";
    echo "  ✓ _telegram_logs (Message history and tracking)\n";
    echo "  ✓ _telegram_templates (Message templates)\n\n";

    echo "📝 Next Steps:\n";
    echo "  1. Configure Telegram Bot token in Messaging > Telegram tab\n";
    echo "  2. Each admin/reseller links their Telegram account\n";
    echo "  3. Configure notification preferences\n";
    echo "  4. Test by creating a new account\n\n";

    echo "🔑 Access Control:\n";
    echo "  - Super Admin: Full access to Telegram tab\n";
    echo "  - Reseller Admin: Full access, notifications for ALL accounts\n";
    echo "  - Regular Reseller: No access to Telegram tab\n\n";

} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
