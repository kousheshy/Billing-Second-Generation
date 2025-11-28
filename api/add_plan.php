<?php
session_start();

// Set JSON header first
header('Content-Type: application/json');

// Disable error display, log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

try {
    include(__DIR__ . '/../config.php');

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
    if(!isset($_GET['tariff_id']) || !isset($_GET['name']) || !isset($_GET['currency']) || !isset($_GET['price']) || !isset($_GET['days'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required parameters']);
        exit;
    }

    $tariff_id = trim($_GET['tariff_id']); // Tariff ID from Stalker Portal
    $name = trim($_GET['name']);
    $currency = strtoupper(trim($_GET['currency']));
    $price = trim($_GET['price']);
    $days = trim($_GET['days']);
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;

    // Validate inputs
    if(empty($tariff_id)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Tariff ID is required']);
        exit;
    }

    if(empty($name)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Plan name is required']);
        exit;
    }

    // Allow '*' for unlimited plans (validated separately based on days=0)
    if(!in_array($currency, ['GBP', 'USD', 'EUR', 'IRR', '*'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid currency']);
        exit;
    }

    if(!is_numeric($price) || $price < 0) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid price']);
        exit;
    }

    // Allow days = 0 for unlimited plans (v1.17.5)
    if(!is_numeric($days) || $days < 0) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid days']);
        exit;
    }

    // Validate category (optional)
    $valid_categories = ['new_device', 'application', 'renew_device'];
    if ($category && !in_array($category, $valid_categories)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid category']);
        exit;
    }

    // Use tariff_id as external_id (the Stalker Portal tariff plan ID)
    $plan = $tariff_id;

    // For unlimited plans (days=0), set currency to '*' so it's available for all currencies (v1.17.5)
    if ((int)$days === 0) {
        $currency = '*';
        $price = 0;
    }

    // Check if this tariff ID + currency combination already exists
    $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?');
    $stmt->execute([$plan, $currency]);

    if($stmt->rowCount() > 0) {
        // This combination already exists, update it
        $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('UPDATE _plans SET name=?, price=?, days=?, category=? WHERE id=?');
        $stmt->execute([$name, $price, $days, $category, $plan_info['id']]);

        echo json_encode([
            'error' => 0,
            'err_msg' => '',
            'message' => 'Plan updated successfully'
        ]);

    } else {
        // Insert new plan
        $stmt = $pdo->prepare('INSERT INTO _plans (external_id, name, currency_id, price, days, category) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$plan, $name, $currency, $price, $days, $category]);

        echo json_encode([
            'error' => 0,
            'err_msg' => '',
            'message' => 'Plan created successfully'
        ]);
    }

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
