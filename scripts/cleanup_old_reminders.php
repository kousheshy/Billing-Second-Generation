<?php
/**
 * Cleanup Old Reminder Records
 *
 * Removes reminder records older than 90 days to keep database clean
 * Run this script monthly via cron to maintain database size
 *
 * Cron example: 0 2 1 * * /usr/bin/php /path/to/cleanup_old_reminders.php
 * (Runs at 2 AM on the 1st of each month)
 *
 * Version: 1.7.8
 */

// Disable session for cron job
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

echo "[" . date('Y-m-d H:i:s') . "] Starting reminder cleanup process...\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Count records before cleanup
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM _expiry_reminders');
    $before_count = $stmt->fetch()['total'];
    echo "[" . date('Y-m-d H:i:s') . "] Current reminder records: {$before_count}\n";

    // Delete records older than 90 days
    $stmt = $pdo->prepare('DELETE FROM _expiry_reminders WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    $stmt->execute();
    $deleted_count = $stmt->rowCount();

    // Count records after cleanup
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM _expiry_reminders');
    $after_count = $stmt->fetch()['total'];

    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted_count} old reminder record(s)\n";
    echo "[" . date('Y-m-d H:i:s') . "] Remaining reminder records: {$after_count}\n";

    // Optimize table after deletion
    echo "[" . date('Y-m-d H:i:s') . "] Optimizing table...\n";
    $pdo->exec('OPTIMIZE TABLE _expiry_reminders');
    echo "[" . date('Y-m-d H:i:s') . "] Table optimization complete\n";

    echo "[" . date('Y-m-d H:i:s') . "] ===== CLEANUP COMPLETE =====\n";
    exit(0);

} catch(PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Database error - " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
