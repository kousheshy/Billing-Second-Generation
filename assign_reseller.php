<?php

session_start();
include('config.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
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

    // Check if user is super admin or reseller admin
    $stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user is super admin or reseller admin (format: can_edit|can_add|is_reseller_admin|can_delete|reserved)
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if($user_info['super_user'] != 1 && !$is_reseller_admin) {
        $response['error'] = 1;
        $response['err_msg'] = 'Only administrators can assign accounts to resellers';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Get parameters
    $account_username = $_POST['username'];
    $reseller_id = $_POST['reseller_id'];

    // Convert empty string to NULL for "Not Assigned"
    if($reseller_id === '' || $reseller_id === null) {
        $reseller_id = null;
    } else {
        $reseller_id = (int)$reseller_id;
    }

    // Update account
    $stmt = $pdo->prepare('UPDATE _accounts SET reseller = ? WHERE username = ?');
    $stmt->execute([$reseller_id, $account_username]);

    // Log the assignment
    $reseller_name = 'Not Assigned';
    if($reseller_id !== null) {
        $stmt = $pdo->prepare('SELECT name FROM _users WHERE id = ?');
        $stmt->execute([$reseller_id]);
        $reseller = $stmt->fetch();
        if($reseller) {
            $reseller_name = $reseller['name'];
        }
    }
    error_log("Admin assigned account '$account_username' to reseller: $reseller_name (ID: " . ($reseller_id ?? 'NULL') . ")");

    $response['error'] = 0;
    $response['message'] = 'Reseller assigned successfully';

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
