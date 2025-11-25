<?php
/**
 * Auto-Logout Settings API
 *
 * GET - Returns current auto-logout timeout setting
 * POST - Updates auto-logout timeout (super admin only)
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

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

    // Ensure _app_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS _app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert default auto_logout_timeout if not exists (5 minutes = 300 seconds)
    $pdo->exec("INSERT IGNORE INTO _app_settings (setting_key, setting_value) VALUES ('auto_logout_timeout', '5')");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Anyone can get the setting (needed for the logout timer)
        $stmt = $pdo->prepare('SELECT setting_value FROM _app_settings WHERE setting_key = ?');
        $stmt->execute(['auto_logout_timeout']);
        $result = $stmt->fetch();

        $timeout = $result ? (int)$result['setting_value'] : 5;

        echo json_encode([
            'error' => 0,
            'auto_logout_timeout' => $timeout,
            'timeout_seconds' => $timeout * 60
        ]);
    }
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Only super admin can update settings
        if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
            echo json_encode(['error' => 1, 'message' => 'Not logged in']);
            exit();
        }

        $username = $_SESSION['username'];

        // Check if user is super admin
        $stmt = $pdo->prepare('SELECT super_user FROM _users WHERE username = ?');
        $stmt->execute([$username]);
        $user_data = $stmt->fetch();

        if (!$user_data || $user_data['super_user'] != 1) {
            echo json_encode(['error' => 1, 'message' => 'Only super admin can change this setting']);
            exit();
        }

        // Get timeout value from POST
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['timeout'])) {
            echo json_encode(['error' => 1, 'message' => 'Missing timeout value']);
            exit();
        }

        $timeout = (int)$input['timeout'];

        // Validate timeout (1-60 minutes, or 0 for disabled)
        if ($timeout < 0 || $timeout > 60) {
            echo json_encode(['error' => 1, 'message' => 'Timeout must be between 0 and 60 minutes (0 = disabled)']);
            exit();
        }

        // Update setting
        $stmt = $pdo->prepare('UPDATE _app_settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([$timeout, 'auto_logout_timeout']);

        echo json_encode([
            'error' => 0,
            'message' => $timeout == 0 ? 'Auto-logout disabled' : "Auto-logout set to {$timeout} minutes",
            'auto_logout_timeout' => $timeout,
            'timeout_seconds' => $timeout * 60
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
