<?php
/**
 * Get Telegram Settings
 * Returns bot configuration, user's telegram status, and notification preferences
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
    $stmt = $pdo->prepare('SELECT id, username, super_user, permissions, telegram_chat_id, telegram_linked_at FROM _users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit;
    }

    // Parse permissions to check if reseller has admin-level permissions
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
    $permissions = explode('|', $current_user['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $current_user['is_reseller_admin'] = $is_reseller_admin;

    // ALL users can access Telegram (to link their account and receive notifications)
    // No access restriction - all logged in users can use Telegram features

    // Get bot settings
    $bot_settings = getTelegramSettings($pdo);

    // Get user's notification settings
    $stmt = $pdo->prepare('SELECT * FROM _telegram_notification_settings WHERE user_id = ?');
    $stmt->execute([$current_user['id']]);
    $notification_settings = $stmt->fetch();

    // If no notification settings, use defaults
    if (!$notification_settings) {
        $notification_settings = [
            'notify_new_account' => 1,
            'notify_renewal' => 1,
            'notify_expiry' => 1,
            'notify_expired' => 1,
            'notify_low_balance' => 1,
            'notify_new_payment' => 1,
            'notify_login' => 0,
            'notify_daily_report' => 0
        ];
    }

    // Get templates
    $stmt = $pdo->query('SELECT id, name, template_key, template, description, is_system FROM _telegram_templates ORDER BY is_system DESC, name ASC');
    $templates = $stmt->fetchAll();

    // Get stats
    $stats = [
        'total_sent' => 0,
        'sent_success' => 0,
        'sent_failed' => 0
    ];

    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM _telegram_logs GROUP BY status");
    while ($row = $stmt->fetch()) {
        $stats['total_sent'] += $row['count'];
        if ($row['status'] == 'sent') {
            $stats['sent_success'] = $row['count'];
        } else if ($row['status'] == 'failed') {
            $stats['sent_failed'] = $row['count'];
        }
    }

    // Get list of users who can receive notifications (for broadcast)
    // Super admin can send to ALL users, reseller admin can send to resellers only
    $recipients = [];
    $stmt = $pdo->query("SELECT id, username, super_user, permissions, telegram_chat_id,
                        CASE WHEN telegram_chat_id IS NOT NULL THEN 1 ELSE 0 END as telegram_linked
                        FROM _users WHERE is_observer = 0 ORDER BY super_user DESC, username ASC");
    $all_users = $stmt->fetchAll();

    foreach ($all_users as $user) {
        $user_perms = explode('|', $user['permissions'] ?? '0|0|0|0|0|0');
        $user_is_reseller_admin = isset($user_perms[2]) && $user_perms[2] === '1';
        $user['is_reseller_admin'] = $user_is_reseller_admin ? 1 : 0;
        $user['user_type'] = $user['super_user'] == 1 ? 'Admin' : ($user_is_reseller_admin ? 'Reseller Admin' : 'Reseller');

        if ($current_user['super_user'] == 1) {
            // Super admin can send to ALL users (admins, reseller admins, and resellers)
            $recipients[] = $user;
        } else if ($is_reseller_admin) {
            // Reseller admin can send to resellers and other reseller admins (not super admins)
            if ($user['super_user'] != 1) {
                $recipients[] = $user;
            }
        }
        // Regular resellers cannot send messages (only receive)
    }

    // Only return bot token to super admins
    $bot_token_for_display = null;
    if ($current_user['super_user'] == 1 && !empty($bot_settings['bot_token'])) {
        $bot_token_for_display = $bot_settings['bot_token'];
    }

    echo json_encode([
        'error' => 0,
        'bot_configured' => !empty($bot_settings['bot_token']),
        'bot_username' => $bot_settings['bot_username'] ?? null,
        'bot_token' => $bot_token_for_display,
        'user' => [
            'id' => $current_user['id'],
            'username' => $current_user['username'],
            'is_super_admin' => $current_user['super_user'] == 1,
            'is_reseller_admin' => $is_reseller_admin,
            'is_reseller' => $current_user['super_user'] != 1,
            'can_send_messages' => $current_user['super_user'] == 1 || $is_reseller_admin,
            'telegram_linked' => !empty($current_user['telegram_chat_id']),
            'telegram_linked_at' => $current_user['telegram_linked_at']
        ],
        'notification_settings' => $notification_settings,
        'templates' => $templates,
        'stats' => $stats,
        'recipients' => $recipients
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
