<?php
/**
 * Reset Messaging Database Tables
 *
 * Clears all messaging/reminder data and resets to fresh state
 * WARNING: This will delete all sent reminder history!
 *
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
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];

echo "═══════════════════════════════════════════════════════════════\n";
echo "  MESSAGING DATABASE RESET UTILITY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Step 1: Count existing records
    echo "[1] Counting existing records...\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM _expiry_reminders');
    $reminder_count = $stmt->fetch()['count'];
    $stmt->closeCursor();

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM _reminder_settings');
    $settings_count = $stmt->fetch()['count'];
    $stmt->closeCursor();

    echo "    • _expiry_reminders: {$reminder_count} record(s)\n";
    echo "    • _reminder_settings: {$settings_count} record(s)\n\n";

    // Step 2: Clear expiry reminders table
    echo "[2] Clearing _expiry_reminders table...\n";
    $pdo->exec('TRUNCATE TABLE _expiry_reminders');
    echo "    ✓ All reminder history deleted\n\n";

    // Step 3: Reset reminder settings to default
    echo "[3] Resetting _reminder_settings to default state...\n";
    $pdo->exec('DELETE FROM _reminder_settings');

    // Insert default settings for super admin (user_id = 1)
    $stmt = $pdo->prepare('INSERT INTO _reminder_settings
                           (user_id, days_before_expiry, message_template, auto_send_enabled, created_at, updated_at)
                           VALUES (?, ?, ?, ?, NOW(), NOW())');

    $default_template = 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.';

    $stmt->execute([1, 7, $default_template, 0]);
    echo "    ✓ Default settings restored for admin (user_id: 1)\n";
    echo "      - Days before expiry: 7\n";
    echo "      - Auto-send: OFF\n";
    echo "      - Template: Default message\n\n";

    // Step 4: Optimize tables
    echo "[4] Optimizing tables...\n";
    $stmt1 = $pdo->query('OPTIMIZE TABLE _expiry_reminders');
    $stmt1->closeCursor();
    $stmt2 = $pdo->query('OPTIMIZE TABLE _reminder_settings');
    $stmt2->closeCursor();
    echo "    ✓ Tables optimized\n\n";

    // Step 5: Verify reset
    echo "[5] Verifying reset...\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM _expiry_reminders');
    $new_reminder_count = $stmt->fetch()['count'];
    $stmt->closeCursor();

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM _reminder_settings');
    $new_settings_count = $stmt->fetch()['count'];
    $stmt->closeCursor();

    echo "    • _expiry_reminders: {$new_reminder_count} record(s)\n";
    echo "    • _reminder_settings: {$new_settings_count} record(s)\n\n";

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  ✅ DATABASE RESET COMPLETED SUCCESSFULLY!\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    echo "Summary:\n";
    echo "  • Deleted {$reminder_count} reminder record(s)\n";
    echo "  • Reset {$settings_count} settings to default\n";
    echo "  • Tables optimized and ready for fresh use\n\n";

    echo "Next steps:\n";
    echo "  1. Login to dashboard as admin\n";
    echo "  2. Go to Settings → Expiry Reminder Settings\n";
    echo "  3. Configure your preferences:\n";
    echo "     - Days before expiry (1-90)\n";
    echo "     - Message template (customize as needed)\n";
    echo "     - Enable automatic reminders (toggle ON if desired)\n";
    echo "  4. Save settings\n\n";

    exit(0);

} catch(PDOException $e) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  ❌ DATABASE RESET FAILED!\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
