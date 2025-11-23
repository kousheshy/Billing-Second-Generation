<?php
session_start();

// Set JSON header first
header('Content-Type: application/json');

// Disable error display, log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

try {
    include('config.php');

    // Check if user is logged in
    if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
        exit;
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

    // Check if user is admin or reseller admin
    $stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found.']);
        exit;
    }

    // Check permissions: super_user OR reseller admin (index 2 in permissions string)
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if ($user_info['super_user'] != 1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'Unauthorized. Admin or Reseller Admin only.']);
        exit;
    }

    // Get and validate input parameters
    if(!isset($_GET['plan_id']) || !isset($_GET['name']) || !isset($_GET['price']) || !isset($_GET['days'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required parameters']);
        exit;
    }

    $plan_id = intval($_GET['plan_id']);
    $name = trim($_GET['name']);
    $price = trim($_GET['price']);
    $days = trim($_GET['days']);
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;

    // Validate inputs
    if(empty($name)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Plan name is required']);
        exit;
    }

    if(!is_numeric($price) || $price < 0) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid price']);
        exit;
    }

    if(!is_numeric($days) || $days < 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid days']);
        exit;
    }

    // Validate category (optional)
    $valid_categories = ['new_device', 'application', 'renew_device'];
    if ($category && !in_array($category, $valid_categories)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid category']);
        exit;
    }

    // Check if plan exists
    $stmt = $pdo->prepare('SELECT * FROM _plans WHERE id = ?');
    $stmt->execute([$plan_id]);
    $existing_plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_plan) {
        echo json_encode(['error' => 1, 'err_msg' => 'Plan not found']);
        exit;
    }

    // Update the plan
    $stmt = $pdo->prepare('UPDATE _plans SET name=?, price=?, days=?, category=? WHERE id=?');
    $stmt->execute([$name, $price, $days, $category, $plan_id]);

    echo json_encode([
        'error' => 0,
        'err_msg' => '',
        'message' => 'Plan updated successfully'
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'error' => 1,
        'err_msg' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'error' => 1,
        'err_msg' => 'Error: ' . $e->getMessage()
    ]);
}
?>
