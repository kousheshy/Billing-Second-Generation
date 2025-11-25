<?php
/**
 * Update Stalker Portal Settings API
 * Saves Stalker Portal connection settings (Admin only - NOT reseller admin)
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
    $stmt = $pdo->prepare("SELECT id, super_user FROM _users WHERE username = ?");
    $stmt->execute([$username]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser || $currentUser['super_user'] != 1) {
        echo json_encode(['error' => 1, 'message' => 'Permission denied. Super admin access required.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get POST data
$server_address = trim($_POST['server_address'] ?? '');
$server_2_address = trim($_POST['server_2_address'] ?? '');
$api_username = trim($_POST['api_username'] ?? '');
$api_password = $_POST['api_password'] ?? '';
$api_base_url = trim($_POST['api_base_url'] ?? '');
$api_2_base_url = trim($_POST['api_2_base_url'] ?? '');
$test_connection = isset($_POST['test_connection']) && $_POST['test_connection'] == '1';

// Validation
if (empty($server_address)) {
    echo json_encode(['error' => 1, 'message' => 'Server address is required']);
    exit;
}

if (empty($api_username)) {
    echo json_encode(['error' => 1, 'message' => 'API username is required']);
    exit;
}

// Auto-generate base URLs if not provided
if (empty($api_base_url)) {
    $api_base_url = rtrim($server_address, '/') . '/stalker_portal/api/';
}
if (empty($api_2_base_url)) {
    $api_2_base_url = rtrim($server_2_address ?: $server_address, '/') . '/stalker_portal/api/';
}
if (empty($server_2_address)) {
    $server_2_address = $server_address;
}

try {
    $pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4", $ub_db_username, $ub_db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `_stalker_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);

    // Get current password if new one is placeholder or empty
    $currentPassword = '';
    $stmt = $pdo->prepare("SELECT setting_value FROM _stalker_settings WHERE setting_key = 'api_password'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $currentPassword = $row['setting_value'];
    }

    // If password is placeholder (********) or empty, keep the current one
    if ($api_password === '********' || $api_password === '') {
        $api_password = $currentPassword;
    }

    // If still empty and we have config password, use that
    if (empty($api_password) && !empty($WEBSERVICE_PASSWORD)) {
        $api_password = $WEBSERVICE_PASSWORD;
    }

    // Test connection if requested
    if ($test_connection) {
        $test_url = rtrim($api_base_url, '/') . '/accounts/';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $api_username . ":" . $api_password);
        curl_setopt($curl, CURLOPT_URL, $test_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);

        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            echo json_encode(['error' => 1, 'message' => 'Connection failed: ' . $error]);
            exit;
        }

        if ($httpCode === 401) {
            echo json_encode(['error' => 1, 'message' => 'Authentication failed. Check username and password.']);
            exit;
        }

        if ($httpCode !== 200) {
            echo json_encode(['error' => 1, 'message' => 'Connection failed with HTTP code: ' . $httpCode]);
            exit;
        }

        $decoded = json_decode($result);
        if (!$decoded || !isset($decoded->status)) {
            echo json_encode(['error' => 1, 'message' => 'Invalid response from Stalker Portal. Check the API URL.']);
            exit;
        }
    }

    // Update config.php file directly
    $configPath = __DIR__ . '/../config.php';

    if (!file_exists($configPath)) {
        echo json_encode(['error' => 1, 'message' => 'config.php not found']);
        exit;
    }

    if (!is_writable($configPath)) {
        echo json_encode(['error' => 1, 'message' => 'config.php is not writable. Check file permissions.']);
        exit;
    }

    $configContent = file_get_contents($configPath);
    if ($configContent === false) {
        echo json_encode(['error' => 1, 'message' => 'Failed to read config.php']);
        exit;
    }

    // Update Stalker settings in config.php
    $configContent = preg_replace(
        '/\$SERVER_1_ADDRESS\s*=\s*["\'].*?["\'];/',
        '$SERVER_1_ADDRESS = "' . addslashes($server_address) . '";',
        $configContent
    );

    $configContent = preg_replace(
        '/\$SERVER_2_ADDRESS\s*=\s*["\'].*?["\'];/',
        '$SERVER_2_ADDRESS = "' . addslashes($server_2_address) . '";',
        $configContent
    );

    $configContent = preg_replace(
        '/\$WEBSERVICE_USERNAME\s*=\s*["\'].*?["\'];/',
        '$WEBSERVICE_USERNAME = "' . addslashes($api_username) . '";',
        $configContent
    );

    // Only update password if it's not the placeholder
    if ($api_password !== '********' && !empty($api_password)) {
        $configContent = preg_replace(
            '/\$WEBSERVICE_PASSWORD\s*=\s*["\'].*?["\'];/',
            '$WEBSERVICE_PASSWORD = "' . addslashes($api_password) . '";',
            $configContent
        );
    }

    $configContent = preg_replace(
        '/\$WEBSERVICE_BASE_URL\s*=\s*["\'].*?["\'];/',
        '$WEBSERVICE_BASE_URL = "' . addslashes($api_base_url) . '";',
        $configContent
    );

    $configContent = preg_replace(
        '/\$WEBSERVICE_2_BASE_URL\s*=\s*["\'].*?["\'];/',
        '$WEBSERVICE_2_BASE_URL = "' . addslashes($api_2_base_url) . '";',
        $configContent
    );

    // Write updated config back
    if (file_put_contents($configPath, $configContent) === false) {
        echo json_encode(['error' => 1, 'message' => 'Failed to write config.php']);
        exit;
    }

    $message = 'Stalker Portal settings saved to config.php successfully!';
    if ($test_connection) {
        $message .= ' Connection test passed.';
    }

    echo json_encode([
        'error' => 0,
        'message' => $message
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
