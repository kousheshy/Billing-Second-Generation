<?php
/**
 * Fix Reminder Deduplication After Account Sync
 *
 * Changes unique constraint from account_id to mac address
 * This ensures reminders persist across account syncs from Stalker Portal
 *
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

    echo "Starting migration: Fixing reminder deduplication...\n\n";

    // Drop old unique constraint
    echo "Step 1: Dropping old unique constraint on (account_id, end_date, days_before)...\n";
    $sql = "ALTER TABLE `_expiry_reminders` DROP INDEX `unique_reminder`";
    try {
        $pdo->exec($sql);
        echo "✓ Old constraint dropped successfully\n\n";
    } catch(PDOException $e) {
        if(strpos($e->getMessage(), "Can't DROP") !== false) {
            echo "⚠ Old constraint already removed (this is ok)\n\n";
        } else {
            throw $e;
        }
    }

    // Remove duplicates (keep only the most recent entry for each mac/end_date/days_before combination)
    echo "Step 2: Removing duplicate entries (keeping most recent)...\n";
    $sql = "DELETE t1 FROM _expiry_reminders t1
            INNER JOIN _expiry_reminders t2
            WHERE t1.mac = t2.mac
              AND t1.end_date = t2.end_date
              AND t1.days_before = t2.days_before
              AND t1.sent_at < t2.sent_at";
    $affected = $pdo->exec($sql);
    echo "✓ Removed $affected duplicate entries\n\n";

    // Add new unique constraint based on MAC address
    echo "Step 3: Adding new unique constraint on (mac, end_date, days_before)...\n";
    $sql = "ALTER TABLE `_expiry_reminders`
            ADD UNIQUE KEY `unique_reminder_mac` (`mac`, `end_date`, `days_before`)";
    try {
        $pdo->exec($sql);
        echo "✓ New constraint added successfully\n\n";
    } catch(PDOException $e) {
        if(strpos($e->getMessage(), "Duplicate key name") !== false) {
            echo "⚠ New constraint already exists (this is ok)\n\n";
        } else {
            throw $e;
        }
    }

    echo "✅ Migration completed successfully!\n\n";
    echo "Summary:\n";
    echo "  - Unique constraint now uses MAC address instead of account_id\n";
    echo "  - Reminders will persist across account syncs from Stalker Portal\n";
    echo "  - Duplicate prevention now works even if account IDs change\n\n";

} catch(PDOException $e) {
    echo "❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
