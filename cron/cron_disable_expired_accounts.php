<?php
/**
 * Cron Job: Auto-Disable Expired Accounts (v1.17.1)
 *
 * This script disables accounts that have expired in both:
 * 1. The billing database (status = 0)
 * 2. The Stalker Portal server (via API)
 *
 * Recommended cron schedule: Every hour
 * Example: 0 * * * * php /var/www/showbox/cron/cron_disable_expired_accounts.php
 *
 * Features:
 * - Finds accounts where expiration_date < today AND status = 1
 * - Disables on Stalker server via API
 * - Updates local database
 * - Logs all actions for audit trail
 * - Supports dual-server mode
 */

// Allow CLI execution
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    // Also allow web access for testing with special parameter
    if (!isset($_GET['run']) || $_GET['run'] !== 'test') {
        http_response_code(403);
        die('Access denied. This script must be run from CLI or cron.');
    }
}

// For web testing, output as plain text
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/api.php';

// Configuration
define('LOG_PREFIX', '[Cron-AutoDisable]');
define('DRY_RUN', false); // Set to true to test without making changes

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
    $msg = LOG_PREFIX . " Database connection failed: " . $e->getMessage();
    error_log($msg);
    echo $msg . "\n";
    exit(1);
}

/**
 * Log message to both error_log and stdout
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $fullMsg = LOG_PREFIX . " $message";
    error_log($fullMsg);
    echo "[$timestamp] $message\n";
}

/**
 * Get expired accounts that are still enabled
 */
function getExpiredEnabledAccounts($pdo) {
    // Use PHP's date (respects Asia/Tehran timezone from config.php)
    // instead of MySQL's NOW() which may use different timezone
    $today = date('Y-m-d');

    // Find accounts where:
    // 1. expiration date is in the past (end_date < today)
    // 2. status is still enabled (status = 1)
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            full_name,
            mac,
            end_date,
            status,
            reseller
        FROM _accounts
        WHERE DATE(end_date) < :today
          AND status = 1
        ORDER BY end_date ASC
    ");
    $stmt->execute(['today' => $today]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Disable account on Stalker Portal server
 */
function disableOnStalker($mac) {
    global $WEBSERVICE_URLs, $WEBSERVICE_2_URLs, $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD;
    global $WEBSERVICE_BASE_URL, $WEBSERVICE_2_BASE_URL, $DUAL_SERVER_MODE_ENABLED;

    $data = 'status=0';
    $case = 'accounts';
    $op = 'PUT';

    $success = true;
    $errors = [];

    // Check if dual server mode is enabled
    $dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED &&
                        ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

    // Update Server 1 (primary)
    $res1 = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded1 = json_decode($res1);

    if (!$decoded1 || $decoded1->status != 'OK') {
        $success = false;
        $errors[] = "Server 1: " . ($decoded1->error ?? 'Unknown error');
    }

    // Update Server 2 if dual mode
    if ($dual_server_mode) {
        $res2 = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        $decoded2 = json_decode($res2);

        if (!$decoded2 || $decoded2->status != 'OK') {
            // Log but don't fail - Server 1 succeeded
            $errors[] = "Server 2: " . ($decoded2->error ?? 'Unknown error');
        }
    }

    return [
        'success' => $success,
        'errors' => $errors
    ];
}

/**
 * Update account status in local database
 */
function disableInDatabase($pdo, $username) {
    $stmt = $pdo->prepare('UPDATE _accounts SET status = 0 WHERE username = ?');
    return $stmt->execute([$username]);
}

/**
 * Log action to audit table if exists
 */
function logAuditAction($pdo, $mac, $fullName, $username) {
    try {
        // Check if audit table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '_audit_log'");
        if ($stmt->rowCount() === 0) {
            return; // Audit table doesn't exist
        }

        $stmt = $pdo->prepare("
            INSERT INTO _audit_log (action, entity_type, entity_id, changes, description, performed_by, created_at)
            VALUES ('auto_disable', 'account', ?, ?, ?, 'system_cron', NOW())
        ");

        $changes = json_encode([
            'old' => ['status' => 1],
            'new' => ['status' => 0],
            'reason' => 'Account expired - auto-disabled by cron'
        ]);

        $description = "Auto-disabled expired account: $fullName ($mac)";

        $stmt->execute([$mac, $changes, $description]);
    } catch (Exception $e) {
        // Audit logging should not break the main flow
        error_log(LOG_PREFIX . " Audit log error: " . $e->getMessage());
    }
}

// Main execution
logMessage("=== Starting Auto-Disable Expired Accounts ===");
logMessage("Server timezone: " . date_default_timezone_get() . " | Today's date: " . date('Y-m-d H:i:s'));

if (DRY_RUN) {
    logMessage("*** DRY RUN MODE - No changes will be made ***");
}

try {
    // Get expired accounts that are still enabled
    $expiredAccounts = getExpiredEnabledAccounts($pdo);
    $totalExpired = count($expiredAccounts);

    logMessage("Found $totalExpired expired accounts with status=enabled");

    if ($totalExpired === 0) {
        logMessage("No accounts to disable. Exiting.");
        exit(0);
    }

    $successCount = 0;
    $failCount = 0;

    foreach ($expiredAccounts as $account) {
        $username = $account['username'];
        $fullName = $account['full_name'] ?: $username;
        $mac = $account['mac'];
        $endDate = $account['end_date'];

        logMessage("Processing: $fullName (MAC: $mac, Expired: $endDate)");

        if (DRY_RUN) {
            logMessage("  [DRY RUN] Would disable: $fullName");
            $successCount++;
            continue;
        }

        // Step 1: Disable on Stalker server
        $stalkerResult = disableOnStalker($mac);

        if (!$stalkerResult['success']) {
            logMessage("  ERROR - Stalker API failed: " . implode(', ', $stalkerResult['errors']));
            $failCount++;
            continue;
        }

        // Step 2: Update local database
        $dbResult = disableInDatabase($pdo, $username);

        if (!$dbResult) {
            logMessage("  ERROR - Database update failed for $username");
            $failCount++;
            continue;
        }

        // Step 3: Log to audit trail
        logAuditAction($pdo, $mac, $fullName, $username);

        logMessage("  SUCCESS - Disabled: $fullName");
        $successCount++;
    }

    logMessage("=== Completed ===");
    logMessage("Total: $totalExpired | Success: $successCount | Failed: $failCount");

} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    exit(1);
}
?>
