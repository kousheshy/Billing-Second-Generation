<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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

$pdo = new PDO($dsn, $user, $pass, $opt);

// Get current user info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Only super users can assign plans
if($user_info['super_user'] != 1)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$reseller_id = $_POST['reseller_id'];
$plans = isset($_POST['plans']) ? $_POST['plans'] : '';

// Get reseller info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE id = ?');
$stmt->execute([$reseller_id]);
$reseller = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$reseller)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Reseller not found';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Update plans
$stmt = $pdo->prepare('UPDATE _users SET plans = ? WHERE id = ?');
$stmt->execute([$plans, $reseller_id]);

$response['error'] = 0;
$response['err_msg'] = '';
$response['plans'] = $plans;

header('Content-Type: application/json');
echo json_encode($response);

?>
