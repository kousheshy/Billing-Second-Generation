<?php
/**
 * Create Mail System Tables
 * Run this script once to set up the mail system database tables
 */

require_once(__DIR__ . '/../config.php');

// Create PDO connection
$dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $ub_db_username, $ub_db_password, $opt);

try {
    // Create _mail_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _mail_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            smtp_host VARCHAR(255) DEFAULT 'mail.showboxtv.tv',
            smtp_port INT DEFAULT 587,
            smtp_secure ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
            smtp_username VARCHAR(255),
            smtp_password VARCHAR(255),
            from_email VARCHAR(255),
            from_name VARCHAR(255) DEFAULT 'ShowBox',
            auto_send_new_account TINYINT(1) DEFAULT 1,
            auto_send_renewal TINYINT(1) DEFAULT 1,
            auto_send_expiry TINYINT(1) DEFAULT 1,
            notify_admin TINYINT(1) DEFAULT 1,
            notify_reseller TINYINT(1) DEFAULT 1,
            days_before_expiry INT DEFAULT 7,
            enable_multistage_reminders TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created _mail_settings table\n";

    // Create _mail_templates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _mail_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body_html TEXT NOT NULL,
            body_plain TEXT,
            description VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created _mail_templates table\n";

    // Create _mail_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _mail_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT DEFAULT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255),
            cc_emails TEXT,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            message_type ENUM('manual', 'new_account', 'renewal', 'expiry_reminder') NOT NULL,
            sent_by INT NOT NULL,
            status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
            error_message TEXT,
            smtp_response TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_type (message_type),
            INDEX idx_created (created_at),
            INDEX idx_recipient (recipient_email),
            INDEX idx_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created _mail_logs table\n";

    // Create _mail_reminder_tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _mail_reminder_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            reminder_stage INT NOT NULL COMMENT '1=7days, 2=3days, 3=1day, 4=expired',
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_reminder (account_id, reminder_stage),
            INDEX idx_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created _mail_reminder_tracking table\n";

    // Get admin user ID (super_user = 1 means super admin)
    $stmt = $pdo->query("SELECT id FROM _users WHERE super_user = 1 ORDER BY id LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_id = $admin ? $admin['id'] : 1;

    // Check if admin mail settings exist
    $stmt = $pdo->prepare("SELECT id FROM _mail_settings WHERE user_id = ?");
    $stmt->execute([$admin_id]);

    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO _mail_settings (user_id, smtp_host, smtp_port, smtp_secure, smtp_username, from_email, from_name)
            VALUES (?, 'mail.showboxtv.tv', 587, 'tls', 'info@showboxtv.tv', 'info@showboxtv.tv', 'ShowBox')
        ");
        $stmt->execute([$admin_id]);
        echo "Created default admin mail settings\n";
    }

    // Check if templates exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM _mail_templates WHERE user_id = ?");
    $stmt->execute([$admin_id]);

    if ($stmt->fetchColumn() == 0) {
        $templates = [
            ['New Account Welcome', 'خوش آمدید - حساب شوباکس شما فعال شد', '<div dir="rtl" style="font-family:Tahoma,Arial;padding:20px;max-width:600px;margin:0 auto;background:#f9f9f9;"><div style="background:#4CAF50;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;"><h1 style="margin:0;">خوش آمدید!</h1></div><div style="background:white;padding:30px;border-radius:0 0 8px 8px;"><h2>سلام {name} عزیز</h2><p>حساب شوباکس شما با موفقیت ایجاد شد.</p><table style="width:100%;border-collapse:collapse;margin:20px 0;"><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>MAC:</strong></td><td style="padding:12px;border:1px solid #ddd;">{mac}</td></tr><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>پلن:</strong></td><td style="padding:12px;border:1px solid #ddd;">{plan_name}</td></tr><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>تاریخ انقضا:</strong></td><td style="padding:12px;border:1px solid #ddd;">{expiry_date}</td></tr></table><p style="color:#999;margin-top:30px;">با احترام،<br><strong>تیم شوباکس</strong></p></div></div>', 'Sent when new account created'],
            ['Renewal Confirmation', 'تمدید موفق - سرویس شوباکس شما تمدید شد', '<div dir="rtl" style="font-family:Tahoma,Arial;padding:20px;max-width:600px;margin:0 auto;background:#f9f9f9;"><div style="background:#2196F3;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;"><h1 style="margin:0;">تمدید موفق</h1></div><div style="background:white;padding:30px;border-radius:0 0 8px 8px;"><h2>سلام {name} عزیز</h2><p>سرویس شوباکس شما با موفقیت تمدید شد.</p><table style="width:100%;border-collapse:collapse;margin:20px 0;"><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>MAC:</strong></td><td style="padding:12px;border:1px solid #ddd;">{mac}</td></tr><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>پلن:</strong></td><td style="padding:12px;border:1px solid #ddd;">{plan_name}</td></tr><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>تاریخ انقضا:</strong></td><td style="padding:12px;border:1px solid #ddd;">{expiry_date}</td></tr></table><p style="color:#999;margin-top:30px;">با احترام،<br><strong>تیم شوباکس</strong></p></div></div>', 'Sent when account renewed'],
            ['Expiry Reminder', 'یادآوری تمدید - سرویس شما به زودی منقضی می‌شود', '<div dir="rtl" style="font-family:Tahoma,Arial;padding:20px;max-width:600px;margin:0 auto;background:#f9f9f9;"><div style="background:#FF9800;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;"><h1 style="margin:0;">یادآوری تمدید</h1></div><div style="background:white;padding:30px;border-radius:0 0 8px 8px;"><h2>سلام {name} عزیز</h2><p>سرویس شوباکس شما در تاریخ <strong style="color:#FF5722;">{expiry_date}</strong> منقضی خواهد شد.</p><div style="background:#FFF3E0;border-right:4px solid #FF9800;padding:15px;margin:20px 0;"><p style="margin:0;color:#E65100;">لطفاً جهت تمدید با نماینده خود تماس بگیرید.</p></div><table style="width:100%;border-collapse:collapse;margin:20px 0;"><tr><td style="padding:12px;border:1px solid #ddd;background:#f5f5f5;"><strong>MAC:</strong></td><td style="padding:12px;border:1px solid #ddd;">{mac}</td></tr></table><p style="color:#999;margin-top:30px;">با احترام،<br><strong>تیم شوباکس</strong></p></div></div>', 'Sent before expiry'],
            ['Account Expired', 'سرویس منقضی شد - حساب شوباکس شما غیرفعال شده است', '<div dir="rtl" style="font-family:Tahoma,Arial;padding:20px;max-width:600px;margin:0 auto;background:#f9f9f9;"><div style="background:#f44336;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;"><h1 style="margin:0;">سرویس منقضی شد</h1></div><div style="background:white;padding:30px;border-radius:0 0 8px 8px;"><h2>سلام {name} عزیز</h2><p>سرویس شوباکس شما در تاریخ <strong style="color:#f44336;">{expiry_date}</strong> منقضی شده است.</p><div style="background:#FFEBEE;border-right:4px solid #f44336;padding:15px;margin:20px 0;"><p style="margin:0;color:#C62828;">برای فعال‌سازی مجدد با نماینده خود تماس بگیرید.</p></div><p style="color:#999;margin-top:30px;">با احترام،<br><strong>تیم شوباکس</strong></p></div></div>', 'Sent when expired']
        ];

        $stmt = $pdo->prepare("INSERT INTO _mail_templates (user_id, name, subject, body_html, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($templates as $t) {
            $stmt->execute([$admin_id, $t[0], $t[1], $t[2], $t[3]]);
        }
        echo "Created 4 default email templates\n";
    }

    echo "\nMail system tables setup completed!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
