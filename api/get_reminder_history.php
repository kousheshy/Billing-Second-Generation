<?php
/**
 * Get Reminder History
 *
 * Retrieves sent reminder history for a specific date
 * Version: 1.7.8
 */

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
    exit();
}

$username = $_SESSION['username'];

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

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found']);
        exit();
    }

    // Check permissions
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $can_control_stb = isset($permissions[4]) && $permissions[4] === '1';

    if($user_info['super_user'] != 1 && !$can_control_stb) {
        echo json_encode(['error' => 1, 'err_msg' => 'Permission denied. You need STB control permission.']);
        exit();
    }

    // Get date parameter (default to today)
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // Validate date format
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid date format']);
        exit();
    }

    // Build query based on user permissions
    $query = 'SELECT r.*, a.reseller
              FROM _expiry_reminders r
              LEFT JOIN _accounts a ON r.mac = a.mac
              WHERE DATE(r.sent_at) = ?';

    $params = [$date];

    // Filter by ownership if not super admin
    if($user_info['super_user'] != 1) {
        $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

        if(!$is_reseller_admin) {
            // Regular reseller: only their reminders
            $query .= ' AND r.sent_by = ?';
            $params[] = $user_info['id'];
        }
        // Reseller admin can see all reminders
    }

    $query .= ' ORDER BY r.sent_at DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total = count($reminders);
    $sent = 0;
    $failed = 0;

    foreach($reminders as $reminder) {
        if($reminder['status'] === 'sent') {
            $sent++;
        } else {
            $failed++;
        }
    }

    echo json_encode([
        'error' => 0,
        'date' => $date,
        'total' => $total,
        'sent' => $sent,
        'failed' => $failed,
        'reminders' => $reminders
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
}
?>
