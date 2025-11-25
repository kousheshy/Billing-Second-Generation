<?php
/**
 * Session Heartbeat API
 *
 * Tracks user activity and checks for session timeout
 *
 * GET - Check if session is still valid (also updates last activity)
 * POST - Update last activity timestamp (heartbeat ping)
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

// Get auto-logout timeout from database
function getAutoLogoutTimeout($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM _app_settings WHERE setting_key = ?');
        $stmt->execute(['auto_logout_timeout']);
        $result = $stmt->fetch();
        return $result ? (int)$result['setting_value'] : 5; // Default 5 minutes
    } catch (Exception $e) {
        return 5; // Default on error
    }
}

// Check if session has expired due to inactivity
function isSessionExpired($timeout_minutes) {
    if ($timeout_minutes <= 0) {
        return false; // Auto-logout disabled
    }

    if (!isset($_SESSION['last_activity'])) {
        // First activity - set it now
        $_SESSION['last_activity'] = time();
        return false;
    }

    $timeout_seconds = $timeout_minutes * 60;
    $inactive_time = time() - $_SESSION['last_activity'];

    return $inactive_time >= $timeout_seconds;
}

// Database connection
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

    // Get timeout setting
    $timeout_minutes = getAutoLogoutTimeout($pdo);

    // Check if user is logged in
    if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
        echo json_encode([
            'error' => 1,
            'expired' => true,
            'message' => 'Not logged in'
        ]);
        exit();
    }

    // Check if session has expired
    if (isSessionExpired($timeout_minutes)) {
        // Session expired - properly destroy session and clear cookie
        $_SESSION = array();

        // Clear the session cookie to prevent session ID reuse
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        echo json_encode([
            'error' => 0,
            'expired' => true,
            'message' => 'Session expired due to inactivity'
        ]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check session validity (used on page load)
        // Update last activity since user is actively loading a page
        $_SESSION['last_activity'] = time();

        $time_remaining = 0;
        if ($timeout_minutes > 0 && isset($_SESSION['last_activity'])) {
            $timeout_seconds = $timeout_minutes * 60;
            $inactive_time = time() - $_SESSION['last_activity'];
            $time_remaining = max(0, $timeout_seconds - $inactive_time);
        }

        echo json_encode([
            'error' => 0,
            'expired' => false,
            'timeout_minutes' => $timeout_minutes,
            'time_remaining_seconds' => $time_remaining,
            'last_activity' => $_SESSION['last_activity']
        ]);
    }
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Heartbeat ping - update last activity
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'error' => 0,
            'expired' => false,
            'message' => 'Activity recorded',
            'last_activity' => $_SESSION['last_activity']
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
