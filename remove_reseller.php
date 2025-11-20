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


if($user_info['super_user']!=1)
{
    $response['error']=1;
    $response['err_msg']='Permission denied';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id == 0)
{
    $response['error']=1;
    $response['err_msg']='Invalid reseller ID';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


$stmt = $pdo->prepare('SELECT * FROM _users WHERE id = ?');
$stmt->execute([$id]);

$reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if reseller has any accounts
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts WHERE reseller = ?');
$stmt->execute([$id]);
$account_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if($account_count > 0)
{
    $response['error']=1;
    $response['err_msg']='Cannot delete reseller with active accounts. Please delete all accounts first.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$stmt = $pdo->prepare('DELETE FROM _users WHERE id=?');
$stmt->execute([$id]);

// Note: Transaction history for this reseller will remain in _transactions table
// with the for_user ID, even though the user is deleted



$response['error']=0;
$response['err_msg']='';

header('Content-Type: application/json');
echo json_encode($response);


?>