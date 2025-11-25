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

$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);

$user_info = $stmt->fetch(PDO::FETCH_ASSOC);


if($user_info['super_user']!=1)
{
    exit();
}



$id = $_GET['id'];


$stmt = $pdo->prepare('SELECT * FROM _users WHERE currency_id = ?');
$stmt->execute([$id]);

$count = $stmt->rowCount();

if($count > 0)
{
    $response['error']=1;
    $response['err_msg']="You can't remove this currency cause it's assigned to some resellers.";

    echo json_encode($response);
    exit();
}

$stmt = $pdo->prepare('DELETE FROM _currencies WHERE id=?');
$stmt->execute([$id]);


$response['error']=0;
$response['err_msg']='';

echo json_encode($response);


?>