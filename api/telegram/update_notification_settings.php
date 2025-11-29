<?php
/**
 * Update Telegram Notification Settings
 * Each user can configure which notifications they want to receive
 */

session_start();
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../telegram_helper.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit;
}

$host = $ub_db_host;
$db = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Get current user info
    $stmt = $pdo->prepare('SELECT id, username, super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit;
    }

    // ALL users can update their notification settings
    // No access restriction needed

    // Get notification settings from POST
    $settings = [
        'notify_new_account' => isset($_POST['notify_new_account']) ? 1 : 0,
        'notify_renewal' => isset($_POST['notify_renewal']) ? 1 : 0,
        'notify_expiry' => isset($_POST['notify_expiry']) ? 1 : 0,
        'notify_expired' => isset($_POST['notify_expired']) ? 1 : 0,
        'notify_low_balance' => isset($_POST['notify_low_balance']) ? 1 : 0,
        'notify_new_payment' => isset($_POST['notify_new_payment']) ? 1 : 0,
        'notify_login' => isset($_POST['notify_login']) ? 1 : 0,
        'notify_daily_report' => isset($_POST['notify_daily_report']) ? 1 : 0
    ];

    // Check if settings exist
    $stmt = $pdo->prepare('SELECT id FROM _telegram_notification_settings WHERE user_id = ?');
    $stmt->execute([$current_user['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update
        $sql = "UPDATE _telegram_notification_settings SET
                notify_new_account = ?,
                notify_renewal = ?,
                notify_expiry = ?,
                notify_expired = ?,
                notify_low_balance = ?,
                notify_new_payment = ?,
                notify_login = ?,
                notify_daily_report = ?,
                updated_at = NOW()
                WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $settings['notify_new_account'],
            $settings['notify_renewal'],
            $settings['notify_expiry'],
            $settings['notify_expired'],
            $settings['notify_low_balance'],
            $settings['notify_new_payment'],
            $settings['notify_login'],
            $settings['notify_daily_report'],
            $current_user['id']
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO _telegram_notification_settings
                (user_id, notify_new_account, notify_renewal, notify_expiry, notify_expired,
                 notify_low_balance, notify_new_payment, notify_login, notify_daily_report)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $current_user['id'],
            $settings['notify_new_account'],
            $settings['notify_renewal'],
            $settings['notify_expiry'],
            $settings['notify_expired'],
            $settings['notify_low_balance'],
            $settings['notify_new_payment'],
            $settings['notify_login'],
            $settings['notify_daily_report']
        ]);
    }

    echo json_encode([
        'error' => 0,
        'message' => 'Notification settings saved successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
