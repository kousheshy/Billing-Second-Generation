<?php

session_start();

include(__DIR__ . '/../config.php');

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

$stmt = $pdo->prepare('SELECT us.name,us.balance,us.permissions,us.super_user,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.username = ?');
$stmt->execute([$username]);

$user_info = $stmt->fetch(PDO::FETCH_ASSOC);


if($user_info['super_user']!=1)
{
    exit();
}



$stmt = $pdo->prepare('SELECT us.name,us.balance,us.permissions,us.super_user,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.id = ?');
$stmt->execute([$_GET['id']]);

$reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);

$amount = (int)$_GET['amount'];
$type = (int)$_GET['type'];


if($type==1)
{
    $new_bal = $reseller_info['balance']+$amount;
}

if($type==0)
{
    $new_bal = $reseller_info['balance']-$amount;
}


if($type==0&&$amount>$reseller_info['balance'])
{
    $new_bal = 0;
}


$stmt = $pdo->prepare("UPDATE _users SET balance=? WHERE id=?");
$stmt->execute([$new_bal,$_GET['id']]);


$stmt = $pdo->prepare('INSERT INTO _transactions (creator, for_user, amount, currency, type, details, timestamp) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$user_info['name'], $_GET['id'], $_GET['amount'], $reseller_info['currency_name'], $_GET['type'], $_GET['details'], time()]);


$response['error']=0;
$response['err_msg']='';

echo json_encode($response);


?>