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


if($user_info['super_user']!=1)
{
    exit();
}


$username=trim($_POST['username']);
$password=md5(trim($_POST['password']));
$name=trim($_POST['name']);
$email=trim($_POST['email']);
$max_users=trim($_POST['max_users']);
$theme=trim($_POST['theme']);
if(empty($max_users))
{
    $max_users=0;
}
$currency=$_POST['currency'];
$balance=isset($_POST['balance']) ? floatval($_POST['balance']) : 0;
$use_ip_ranges=$_POST['use_ip_ranges'];
$plans=$_POST['plans'];
$is_admin=$_POST['is_admin'];
$permissions=$_POST['permissions'];



$stmt = $pdo->prepare('INSERT INTO _users (username, password, name, email, max_users, balance, theme, ip_ranges, currency_id, plans, super_user, permissions, timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([$username, $password, $name, $email, $max_users, $balance, $theme, $use_ip_ranges, $currency, $plans, $is_admin, $permissions, time()]);

$response['error']=0;
$response['err_msg']='';

echo json_encode($response);


?>