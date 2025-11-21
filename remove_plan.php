<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include('config.php');

if(isset($_SESSION['login']))
{

    $session = $_SESSION['login'];

    if($session!=1)
    {
        exit();
    }

}else{
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

// Parse permissions: can_edit|can_add|is_reseller_admin|reserved|reserved
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

if($user_info['super_user']!=1 && !$is_reseller_admin)
{
    $response['error']=1;
    $response['err_msg']='Permission denied';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}



$plan_id = $_GET['plan'];
$currency = $_GET['currency'];



$stmt = $pdo->prepare('DELETE FROM _plans WHERE external_id=? AND currency_id=?');
$stmt->execute([$plan_id, $currency]);


$response['error']=0;
$response['err_msg']='';

header('Content-Type: application/json');
echo json_encode($response);


?>