<?php
/**
 * Get Reminder Settings
 *
 * Retrieves current expiry reminder configuration for the logged-in user
 * Version: 1.7.8
 */

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');

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

    // Get reminder settings
    $stmt = $pdo->prepare('SELECT * FROM _reminder_settings WHERE user_id = ?');
    $stmt->execute([$user_info['id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return default settings if none exist
    if(!$settings) {
        $default_template = 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.';
        echo json_encode([
            'error' => 0,
            'settings' => [
                'days_before_expiry' => 7,
                'message_template' => $default_template,
                'auto_send_enabled' => 0,
                'last_sweep_at' => null
            ]
        ]);
        exit();
    }

    echo json_encode([
        'error' => 0,
        'settings' => [
            'days_before_expiry' => (int)$settings['days_before_expiry'],
            'message_template' => $settings['message_template'],
            'auto_send_enabled' => (int)$settings['auto_send_enabled'],
            'last_sweep_at' => $settings['last_sweep_at']
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
}
?>
