<?php
/**
 * Automatic Expiry SMS Reminder Cron Job
 * Sends automatic SMS reminders for expiring accounts
 * Run this script daily via cron: 0 9 * * * /usr/bin/php /path/to/cron_send_expiry_sms.php
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
];

echo "════════════════════════════════════════════════════════════════\n";
echo "  Automatic Expiry SMS Reminder - " . date('Y-m-d H:i:s') . "\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get all users with SMS auto-send enabled
    $stmt = $pdo->query("SELECT user_id, api_token, sender_number, base_url, days_before_expiry, expiry_template
                         FROM _sms_settings
                         WHERE auto_send_enabled = 1
                         AND api_token IS NOT NULL
                         AND sender_number IS NOT NULL");

    $settings_list = $stmt->fetchAll();

    if (empty($settings_list)) {
        echo "No users have automatic SMS reminders enabled.\n";
        exit(0);
    }

    echo "Found " . count($settings_list) . " user(s) with automatic SMS enabled.\n\n";

    $total_sent = 0;
    $total_failed = 0;

    foreach ($settings_list as $settings) {
        $user_id = $settings['user_id'];
        $days_before = $settings['days_before_expiry'];

        echo "Processing user ID: $user_id (Send $days_before days before expiry)\n";

        // Calculate target date
        $target_date = date('Y-m-d', strtotime("+$days_before days"));

        // Find accounts expiring on target date with phone numbers
        $stmt = $pdo->prepare("SELECT a.id, a.mac, a.full_name, a.phone_number, a.end_date, a.reseller
                               FROM _accounts a
                               WHERE a.end_date = ?
                               AND a.phone_number IS NOT NULL
                               AND a.phone_number != ''
                               AND a.status = 'active'
                               AND (a.reseller = ? OR ? = (SELECT super_user FROM _users WHERE id = ?))");

        $stmt->execute([$target_date, $user_id, 1, $user_id]);
        $accounts = $stmt->fetchAll();

        if (empty($accounts)) {
            echo "  No accounts expiring on $target_date\n\n";
            continue;
        }

        echo "  Found " . count($accounts) . " account(s) expiring on $target_date\n";

        $recipients = [];
        $log_entries = [];

        foreach ($accounts as $account) {
            // Check if SMS already sent for this account and end_date
            $stmt = $pdo->prepare("SELECT id FROM _sms_logs
                                   WHERE mac = ?
                                   AND message_type = 'expiry_reminder'
                                   AND DATE(sent_at) = CURDATE()
                                   AND status = 'sent'
                                   LIMIT 1");
            $stmt->execute([$account['mac']]);

            if ($stmt->fetch()) {
                echo "    Skipping {$account['mac']} - Already sent today\n";
                continue;
            }

            // Prepare personalized message
            $message = str_replace(
                ['{name}', '{mac}', '{expiry_date}', '{days}'],
                [
                    $account['full_name'] ?? 'Customer',
                    $account['mac'] ?? '',
                    $account['end_date'] ?? '',
                    $days_before
                ],
                $settings['expiry_template']
            );

            $recipients[] = $account['phone_number'];
            $log_entries[] = [
                'account_id' => $account['id'],
                'mac' => $account['mac'],
                'recipient_name' => $account['full_name'],
                'recipient_number' => $account['phone_number'],
                'message' => $message
            ];
        }

        if (empty($recipients)) {
            echo "  No new SMS to send\n\n";
            continue;
        }

        // Send SMS via API
        $api_url = rtrim($settings['base_url'], '/') . '/api/send';

        $payload = [
            'sending_type' => 'webservice',
            'from_number' => $settings['sender_number'],
            'message' => $settings['expiry_template'],
            'params' => [
                'recipients' => $recipients
            ]
        ];

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

        $api_response = json_decode($response, true);

        $status = 'failed';
        $error_message = null;
        $bulk_id = null;

        if ($http_code == 200 && $api_response && isset($api_response['data'])) {
            $status = 'sent';
            $bulk_id = isset($api_response['data']['bulk_id']) ? $api_response['data']['bulk_id'] : null;
            echo "  ✓ SMS sent successfully to " . count($recipients) . " recipient(s)\n";
            $total_sent += count($recipients);
        } else {
            $error_message = $curl_error ? $curl_error : ($api_response['message'] ?? 'Unknown error');
            echo "  ✗ SMS failed: $error_message\n";
            $total_failed += count($recipients);
        }

        // Log SMS
        foreach ($log_entries as $entry) {
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
        }

        echo "\n";
    }

    echo "════════════════════════════════════════════════════════════════\n";
    echo "  Summary:\n";
    echo "  ✓ Sent: $total_sent\n";
    echo "  ✗ Failed: $total_failed\n";
    echo "════════════════════════════════════════════════════════════════\n";

} catch(PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
