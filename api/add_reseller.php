<?php

session_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set JSON header first
header('Content-Type: application/json');

try {
    include(__DIR__ . '/../config.php');
    include('sms_helper.php'); // Include SMS helper functions

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

    $pdo = new PDO($dsn, $user, $pass, $opt);

    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);

    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user is admin or reseller admin
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if($user_info['super_user']!=1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'Unauthorized. Admin or Reseller Admin only.']);
        exit();
    }

    // Validate required fields
    if(empty($_POST['username']) || empty($_POST['password']) || empty($_POST['name'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required fields']);
        exit();
    }

    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $max_users = trim($_POST['max_users']);
    $theme = trim($_POST['theme']);

    if(empty($max_users)) {
        $max_users = 0;
    }

    $currency = $_POST['currency'];
    $balance = isset($_POST['balance']) ? floatval($_POST['balance']) : 0;
    $use_ip_ranges = isset($_POST['use_ip_ranges']) ? $_POST['use_ip_ranges'] : '';
    $plans = isset($_POST['plans']) ? $_POST['plans'] : '';
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : '0|0|0|0|1|0';
    $is_observer = isset($_POST['is_observer']) ? intval($_POST['is_observer']) : 0;

    // All resellers are created with super_user = 0
    // Admin-level permissions are stored in permissions string (index 2)
    // Delete permission is stored in permissions string (index 3)
    // Observer status is stored in is_observer field
    $stmt = $pdo->prepare('INSERT INTO _users (username, password, name, email, max_users, balance, theme, ip_ranges, currency_id, plans, super_user, is_observer, permissions, timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$username, $password, $name, $email, $max_users, $balance, $theme, $use_ip_ranges, $currency, $plans, 0, $is_observer, $permissions, time()]);

    // Get the newly created reseller's ID
    $new_reseller_id = $pdo->lastInsertId();

    // Initialize SMS settings and templates for the new reseller
    // This allows them to automatically send welcome SMS when adding accounts
    initializeResellerSMS($pdo, $new_reseller_id);

    echo json_encode(['error' => 0, 'err_msg' => '']);

} catch(PDOException $e) {
    // Check if it's a duplicate entry error
    if($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['error' => 1, 'err_msg' => 'Username already exists. Please choose a different username.']);
    } else {
        echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
    }
} catch(Exception $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}
?>