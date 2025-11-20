<?php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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


if($user_info['super_user']==0)
{

    if($permissions[1]==0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }

}


$msg=urldecode(trim($_POST['msg']));
$stb_macs = $_POST['stb_macs'];
$stb_macs = explode('|', $stb_macs);


$data = 'msg='.$msg.'';
$case = 'stb_msg';
$op = "POST";


$error_list = [];
$counter_all = 0;
$success_counter = 0;
$error_counter = 0;


foreach ($stb_macs as $mac)
{

    $counter_all++;
    
    $res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded = json_decode($res);

    if($decoded->status == 'OK')
    {
        $success_counter++;

    }else
    {
        $error_counter++;
        array_push($error_list, $mac);
    }

}



if($error_counter == $counter_all)
{
    $response['error']=1;
    $response['err_msg']="Couldn't send message to any STB.";

    echo json_encode($response);
    exit();
}


if($error_counter > 0)
{
    $response['error']=0;
    $response['warning']=1;
    $response['wrn_msg']="Message successfully sent to ".$success_counter." STB's but couldn't send to STB's listed below:<br />";

    foreach ($error_list as $mac)
    {
        $response['wrn_msg']=$response['wrn_msg']."".$mac."<br />";
    }

    echo json_encode($response);
    exit();
}


if($error_counter == 0)
{
    $response['error']=0;
    $response['err_msg']="";

    echo json_encode($response);
    exit();
}






?>