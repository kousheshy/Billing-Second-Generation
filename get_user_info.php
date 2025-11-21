<?php

session_start();

include('config.php');

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

    $stmt = $pdo->prepare('SELECT us.*, cr.name as currency_name FROM _users AS us LEFT OUTER JOIN _currencies AS cr ON us.currency_id=cr.id WHERE us.username = ?');
    $stmt->execute([$username]);

    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Parse permissions to check if reseller has admin-level permissions
    // Format: can_edit|can_add|is_reseller_admin|reserved|reserved
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    $user_info['is_reseller_admin'] = $is_reseller_admin;

    // Get view mode preference from request (only for reseller admins)
    // Default to false (My Accounts) for reseller admins
    $viewAllAccounts = isset($_GET['viewAllAccounts']) ? $_GET['viewAllAccounts'] === 'true' : false;

    // Get counts based on user type and view mode
    if($user_info['super_user'] == 1)
    {
        // Super admin always sees all accounts
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts');
        $stmt->execute([]);
    }
    else if($is_reseller_admin && $viewAllAccounts)
    {
        // Reseller admin viewing all accounts
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts');
        $stmt->execute([]);
    }
    else
    {
        // Regular reseller or reseller admin viewing only their own
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts WHERE reseller = ?');
        $stmt->execute([$user_info['id']]);
    }
    $total_accounts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $response['error'] = 0;
    $response['user'] = $user_info;
    $response['total_accounts'] = $total_accounts;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
