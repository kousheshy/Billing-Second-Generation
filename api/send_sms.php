<?php
/**
 * Send SMS
 * Sends SMS using Faraz SMS (IPPanel Edge) API
 * Supports single number or multiple accounts
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['error' => 1, 'message' => 'Invalid request method']);
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

    // Get user ID
    $stmt = $pdo->prepare('SELECT id FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $user_id = $user_data['id'];

    // Get SMS settings
    $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

    if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
        echo json_encode(['error' => 1, 'message' => 'SMS not configured. Please configure SMS settings first.']);
        exit();
    }

    // Get POST data
    $send_type = isset($_POST['send_type']) ? $_POST['send_type'] : 'manual'; // manual, accounts
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $recipient_number = isset($_POST['recipient_number']) ? trim($_POST['recipient_number']) : '';
    $account_ids = isset($_POST['account_ids']) ? $_POST['account_ids'] : [];

    if (empty($message)) {
        echo json_encode(['error' => 1, 'message' => 'Message is required']);
        exit();
    }

    $recipients = [];
    $log_entries = [];

    if ($send_type === 'manual' && !empty($recipient_number)) {
        // Single manual SMS
        $recipients[] = $recipient_number;
        $log_entries[] = [
            'account_id' => null,
            'mac' => null,
            'recipient_name' => null,
            'recipient_number' => $recipient_number,
            'message' => $message
        ];

    } elseif ($send_type === 'accounts' && !empty($account_ids)) {
        // Multiple accounts
        if (!is_array($account_ids)) {
            $account_ids = json_decode($account_ids, true);
        }

        if (empty($account_ids)) {
            echo json_encode(['error' => 1, 'message' => 'No accounts selected']);
            exit();
        }

        // Get account details
        $placeholders = str_repeat('?,', count($account_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, mac, full_name, phone_number, end_date FROM _accounts WHERE id IN ($placeholders)");
        $stmt->execute($account_ids);
        $accounts = $stmt->fetchAll();

        foreach ($accounts as $account) {
            $phone = !empty($account['phone_number']) ? $account['phone_number'] : null;

            if (empty($phone)) {
                continue; // Skip accounts without phone number
            }

            // Replace placeholders in message
            $personalized_message = str_replace(
                ['{name}', '{mac}', '{expiry_date}'],
                [
                    $account['full_name'] ?? 'Customer',
                    $account['mac'] ?? '',
                    $account['end_date'] ?? ''
                ],
                $message
            );

            $recipients[] = $phone;
            $log_entries[] = [
                'account_id' => $account['id'],
                'mac' => $account['mac'],
                'recipient_name' => $account['full_name'],
                'recipient_number' => $phone,
                'message' => $personalized_message
            ];
        }

        if (empty($recipients)) {
            echo json_encode(['error' => 1, 'message' => 'No valid phone numbers found in selected accounts']);
            exit();
        }

    } else {
        echo json_encode(['error' => 1, 'message' => 'Invalid send type or missing recipients']);
        exit();
    }

    // Prepare API request
    $api_url = rtrim($settings['base_url'], '/') . '/api/send';

    $payload = [
        'sending_type' => 'webservice',
        'from_number' => $settings['sender_number'],
        'message' => $message,
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
    } else {
        $error_message = $curl_error ? $curl_error : ($api_response['message'] ?? 'Unknown error');
    }

    // Log SMS in database
    $sent_count = 0;
    $failed_count = 0;

    foreach ($log_entries as $entry) {
        $sql = "INSERT INTO _sms_logs
                (account_id, mac, recipient_name, recipient_number, message, message_type, sent_by, sent_at, status, api_response, bulk_id, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, 'manual', ?, NOW(), ?, ?, ?, ?, NOW())";

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

        if ($status === 'sent') {
            $sent_count++;
        } else {
            $failed_count++;
        }
    }

    echo json_encode([
        'error' => $status === 'sent' ? 0 : 1,
        'message' => $status === 'sent' ? "SMS sent successfully to $sent_count recipient(s)" : "SMS failed: $error_message",
        'sent_count' => $sent_count,
        'failed_count' => $failed_count,
        'bulk_id' => $bulk_id,
        'api_response' => $api_response
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['error' => 1, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
