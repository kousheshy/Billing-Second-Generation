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

    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Parse permissions: can_edit|can_add|is_reseller_admin|reserved|reserved
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $is_observer = $user_info['is_observer'] == 1;

    // Get view mode preference from request (only for reseller admins)
    // Default to false (My Accounts) for reseller admins
    $viewAllAccounts = isset($_GET['viewAllAccounts']) ? $_GET['viewAllAccounts'] === 'true' : false;

    // Super admins and observers always see all accounts
    // Reseller admins can toggle between all accounts and their own
    // Regular resellers only see their own
    if($user_info['super_user'] == 1 || $is_observer)
    {
        $stmt = $pdo->prepare('SELECT a.*, p.name as plan_name FROM _accounts a LEFT JOIN _plans p ON a.plan = p.id ORDER BY a.id DESC');
        $stmt->execute([]);
    }
    else if($is_reseller_admin && $viewAllAccounts)
    {
        // Reseller admin viewing all accounts
        $stmt = $pdo->prepare('SELECT a.*, p.name as plan_name FROM _accounts a LEFT JOIN _plans p ON a.plan = p.id ORDER BY a.id DESC');
        $stmt->execute([]);
    }
    else
    {
        // Regular reseller or reseller admin viewing only their own accounts
        error_log("Getting accounts for reseller - ID: " . $user_info['id'] . ", Username: " . $user_info['username']);
        $stmt = $pdo->prepare('SELECT a.*, p.name as plan_name FROM _accounts a LEFT JOIN _plans p ON a.plan = p.id WHERE a.reseller = ? ORDER BY a.id DESC');
        $stmt->execute([$user_info['id']]);
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($accounts) . " accounts for user " . $user_info['username']);

    // Data is now stored locally - no need to fetch from Stalker Portal every time
    // All account data (full_name, tariff_plan, end_date) is already in the local database
    // It gets synced when the admin clicks "Sync Accounts from Server"

    $response['error'] = 0;
    $response['accounts'] = $accounts;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
