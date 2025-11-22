#!/usr/bin/env php
<?php
/**
 * Multi-Stage Expiry Reminder Cron Job
 *
 * Sends automatic SMS reminders at 4 different stages:
 * 1. 7 days before expiry
 * 2. 3 days (72 hours) before expiry
 * 3. 1 day (24 hours) before expiry
 * 4. Account expired (deactivated)
 *
 * Schedule this to run daily:
 * 0 9 * * * /usr/bin/php /path/to/cron_multistage_expiry_reminders.php >> /path/to/sms_reminders.log 2>&1
 */

require_once(__DIR__ . '/config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Multi-Stage SMS Expiry Reminder Cron Job\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all users with SMS enabled (multi-stage is now always on)
    $stmt = $pdo->query("SELECT s.*, u.id as user_id, u.username, u.super_user
                         FROM _sms_settings s
                         JOIN _users u ON s.user_id = u.id
                         WHERE s.auto_send_enabled = 1
                         AND s.api_token IS NOT NULL
                         AND s.sender_number IS NOT NULL");

    $users_with_sms = $stmt->fetchAll();

    if (empty($users_with_sms)) {
        echo "No users with SMS reminders enabled.\n";
        exit(0);
    }

    echo "Found " . count($users_with_sms) . " user(s) with SMS enabled\n\n";

    // Define reminder stages with their dates and template names
    $stages = [
        '7days' => [
            'days' => 7,
            'template_name' => '7 Days Before Expiry',
            'label' => '7 days before expiry'
        ],
        '3days' => [
            'days' => 3,
            'template_name' => '3 Days Before Expiry',
            'label' => '3 days before expiry'
        ],
        '1day' => [
            'days' => 1,
            'template_name' => '1 Day Before Expiry',
            'label' => '1 day before expiry'
        ],
        'expired' => [
            'days' => 0,
            'template_name' => 'Account Expired',
            'label' => 'account expired (today)'
        ]
    ];

    foreach ($users_with_sms as $settings) {
        $user_id = $settings['user_id'];
        $is_super = $settings['super_user'] == 1;

        echo "Processing user: {$settings['username']} (ID: $user_id)\n";

        foreach ($stages as $stage_key => $stage_info) {
            $days = $stage_info['days'];
            $template_name = $stage_info['template_name'];
            $label = $stage_info['label'];

            // Calculate target date
            if ($days > 0) {
                $target_date = date('Y-m-d', strtotime("+$days days"));
            } else {
                // For expired stage, look for accounts that expired today or earlier
                $target_date = date('Y-m-d');
            }

            echo "  Stage: $label (target date: $target_date)\n";

            // Get template for this stage
            $stmt = $pdo->prepare("SELECT template FROM _sms_templates
                                   WHERE user_id = ? AND name = ? LIMIT 1");
            $stmt->execute([$user_id, $template_name]);
            $template_row = $stmt->fetch();

            if (!$template_row) {
                echo "    ⚠ Template '$template_name' not found, skipping\n";
                continue;
            }

            $template = $template_row['template'];

            // Build account query based on stage
            if ($stage_key === 'expired') {
                // For expired accounts: end_date <= today AND status = inactive
                $account_sql = "SELECT a.id, a.mac, a.full_name, a.phone_number, a.end_date, a.reseller, a.status
                               FROM _accounts a
                               WHERE a.end_date <= ?
                               AND a.status = 'inactive'
                               AND a.phone_number IS NOT NULL
                               AND a.phone_number != ''
                               AND (a.reseller = ? OR ? = 1)";
            } else {
                // For future reminders: end_date = target_date AND status = active
                $account_sql = "SELECT a.id, a.mac, a.full_name, a.phone_number, a.end_date, a.reseller, a.status
                               FROM _accounts a
                               WHERE a.end_date = ?
                               AND a.status = 'active'
                               AND a.phone_number IS NOT NULL
                               AND a.phone_number != ''
                               AND (a.reseller = ? OR ? = 1)";
            }

            $stmt = $pdo->prepare($account_sql);
            $stmt->execute([$target_date, $user_id, $is_super ? 1 : 0]);
            $accounts = $stmt->fetchAll();

            echo "    Found " . count($accounts) . " account(s)\n";

            if (empty($accounts)) {
                continue;
            }

            // Filter out accounts that already received this stage's reminder
            $recipients = [];
            $log_entries = [];

            foreach ($accounts as $account) {
                // Check if this reminder was already sent
                $check = $pdo->prepare("SELECT id FROM _sms_reminder_tracking
                                       WHERE account_id = ?
                                       AND reminder_stage = ?
                                       AND end_date = ?");
                $check->execute([$account['id'], $stage_key, $account['end_date']]);

                if ($check->rowCount() > 0) {
                    echo "      - Skipping {$account['full_name']} (reminder already sent)\n";
                    continue;
                }

                // Personalize message
                $message = str_replace(
                    ['{name}', '{mac}', '{expiry_date}', '{days}'],
                    [
                        $account['full_name'] ?? 'Customer',
                        $account['mac'] ?? '',
                        $account['end_date'] ?? '',
                        $days
                    ],
                    $template
                );

                $recipients[] = $account['phone_number'];
                $log_entries[] = [
                    'account_id' => $account['id'],
                    'mac' => $account['mac'],
                    'recipient_name' => $account['full_name'],
                    'recipient_number' => $account['phone_number'],
                    'message' => $message,
                    'end_date' => $account['end_date']
                ];
            }

            if (empty($recipients)) {
                echo "    No new SMS to send\n";
                continue;
            }

            echo "    Sending SMS to " . count($recipients) . " recipient(s)...\n";

            // Prepare API request
            $api_url = rtrim($settings['base_url'], '/') . '/api/send';

            $payload = [
                'sending_type' => 'webservice',
                'from_number' => $settings['sender_number'],
                'message' => $template, // Use template (will be personalized per recipient)
                'params' => [
                    'recipients' => $recipients
                ]
            ];

            // Send SMS via cURL
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: ' . $settings['api_token']
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Parse response
            $api_response = json_decode($response, true);

            $status = 'failed';
            $error_message = null;
            $bulk_id = null;

            if ($http_code == 200 && $api_response && isset($api_response['data'])) {
                $status = 'sent';
                $bulk_id = isset($api_response['data']['bulk_id']) ? $api_response['data']['bulk_id'] : null;
                echo "    ✓ SMS sent successfully (Bulk ID: $bulk_id)\n";
            } else {
                $error_message = $curl_error ? $curl_error : ($api_response['message'] ?? 'Unknown error');
                echo "    ✗ SMS failed: $error_message\n";
            }

            // Log SMS in database
            foreach ($log_entries as $entry) {
                // Insert into SMS logs
                $sql = "INSERT INTO _sms_logs
                        (account_id, mac, recipient_name, recipient_number, message, message_type, sent_by, sent_at, status, api_response, bulk_id, error_message, created_at)
                        VALUES (?, ?, ?, ?, ?, 'expiry_reminder', ?, NOW(), ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $entry['account_id'],
                    $entry['mac'],
                    $entry['recipient_name'],
                    $entry['recipient_number'],
                    $entry['message'],
                    $user_id,
                    $status,
                    json_encode($api_response),
                    $bulk_id,
                    $error_message
                ]);

                // Mark reminder as sent in tracking table
                if ($status === 'sent') {
                    $track = $pdo->prepare("INSERT INTO _sms_reminder_tracking
                                           (account_id, mac, reminder_stage, sent_at, end_date)
                                           VALUES (?, ?, ?, NOW(), ?)");
                    $track->execute([
                        $entry['account_id'],
                        $entry['mac'],
                        $stage_key,
                        $entry['end_date']
                    ]);
                }
            }

            echo "    Logged " . count($log_entries) . " SMS record(s)\n";
        }

        echo "\n";
    }

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Cron job completed successfully\n";
    echo "═══════════════════════════════════════════════════════════════\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
