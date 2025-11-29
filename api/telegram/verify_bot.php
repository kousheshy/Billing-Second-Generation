<?php
/**
 * Verify Telegram Bot Token
 * Tests if a bot token is valid without saving it
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
    $stmt = $pdo->prepare('SELECT id, super_user FROM _users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();

    if (!$current_user || $current_user['super_user'] != 1) {
        echo json_encode(['error' => 1, 'message' => 'Only super admin can verify bot tokens']);
        exit;
    }

    $bot_token = trim($_POST['bot_token'] ?? '');

    if (empty($bot_token)) {
        echo json_encode(['error' => 1, 'message' => 'Bot token is required']);
        exit;
    }

    // Verify the bot token
    $verification = verifyTelegramBot($bot_token);

    if ($verification['success']) {
        echo json_encode([
            'error' => 0,
            'message' => 'Bot token is valid',
            'bot_id' => $verification['bot_id'],
            'bot_username' => $verification['bot_username'],
            'bot_name' => $verification['bot_name']
        ]);
    } else {
        echo json_encode([
            'error' => 1,
            'message' => 'Invalid bot token: ' . $verification['error']
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
