<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
include('config.php');
include('api.php');

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

$permissions = explode('|', $user_info['permissions']);


$id = $_POST['id'];


if($user_info['super_user']==0)
{
    // Resellers are not allowed to delete accounts
    $response['error']=1;
    $response['err_msg']="Resellers cannot delete accounts. Please contact admin.";
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}




$data = null;
$case = 'users';
$op = "GET";

$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $id, $data);

$decoded = json_decode($res);

if($decoded->status == 'OK')
{
    $mac = $decoded->results->stb_mac;

}else
{

    $response['error']=1;
    $response['err_msg']=$decoded->error;

    echo json_encode($response);
    exit();
}









// DISABLED: Second server deletion - will be enabled in future
/*
$data = null;
$case = 'accounts';
$op = "DELETE";

$res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

$decoded = json_decode($res);

if($decoded->status != 'OK')
{
    $response['error']=1;
    $response['err_msg']=$decoded->error;
    echo json_encode($response);
    exit();
}
*/






$data = null;
$case = 'accounts';
$op = "DELETE";

$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

$decoded = json_decode($res);

if($decoded->status == 'OK')
{

    $stmt = $pdo->prepare('DELETE FROM _accounts WHERE username = ?');
    $stmt->execute([$id]);

    $response['error']=0;
    $response['err_msg']='';

    echo json_encode($response);


}else
{

    $response['error']=1;
    $response['err_msg']=$decoded->error;

    echo json_encode($response);
    exit();
}




?>