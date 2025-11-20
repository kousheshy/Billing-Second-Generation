<?php


session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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


$old_pass=trim($_POST['old_pass']);
$new_pass=md5(trim($_POST['new_pass']));
$renew_pass=trim($_POST['renew_pass']);



$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ? AND password = ?');
$stmt->execute([$username, md5($old_pass)]);

$count = $stmt->rowCount();

if($count==0)
{
    $response['error']=1;
    $response['err_msg']='Old password is incorrect.';

    echo json_encode($response);
    exit();
}




$stmt = $pdo->prepare('UPDATE _users SET password=? WHERE id=?');
$stmt->execute([$new_pass, $user_info['id']]);

$response['error']=0;
$response['err_msg']='';

echo json_encode($response);


?>