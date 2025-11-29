<?php
/**
 * Send Telegram Message
 * Send manual message to selected admins/reseller admins
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

    // Parse permissions to check if reseller has admin-level permissions
    $permissions = explode('|', $current_user['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Check access
    if ($current_user['super_user'] != 1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'message' => 'Access denied']);
        exit;
    }

    // Get bot settings
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        echo json_encode(['error' => 1, 'message' => 'Telegram bot is not configured']);
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $recipient_ids = $_POST['recipient_ids'] ?? [];

    if (empty($message)) {
        echo json_encode(['error' => 1, 'message' => 'Message is required']);
        exit;
    }

    if (empty($recipient_ids)) {
        echo json_encode(['error' => 1, 'message' => 'At least one recipient is required']);
        exit;
    }

    // Parse recipient_ids if it's a string
    if (is_string($recipient_ids)) {
        $recipient_ids = json_decode($recipient_ids, true);
        if (!is_array($recipient_ids)) {
            $recipient_ids = explode(',', $_POST['recipient_ids']);
        }
    }

    // Filter recipients based on access
    // Super admin can send to anyone, reseller admin can only send to other reseller admins
    // Since is_reseller_admin is derived from permissions, we filter in PHP
    $allowed_recipients = [];
    $placeholders = str_repeat('?,', count($recipient_ids) - 1) . '?';
    $sql = "SELECT id, username, super_user, permissions, telegram_chat_id FROM _users
            WHERE id IN ($placeholders)
            AND telegram_chat_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($recipient_ids);
    $potential_recipients = $stmt->fetchAll();

    foreach ($potential_recipients as $recipient) {
        $recipient_perms = explode('|', $recipient['permissions'] ?? '0|0|0|0|0|0');
        $recipient_is_reseller_admin = isset($recipient_perms[2]) && $recipient_perms[2] === '1';

        if ($current_user['super_user'] == 1) {
            // Super admin can send to admins and reseller admins
            if ($recipient['super_user'] == 1 || $recipient_is_reseller_admin) {
                $allowed_recipients[] = $recipient;
            }
        } else if ($is_reseller_admin) {
            // Reseller admin can only send to other reseller admins (not super admins)
            if ($recipient_is_reseller_admin && $recipient['super_user'] != 1) {
                $allowed_recipients[] = $recipient;
            }
        }
    }

    if (empty($allowed_recipients)) {
        echo json_encode(['error' => 1, 'message' => 'No valid recipients found. Make sure they have Telegram linked.']);
        exit;
    }

    // Send messages
    $results = [
        'total' => count($allowed_recipients),
        'sent' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($allowed_recipients as $recipient) {
        $result = sendTelegramMessage($settings['bot_token'], $recipient['telegram_chat_id'], $message);

        logTelegramMessage(
            $pdo,
            $recipient['id'],
            $recipient['telegram_chat_id'],
            $message,
            'manual',
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $result['message_id'] ?? null
        );

        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'user' => $recipient['username'],
                'error' => $result['error']
            ];
        }
    }

    if ($results['sent'] > 0) {
        echo json_encode([
            'error' => 0,
            'message' => "Message sent successfully to {$results['sent']} of {$results['total']} recipients",
            'results' => $results
        ]);
    } else {
        echo json_encode([
            'error' => 1,
            'message' => 'Failed to send message to any recipient',
            'results' => $results
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
