<?php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include(__DIR__ . '/../config.php');
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

    if($permissions[2]==0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }

}


$event=$_POST['event'];



switch ($event)
{
    case "1":
        $event="reboot";
        break;
    case "2":
        $event="reload_portal";
        break;
    case "3":
        $event="update_channels";
        break;
    case "4":
        $event="play_channel";
        $channel_number = $_POST['channel_number'];
        break;
    case "5":
        $event="play_radio_channel";
        $channel_number = $_POST['channel_number'];
        break;
    case "6":
        $event="update_image";
        break;
    case "7":
        $event="show_menu";
        break;
    case "8":
        $event="cut_off";
        break;
}



$stb_macs = $_POST['stb_macs'];
$stb_macs = explode('|', $stb_macs);


if($event == 'play_channel' || $event == 'play_radio_channel')
{
    $data = 'event='.$event.'$channel_number='.$channel_number;
}else
{
    $data = 'event='.$event.'';
}

$case = 'send_event';
$op = "POST";

// Check if both servers are the same (avoid duplicate operations)
$dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

$error_list = [];
$counter_all = 0;
$success_counter = 0;
$error_counter = 0;


foreach ($stb_macs as $mac)
{

    $counter_all++;

    // Step 1: Send to Server 1 (primary) FIRST
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded = json_decode($res);

    if($decoded && $decoded->status == 'OK')
    {
        $success_counter++;

        // Step 2: Send to Server 2 (only if dual server mode and Server 1 succeeded)
        if($dual_server_mode)
        {
            api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        }
    }else
    {
        $error_counter++;
        array_push($error_list, $mac);
    }

}



if($error_counter == $counter_all)
{
    $response['error']=1;
    $response['err_msg']="Couldn't send event to any STB.";

    echo json_encode($response);
    exit();
}


if($error_counter > 0)
{
    $response['error']=0;
    $response['warning']=1;
    $response['wrn_msg']="Event successfully sent to ".$success_counter." STB's but couldn't send to STB's listed below:<br />";

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