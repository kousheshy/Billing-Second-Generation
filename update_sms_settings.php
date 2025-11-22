<?php
/**
 * Update SMS Settings
 * Updates SMS API configuration for the logged-in user
 */

session_start();
include('config.php');

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

    // Get POST data
    $api_token = isset($_POST['api_token']) ? trim($_POST['api_token']) : '';
    $sender_number = isset($_POST['sender_number']) ? trim($_POST['sender_number']) : '';
    $base_url = isset($_POST['base_url']) ? trim($_POST['base_url']) : 'https://edge.ippanel.com/v1';
    $auto_send_enabled = isset($_POST['auto_send_enabled']) ? (int)$_POST['auto_send_enabled'] : 0;
    $enable_multistage_reminders = isset($_POST['enable_multistage_reminders']) ? (int)$_POST['enable_multistage_reminders'] : 1;
    $days_before_expiry = isset($_POST['days_before_expiry']) ? (int)$_POST['days_before_expiry'] : 7;
    $expiry_template = isset($_POST['expiry_template']) ? trim($_POST['expiry_template']) : '';

    // Validate days before expiry
    if ($days_before_expiry < 1 || $days_before_expiry > 30) {
        echo json_encode(['error' => 1, 'message' => 'Days before expiry must be between 1 and 30']);
        exit();
    }

    // Insert or update settings
    $sql = "INSERT INTO _sms_settings
            (user_id, api_token, sender_number, base_url, auto_send_enabled, enable_multistage_reminders, days_before_expiry, expiry_template, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            api_token = VALUES(api_token),
            sender_number = VALUES(sender_number),
            base_url = VALUES(base_url),
            auto_send_enabled = VALUES(auto_send_enabled),
            enable_multistage_reminders = VALUES(enable_multistage_reminders),
            days_before_expiry = VALUES(days_before_expiry),
            expiry_template = VALUES(expiry_template),
            updated_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $user_id,
        $api_token,
        $sender_number,
        $base_url,
        $auto_send_enabled,
        $enable_multistage_reminders,
        $days_before_expiry,
        $expiry_template
    ]);

    echo json_encode([
        'error' => 0,
        'message' => 'SMS settings updated successfully'
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
