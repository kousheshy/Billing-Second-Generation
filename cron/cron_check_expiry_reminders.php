<?php
/**
 * Automated Expiry Reminder Cron Job
 *
 * This script should be run daily via cron (e.g., every morning at 9 AM)
 * It checks all users with auto_send_enabled = 1 and sends reminders
 * to their expiring accounts automatically.
 *
 * Cron setup example:
 * 0 9 * * * /usr/bin/php /path/to/cron_check_expiry_reminders.php
 *
 * Version: 1.7.8
 */

// Disable session for cron job
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../api/api.php');

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

echo "[" . date('Y-m-d H:i:s') . "] Starting automated expiry reminder check...\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get all users with auto-send enabled
    $stmt = $pdo->prepare('SELECT u.*, rs.*
                           FROM _users u
                           JOIN _reminder_settings rs ON u.id = rs.user_id
                           WHERE rs.auto_send_enabled = 1');
    $stmt->execute();
    $users_with_autosend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($users_with_autosend)) {
        echo "[" . date('Y-m-d H:i:s') . "] No users have auto-send enabled. Exiting.\n";
        exit(0);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Found " . count($users_with_autosend) . " user(s) with auto-send enabled\n";

    $total_sent = 0;
    $total_skipped = 0;
    $total_failed = 0;

    foreach($users_with_autosend as $user_settings) {
        $user_id = $user_settings['user_id'];
        $days_before = (int)$user_settings['days_before_expiry'];
        $message_template = $user_settings['message_template'];
        $is_super_admin = $user_settings['super_user'] == 1;
        $permissions = explode('|', $user_settings['permissions'] ?? '0|0|0|0|0|0');
        $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
        $can_control_stb = isset($permissions[4]) && $permissions[4] === '1';

        // Verify user still has permission to send reminders
        if(!$is_super_admin && !$can_control_stb) {
            echo "[" . date('Y-m-d H:i:s') . "] User ID {$user_id} ({$user_settings['username']}) no longer has STB permission. Skipping.\n";
            continue;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Processing user: {$user_settings['username']} (ID: {$user_id})\n";
        echo "[" . date('Y-m-d H:i:s') . "]   Days before expiry: {$days_before}\n";

        // Calculate target date
        $target_date = new DateTime();
        $target_date->modify("+{$days_before} days");
        $target_date_str = $target_date->format('Y-m-d');

        echo "[" . date('Y-m-d H:i:s') . "]   Target expiry date: {$target_date_str}\n";

        // Get accounts expiring on target date
        $query = 'SELECT a.*, u.username as reseller_username
                  FROM _accounts a
                  LEFT JOIN _users u ON a.reseller = u.id
                  WHERE DATE(a.end_date) = ? AND a.status = 1';

        // Filter by ownership for non-admin users
        if(!$is_super_admin) {
            if(!$is_reseller_admin) {
                // Regular reseller: only their accounts
                $query .= ' AND a.reseller = ' . (int)$user_id;
            }
            // Reseller admin can see all accounts
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute([$target_date_str]);
        $expiring_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "[" . date('Y-m-d H:i:s') . "]   Found " . count($expiring_accounts) . " account(s) expiring on {$target_date_str}\n";

        $user_sent = 0;
        $user_skipped = 0;
        $user_failed = 0;

        foreach($expiring_accounts as $account) {
            // Check if reminder already sent for this MAC/date/days combination within the last 60 days
            // Using MAC address ensures deduplication works even after account sync
            // Time window prevents blocking future renewals (e.g., if they renew next month/year)
            $stmt = $pdo->prepare('SELECT id FROM _expiry_reminders
                                   WHERE mac = ?
                                   AND end_date = ?
                                   AND days_before = ?
                                   AND sent_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)');
            $stmt->execute([$account['mac'], $account['end_date'], $days_before]);
            $existing_reminder = $stmt->fetch();

            if($existing_reminder) {
                $user_skipped++;
                echo "[" . date('Y-m-d H:i:s') . "]     SKIP: {$account['username']} - Already sent\n";
                continue;
            }

            // Prepare personalized message
            $message = str_replace(
                ['{days}', '{name}', '{username}', '{date}'],
                [$days_before, $account['full_name'], $account['username'], date('Y-m-d', strtotime($account['end_date']))],
                $message_template
            );

            // Send message via Stalker Portal API
            $mac = $account['mac'];

            // Build server config for primary server
            $server1_config = [
                'WEBSERVICE_URLs' => $WEBSERVICE_URLs,
                'WEBSERVICE_USERNAME' => $WEBSERVICE_USERNAME,
                'WEBSERVICE_PASSWORD' => $WEBSERVICE_PASSWORD
            ];

            // Build server config for secondary server
            $server2_config = [
                'WEBSERVICE_URLs' => $WEBSERVICE_2_URLs,
                'WEBSERVICE_USERNAME' => $WEBSERVICE_USERNAME,
                'WEBSERVICE_PASSWORD' => $WEBSERVICE_PASSWORD
            ];

            $response1 = send_message($mac, $message, $server1_config);
            $response2 = send_message($mac, $message, $server2_config);

            $success = ($response1['error'] == 0 || $response2['error'] == 0);

            if($success) {
                // Log successful reminder
                $stmt = $pdo->prepare('INSERT INTO _expiry_reminders
                    (account_id, mac, username, full_name, end_date, days_before, reminder_date, sent_at, sent_by, message, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)');
                $stmt->execute([
                    $account['id'],
                    $account['mac'],
                    $account['username'],
                    $account['full_name'],
                    $account['end_date'],
                    $days_before,
                    $target_date_str,
                    $user_id,
                    $message,
                    'sent'
                ]);

                $user_sent++;
                echo "[" . date('Y-m-d H:i:s') . "]     SENT: {$account['username']} ({$account['full_name']})\n";
            } else {
                // Log failed reminder
                $error_msg = $response1['err_msg'] ?? $response2['err_msg'] ?? 'Unknown error';

                $stmt = $pdo->prepare('INSERT INTO _expiry_reminders
                    (account_id, mac, username, full_name, end_date, days_before, reminder_date, sent_at, sent_by, message, status, error_message)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)');
                $stmt->execute([
                    $account['id'],
                    $account['mac'],
                    $account['username'],
                    $account['full_name'],
                    $account['end_date'],
                    $days_before,
                    $target_date_str,
                    $user_id,
                    $message,
                    'failed',
                    $error_msg
                ]);

                $user_failed++;
                echo "[" . date('Y-m-d H:i:s') . "]     FAIL: {$account['username']} - {$error_msg}\n";
            }

            // Rate limiting: 300ms delay
            usleep(300000);
        }

        // Update last sweep timestamp
        $stmt = $pdo->prepare('UPDATE _reminder_settings SET last_sweep_at = NOW() WHERE user_id = ?');
        $stmt->execute([$user_id]);

        echo "[" . date('Y-m-d H:i:s') . "]   User summary: Sent={$user_sent}, Skipped={$user_skipped}, Failed={$user_failed}\n";

        $total_sent += $user_sent;
        $total_skipped += $user_skipped;
        $total_failed += $user_failed;
    }

    echo "[" . date('Y-m-d H:i:s') . "] ===== CRON JOB COMPLETE =====\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total sent: {$total_sent}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total skipped: {$total_skipped}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total failed: {$total_failed}\n";
    echo "[" . date('Y-m-d H:i:s') . "] ==============================\n";

    exit(0);

} catch(PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Database error - " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
