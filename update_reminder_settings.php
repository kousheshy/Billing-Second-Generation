<?php
/**
 * Update Reminder Settings
 *
 * Allows users to configure expiry reminder preferences
 * Version: 1.7.8
 */

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include('config.php');

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

    // Check permissions - only STB-enabled users can configure reminders
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $can_control_stb = isset($permissions[4]) && $permissions[4] === '1';

    if($user_info['super_user'] != 1 && !$can_control_stb) {
        echo json_encode(['error' => 1, 'err_msg' => 'Permission denied. You need STB control permission.']);
        exit();
    }

    // Check if settings exist first
    $stmt = $pdo->prepare('SELECT * FROM _reminder_settings WHERE user_id = ?');
    $stmt->execute([$user_info['id']]);
    $current_settings = $stmt->fetch();

    // Determine which fields are being updated
    $update_auto_send = isset($_POST['auto_send_enabled']);
    $update_config = isset($_POST['days_before_expiry']) || isset($_POST['message_template']);

    // Get parameters with fallback to current values or defaults
    if($current_settings) {
        $days_before = isset($_POST['days_before_expiry']) ? (int)$_POST['days_before_expiry'] : $current_settings['days_before_expiry'];
        $message_template = isset($_POST['message_template']) ? trim($_POST['message_template']) : $current_settings['message_template'];
        $auto_send = isset($_POST['auto_send_enabled']) ? (int)$_POST['auto_send_enabled'] : $current_settings['auto_send_enabled'];
    } else {
        // No existing settings - use provided values or defaults
        $days_before = isset($_POST['days_before_expiry']) ? (int)$_POST['days_before_expiry'] : 7;
        $message_template = isset($_POST['message_template']) ? trim($_POST['message_template']) : 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.';
        $auto_send = isset($_POST['auto_send_enabled']) ? (int)$_POST['auto_send_enabled'] : 0;
    }

    // Validate only if these fields are being updated
    if($update_config) {
        if(isset($_POST['days_before_expiry']) && ($days_before < 1 || $days_before > 90)) {
            echo json_encode(['error' => 1, 'err_msg' => 'Days before expiry must be between 1 and 90']);
            exit();
        }

        if(isset($_POST['message_template']) && empty($message_template)) {
            echo json_encode(['error' => 1, 'err_msg' => 'Message template is required']);
            exit();
        }
    }

    if($current_settings) {
        // Update existing settings
        $stmt = $pdo->prepare('UPDATE _reminder_settings
                               SET days_before_expiry = ?, message_template = ?, auto_send_enabled = ?, updated_at = NOW()
                               WHERE user_id = ?');
        $stmt->execute([$days_before, $message_template, $auto_send, $user_info['id']]);
    } else {
        // Insert new settings
        $stmt = $pdo->prepare('INSERT INTO _reminder_settings
                               (user_id, days_before_expiry, message_template, auto_send_enabled, created_at, updated_at)
                               VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$user_info['id'], $days_before, $message_template, $auto_send]);
    }

    echo json_encode([
        'error' => 0,
        'message' => 'Reminder settings updated successfully',
        'settings' => [
            'days_before_expiry' => $days_before,
            'message_template' => $message_template,
            'auto_send_enabled' => $auto_send
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
}
?>
