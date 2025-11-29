<?php
/**
 * Telegram Bot Webhook Handler
 * Responds to /start command with user's Chat ID
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../telegram_helper.php');

// Get the incoming update from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Log incoming updates for debugging
error_log("Telegram webhook received: " . $content);

if (!$update) {
    http_response_code(200);
    exit;
}

// Connect to database
$host = $ub_db_host;
$db = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Get bot settings
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        http_response_code(200);
        exit;
    }

    $bot_token = $settings['bot_token'];

    // Handle message
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $first_name = $message['from']['first_name'] ?? 'User';
        $username = $message['from']['username'] ?? '';

        // Handle /start command
        if ($text === '/start' || strpos($text, '/start') === 0) {
            $response_text = "👋 سلام {$first_name}!\n\n";
            $response_text .= "🆔 *شناسه چت شما:*\n";
            $response_text .= "`{$chat_id}`\n\n";
            $response_text .= "📋 این شناسه را کپی کنید و در پنل ShowBox در قسمت Telegram وارد کنید.\n\n";
            $response_text .= "---\n\n";
            $response_text .= "👋 Hello {$first_name}!\n\n";
            $response_text .= "🆔 *Your Chat ID:*\n";
            $response_text .= "`{$chat_id}`\n\n";
            $response_text .= "📋 Copy this ID and paste it in the ShowBox panel under Telegram settings.";

            sendTelegramMessage($bot_token, $chat_id, $response_text, ['parse_mode' => 'Markdown']);
        }
        // Handle /help command
        else if ($text === '/help') {
            $response_text = "🤖 *ShowBox Telegram Bot*\n\n";
            $response_text .= "این ربات برای ارسال اعلان‌های ShowBox استفاده می‌شود.\n\n";
            $response_text .= "*دستورات:*\n";
            $response_text .= "/start - دریافت شناسه چت\n";
            $response_text .= "/help - راهنما\n\n";
            $response_text .= "---\n\n";
            $response_text .= "This bot is used to send ShowBox notifications.\n\n";
            $response_text .= "*Commands:*\n";
            $response_text .= "/start - Get your Chat ID\n";
            $response_text .= "/help - Help";

            sendTelegramMessage($bot_token, $chat_id, $response_text, ['parse_mode' => 'Markdown']);
        }
    }

} catch (Exception $e) {
    error_log("Telegram webhook error: " . $e->getMessage());
}

http_response_code(200);
?>
