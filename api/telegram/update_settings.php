<?php
/**
 * Update Telegram Bot Settings
 * Only super_admin can update bot token
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
        echo json_encode(['error' => 1, 'message' => 'Only super admin can update bot settings']);
        exit;
    }

    $bot_token = trim($_POST['bot_token'] ?? '');

    if (empty($bot_token)) {
        echo json_encode(['error' => 1, 'message' => 'Bot token is required']);
        exit;
    }

    // Verify the bot token
    $verification = verifyTelegramBot($bot_token);

    if (!$verification['success']) {
        echo json_encode(['error' => 1, 'message' => 'Invalid bot token: ' . $verification['error']]);
        exit;
    }

    // Check if settings exist
    $stmt = $pdo->query('SELECT id FROM _telegram_settings LIMIT 1');
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare('UPDATE _telegram_settings SET bot_token = ?, bot_username = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$bot_token, $verification['bot_username'], $existing['id']]);
    } else {
        // Insert new
        $stmt = $pdo->prepare('INSERT INTO _telegram_settings (bot_token, bot_username, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $stmt->execute([$bot_token, $verification['bot_username']]);
    }

    echo json_encode([
        'error' => 0,
        'message' => 'Bot settings saved successfully',
        'bot_username' => $verification['bot_username'],
        'bot_name' => $verification['bot_name']
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
