<?php
/**
 * Cron Job: Check Expired Accounts and Send Push Notifications (v1.11.48)
 *
 * This script should be run periodically (e.g., every 5-15 minutes) via cron
 * Example: crontab -e and add: 0,10,20,30,40,50 * * * * php /path/to/cron_check_expired.php
 *
 * Features:
 * - Checks for accounts that have expired
 * - Sends push notification to the reseller who owns the account
 * - Also notifies reseller admins
 * - Does NOT notify super admin
 * - Tracks sent notifications to avoid duplicates
 * - Sends individual notification for each expired account
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    http_response_code(403);
    die('Access denied. This script must be run from CLI or cron.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/push_helper.php';

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4",
        $ub_db_username,
        $ub_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log('[Cron-Expiry] Database connection failed: ' . $e->getMessage());
    die('Database connection failed');
}

// Configuration
define('EXPIRY_CHECK_WINDOW_HOURS', 24); // Only notify for accounts expired within last 24 hours
define('LOG_PREFIX', '[Cron-Expiry]');

/**
 * Create tracking table if not exists
 */
function ensureTrackingTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `_push_expiry_tracking` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `expiry_date` DATE NOT NULL,
        `notified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_account_expiry` (`account_id`, `expiry_date`),
        INDEX `idx_notified_at` (`notified_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
}

/**
 * Check if notification was already sent for this account's expiry
 */
function wasNotificationSent($pdo, $accountId, $expiryDate) {
    $stmt = $pdo->prepare("
        SELECT id FROM _push_expiry_tracking
        WHERE account_id = ? AND expiry_date = ?
    ");
    $stmt->execute([$accountId, $expiryDate]);
    return $stmt->fetch() !== false;
}

/**
 * Mark notification as sent
 */
function markNotificationSent($pdo, $accountId, $expiryDate) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO _push_expiry_tracking (account_id, expiry_date)
        VALUES (?, ?)
    ");
    return $stmt->execute([$accountId, $expiryDate]);
}

/**
 * Clean up old tracking records (older than 30 days)
 */
function cleanupOldRecords($pdo) {
    $stmt = $pdo->prepare("
        DELETE FROM _push_expiry_tracking
        WHERE notified_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    if ($deleted > 0) {
        error_log(LOG_PREFIX . " Cleaned up $deleted old tracking records");
    }
}

/**
 * Get expired accounts that need notification
 */
function getExpiredAccounts($pdo) {
    // Get accounts that:
    // 1. Have expired (end_date < NOW())
    // 2. Expired within the check window (not too old)
    // 3. Have a reseller assigned
    // 4. Haven't been notified yet for this expiry date
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.username,
            a.full_name,
            a.mac,
            a.end_date,
            a.reseller,
            DATE(a.end_date) as expiry_date
        FROM _accounts a
        WHERE a.end_date < NOW()
          AND a.end_date > DATE_SUB(NOW(), INTERVAL :hours HOUR)
          AND a.reseller IS NOT NULL
          AND a.reseller > 0
        ORDER BY a.end_date DESC
    ");
    $stmt->execute(['hours' => EXPIRY_CHECK_WINDOW_HOURS]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Main execution
try {
    error_log(LOG_PREFIX . " Starting expiry check...");

    // Ensure tracking table exists
    ensureTrackingTable($pdo);

    // Clean up old records
    cleanupOldRecords($pdo);

    // Get expired accounts
    $expiredAccounts = getExpiredAccounts($pdo);
    error_log(LOG_PREFIX . " Found " . count($expiredAccounts) . " expired accounts to check");

    $notificationsSent = 0;
    $notificationsSkipped = 0;
    $notificationsFailed = 0;

    foreach ($expiredAccounts as $account) {
        $accountId = $account['id'];
        $expiryDate = $account['expiry_date'];

        // Check if already notified
        if (wasNotificationSent($pdo, $accountId, $expiryDate)) {
            $notificationsSkipped++;
            continue;
        }

        // Send notification
        $displayName = $account['full_name'] ?: $account['username'];
        $formattedDate = date('Y-m-d H:i', strtotime($account['end_date']));

        error_log(LOG_PREFIX . " Sending notification for account: $displayName (ID: $accountId)");

        $result = notifyAccountExpired(
            $pdo,
            $account['reseller'],
            $displayName,
            $account['username'],
            $formattedDate
        );

        if ($result['sent'] > 0) {
            // Mark as notified
            markNotificationSent($pdo, $accountId, $expiryDate);
            $notificationsSent++;
            error_log(LOG_PREFIX . " ✓ Notification sent for: $displayName");
        } else {
            $notificationsFailed++;
            error_log(LOG_PREFIX . " ✗ Failed to send notification for: $displayName");
        }
    }

    error_log(LOG_PREFIX . " Completed. Sent: $notificationsSent, Skipped: $notificationsSkipped, Failed: $notificationsFailed");

    // Output summary (for cron logs)
    echo date('Y-m-d H:i:s') . " - Expiry check completed. ";
    echo "Sent: $notificationsSent, Skipped: $notificationsSkipped, Failed: $notificationsFailed\n";

} catch (Exception $e) {
    error_log(LOG_PREFIX . " Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
