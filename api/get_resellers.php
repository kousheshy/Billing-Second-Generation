<?php

session_start();

include(__DIR__ . '/../config.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1)
{
    $response['error'] = 1;
    $response['message'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
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

    // Get current user info to check permissions
    $stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user is admin or reseller admin
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if($user_info['super_user'] != 1 && !$is_reseller_admin) {
        $response['error'] = 1;
        $response['message'] = 'Permission denied. Admin or Reseller Admin only.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $stmt = $pdo->prepare('SELECT us.*, cr.name as currency_name FROM _users AS us LEFT OUTER JOIN _currencies AS cr ON us.currency_id=cr.id WHERE us.super_user = 0 ORDER BY us.id DESC');
    $stmt->execute([]);

    $resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add account count for each reseller
    foreach ($resellers as &$reseller) {
        $stmt_count = $pdo->prepare('SELECT COUNT(*) as account_count FROM _accounts WHERE reseller = ?');
        $stmt_count->execute([$reseller['id']]);
        $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $reseller['account_count'] = $count_result['account_count'] ?? 0;
    }
    unset($reseller); // Break reference

    $response['error'] = 0;
    $response['resellers'] = $resellers;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
