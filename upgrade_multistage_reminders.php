<?php
/**
 * Multi-Stage Expiry Reminder System - Database Upgrade
 *
 * This script adds support for:
 * - 7 days before expiry reminder
 * - 72 hours (3 days) before expiry reminder
 * - 24 hours (1 day) before expiry reminder
 * - Account deactivated (expired) notification
 */

include('config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

echo "=== Multi-Stage Expiry Reminder System - Database Upgrade ===\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Step 1: Creating reminder tracking table...\n";

    // Create table to track which reminders have been sent
    $sql = "CREATE TABLE IF NOT EXISTS _sms_reminder_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        mac VARCHAR(20),
        reminder_stage ENUM('7days', '3days', '1day', 'expired') NOT NULL,
        sent_at DATETIME NOT NULL,
        end_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_account_stage (account_id, reminder_stage),
        INDEX idx_mac_stage (mac, reminder_stage),
        INDEX idx_end_date (end_date),
        UNIQUE KEY unique_reminder (account_id, reminder_stage, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✓ Reminder tracking table created\n\n";

    echo "Step 2: Updating SMS settings table...\n";

    // Check if columns exist before adding
    $result = $pdo->query("SHOW COLUMNS FROM _sms_settings LIKE 'enable_multistage_reminders'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE _sms_settings ADD COLUMN enable_multistage_reminders TINYINT(1) DEFAULT 1 AFTER auto_send_enabled");
        echo "✓ Added enable_multistage_reminders column\n";
    } else {
        echo "✓ enable_multistage_reminders column already exists\n";
    }

    echo "\nStep 3: Adding new message templates...\n";

    // Get all users
    $stmt = $pdo->query("SELECT id, username FROM _users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $user_id = $user['id'];

        // Template 1: 7 Days Before Expiry
        $template_7days = "{name}
عزیز، سرویس شما ۷ روز دیگر منقضی می‌شود
تاریخ اتمام: {expiry_date}
برای تمدید سریع با ما تماس بگیرید.

پشتیبانی: واتساپ 00447736932888";

        // Template 2: 3 Days (72 hours) Before Expiry
        $template_3days = "{name}
⚠️ عزیز، فقط ۳ روز تا پایان سرویس شما باقی مانده است!
تاریخ اتمام: {expiry_date}
لطفاً هرچه سریعتر تمدید کنید.

پشتیبانی: واتساپ 00447736932888";

        // Template 3: 1 Day (24 hours) Before Expiry
        $template_1day = "{name}
🚨 عزیز، فقط ۱ روز تا قطع سرویس شما باقی مانده!
تاریخ اتمام: {expiry_date}
برای جلوگیری از قطعی، همین حالا تمدید کنید.

پشتیبانی: واتساپ 00447736932888";

        // Template 4: Account Expired/Deactivated
        $template_expired = "{name}
❌ عزیز، سرویس شما منقضی شد
تاریخ اتمام: {expiry_date}
برای فعال‌سازی مجدد، لطفاً تمدید کنید.

پشتیبانی: واتساپ 00447736932888";

        // Insert templates (ignore if already exist)
        $templates = [
            ['7 Days Before Expiry', $template_7days],
            ['3 Days Before Expiry', $template_3days],
            ['1 Day Before Expiry', $template_1day],
            ['Account Expired', $template_expired]
        ];

        foreach ($templates as $tpl) {
            $check = $pdo->prepare("SELECT id FROM _sms_templates WHERE user_id = ? AND name = ?");
            $check->execute([$user_id, $tpl[0]]);

            if ($check->rowCount() == 0) {
                $insert = $pdo->prepare("INSERT INTO _sms_templates (user_id, name, template, created_at) VALUES (?, ?, ?, NOW())");
                $insert->execute([$user_id, $tpl[0], $tpl[1]]);
                echo "  ✓ Added template '{$tpl[0]}' for user {$user['username']}\n";
            } else {
                echo "  - Template '{$tpl[0]}' already exists for user {$user['username']}\n";
            }
        }
    }

    echo "\n✅ Multi-Stage Reminder System upgrade completed successfully!\n\n";
    echo "Next Steps:\n";
    echo "1. The system now supports 4 reminder stages:\n";
    echo "   - 7 days before expiry\n";
    echo "   - 3 days (72 hours) before expiry\n";
    echo "   - 1 day (24 hours) before expiry\n";
    echo "   - Account expired notification\n\n";
    echo "2. Update your cron job to use the new script:\n";
    echo "   php cron_multistage_expiry_reminders.php\n\n";
    echo "3. Configure settings in Dashboard → Messaging → SMS Messages\n\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
