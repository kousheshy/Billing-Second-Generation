<?php

session_start();

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
    exit();
}



$plan = $_GET['plan'];
$currency = strtoupper($_GET['currency']);
$price = trim($_GET['price']);
$days = trim($_GET['days']);



$stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?');
$stmt->execute([$plan, $currency]);

$count = $stmt->rowCount();


if($count > 0)
{
    $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('UPDATE _plans SET external_id=?, currency_id=?, price=? WHERE id=?');
    $stmt->execute([$plan, $currency, $price, $plan_info['id']]);

}else
{
    $stmt = $pdo->prepare('INSERT INTO _plans (external_id, currency_id, price, days) VALUES (?,?,?,?)');
    $stmt->execute([$plan, $currency, $price, $days]);

}


$stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id = ?');
$stmt->execute([$plan]);

$count = $stmt->rowCount();

if($count > 0)
{
    $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('UPDATE _plans SET days=? WHERE external_id=?');
    $stmt->execute([$days, $plan_info['external_id']]);

}



$response['error']=0;
$response['err_msg']='';

echo json_encode($response);


?>