<?php
/**
 * Link Telegram Account
 * Generates a unique link for user to connect their Telegram account
 * Or manually sets the chat_id if provided
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

    // ALL users can link their Telegram account to receive notifications
    // No access restriction needed

    $action = $_POST['action'] ?? 'get_link';

    if ($action === 'set_chat_id') {
        // Manually set chat_id
        $chat_id = trim($_POST['chat_id'] ?? '');

        if (empty($chat_id) || !is_numeric($chat_id)) {
            echo json_encode(['error' => 1, 'message' => 'Valid Chat ID is required']);
            exit;
        }

        // Verify bot is configured
        $settings = getTelegramSettings($pdo);
        if (!$settings || empty($settings['bot_token'])) {
            echo json_encode(['error' => 1, 'message' => 'Telegram bot is not configured']);
            exit;
        }

        // Test sending a message to verify the chat_id
        $test_message = "✅ *اتصال تلگرام موفق*\n\nسلام {$current_user['username']}!\nحساب شما با موفقیت به تلگرام متصل شد.\n\nاز این پس اعلان‌های سیستم را از طریق این ربات دریافت خواهید کرد.";

        $result = sendTelegramMessage($settings['bot_token'], $chat_id, $test_message);

        if (!$result['success']) {
            echo json_encode([
                'error' => 1,
                'message' => 'Could not send test message. Make sure you have started the bot first. Error: ' . $result['error']
            ]);
            exit;
        }

        // Save chat_id
        $stmt = $pdo->prepare('UPDATE _users SET telegram_chat_id = ?, telegram_linked_at = NOW() WHERE id = ?');
        $stmt->execute([$chat_id, $current_user['id']]);

        // Initialize notification settings
        initializeTelegramNotificationSettings($pdo, $current_user['id']);

        echo json_encode([
            'error' => 0,
            'message' => 'Telegram linked successfully! A confirmation message was sent to your Telegram.'
        ]);

    } else if ($action === 'unlink') {
        // Unlink telegram
        $stmt = $pdo->prepare('UPDATE _users SET telegram_chat_id = NULL, telegram_linked_at = NULL WHERE id = ?');
        $stmt->execute([$current_user['id']]);

        echo json_encode([
            'error' => 0,
            'message' => 'Telegram unlinked successfully'
        ]);

    } else {
        // Get link - generate deep link for the bot
        $settings = getTelegramSettings($pdo);

        if (!$settings || empty($settings['bot_username'])) {
            echo json_encode(['error' => 1, 'message' => 'Telegram bot is not configured']);
            exit;
        }

        // Generate a unique token for this user
        $link_token = bin2hex(random_bytes(16));

        // The deep link - user clicks this to start conversation with bot
        $deep_link = "https://t.me/{$settings['bot_username']}?start=link_{$current_user['id']}_{$link_token}";

        echo json_encode([
            'error' => 0,
            'bot_username' => $settings['bot_username'],
            'deep_link' => $deep_link,
            'instructions' => "1. Click the link or scan QR code to open Telegram\n2. Click 'Start' in the bot\n3. The bot will confirm your connection\n\nAlternatively, you can:\n1. Open @{$settings['bot_username']} in Telegram\n2. Send /start command\n3. Copy your Chat ID and paste it here"
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
