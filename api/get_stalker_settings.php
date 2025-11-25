<?php
/**
 * Get Stalker Portal Settings API
 * Returns Stalker Portal connection settings from config.php (Admin only - NOT reseller admin)
 */

session_start();
header('Content-Type: application/json');

include(__DIR__ . '/../config.php');

// Check if user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Check if user is super admin from database (NOT reseller admin)
try {
    $pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4", $ub_db_username, $ub_db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT super_user FROM _users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['super_user'] != 1) {
        echo json_encode(['error' => 1, 'message' => 'Permission denied. Super admin access required.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Return settings from config.php
echo json_encode([
    'error' => 0,
    'settings' => [
        'server_address' => $SERVER_1_ADDRESS ?? '',
        'server_2_address' => $SERVER_2_ADDRESS ?? '',
        'api_username' => $WEBSERVICE_USERNAME ?? '',
        'api_password' => !empty($WEBSERVICE_PASSWORD) ? '********' : '',
        'api_base_url' => $WEBSERVICE_BASE_URL ?? '',
        'api_2_base_url' => $WEBSERVICE_2_BASE_URL ?? '',
        'dual_server_mode_enabled' => isset($DUAL_SERVER_MODE_ENABLED) ? $DUAL_SERVER_MODE_ENABLED : false
    ]
]);
?>
