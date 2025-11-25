<?php
/**
 * Send Expiry Reminders to Customers
 *
 * Automated churn-prevention messaging system that sends alerts
 * to customers whose accounts are expiring soon
 *
 * Version: 1.7.8
 */

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../config.php');
include('api.php');

header('Content-Type: application/json');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
    exit();
}

$username = $_SESSION['username'];

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

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found']);
        exit();
    }

    // Check permissions
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $can_control_stb = isset($permissions[4]) && $permissions[4] === '1';

    // Only super admin, reseller admin with STB permission, or resellers with STB permission can send reminders
    if($user_info['super_user'] != 1) {
        if(!$can_control_stb) {
            echo json_encode(['error' => 1, 'err_msg' => 'Permission denied. You need STB control permission to send reminders.']);
            exit();
        }
    }

    // Get reminder settings for current user
    $stmt = $pdo->prepare('SELECT * FROM _reminder_settings WHERE user_id = ?');
    $stmt->execute([$user_info['id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create default settings if none exist
    if(!$settings) {
        $stmt = $pdo->prepare('INSERT INTO _reminder_settings (user_id, days_before_expiry, message_template, created_at, updated_at) VALUES (?, 7, ?, NOW(), NOW())');
        $default_template = 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.';
        $stmt->execute([$user_info['id'], $default_template]);

        $stmt = $pdo->prepare('SELECT * FROM _reminder_settings WHERE user_id = ?');
        $stmt->execute([$user_info['id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $days_before = (int)$settings['days_before_expiry'];
    $message_template = $settings['message_template'];

    // Calculate target date (today + days_before)
    $target_date = new DateTime();
    $target_date->modify("+{$days_before} days");
    $target_date_str = $target_date->format('Y-m-d');

    // Get all accounts expiring on target date
    $query = 'SELECT a.*, u.username as reseller_username
              FROM _accounts a
              LEFT JOIN _users u ON a.reseller = u.id
              WHERE DATE(a.end_date) = ? AND a.status = 1';

    // If not super admin, filter by ownership
    if($user_info['super_user'] != 1) {
        if(!$is_reseller_admin) {
            // Regular reseller: only their accounts
            $query .= ' AND a.reseller = ' . (int)$user_info['id'];
        }
        // Reseller admin can see all accounts
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute([$target_date_str]);
    $expiring_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent_count = 0;
    $skipped_count = 0;
    $failed_count = 0;
    $results = [];

    foreach($expiring_accounts as $account) {
        // Check if reminder already sent for this MAC/date/days combination within the last 60 days
        // Using MAC address ensures deduplication works even after account sync
        // Time window prevents blocking future renewals (e.g., if they renew next month/year)
        $stmt = $pdo->prepare('SELECT id, sent_at FROM _expiry_reminders
                               WHERE mac = ?
                               AND end_date = ?
                               AND days_before = ?
                               AND sent_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)');
        $stmt->execute([$account['mac'], $account['end_date'], $days_before]);
        $existing_reminder = $stmt->fetch();

        if($existing_reminder) {
            $skipped_count++;
            $results[] = [
                'account' => $account['username'],
                'full_name' => $account['full_name'],
                'status' => 'skipped',
                'reason' => 'Already sent on ' . date('Y-m-d H:i', strtotime($existing_reminder['sent_at']))
            ];
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
                $user_info['id'],
                $message,
                'sent'
            ]);

            $sent_count++;
            $results[] = [
                'account' => $account['username'],
                'full_name' => $account['full_name'],
                'mac' => $account['mac'],
                'expiry_date' => $account['end_date'],
                'status' => 'sent',
                'message' => $message
            ];
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
                $user_info['id'],
                $message,
                'failed',
                $error_msg
            ]);

            $failed_count++;
            $results[] = [
                'account' => $account['username'],
                'full_name' => $account['full_name'],
                'mac' => $account['mac'],
                'status' => 'failed',
                'error' => $error_msg
            ];
        }

        // Rate limiting: 300ms delay between messages
        usleep(300000);
    }

    // Update last sweep timestamp
    $stmt = $pdo->prepare('UPDATE _reminder_settings SET last_sweep_at = NOW() WHERE user_id = ?');
    $stmt->execute([$user_info['id']]);

    echo json_encode([
        'error' => 0,
        'sent' => $sent_count,
        'skipped' => $skipped_count,
        'failed' => $failed_count,
        'total' => count($expiring_accounts),
        'days_before' => $days_before,
        'target_date' => $target_date_str,
        'results' => $results
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['error' => 1, 'err_msg' => $e->getMessage()]);
}
?>
