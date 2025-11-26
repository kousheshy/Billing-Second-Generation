<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
include(__DIR__ . '/../config.php');
include('api.php');
include('audit_helper.php');

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

// Check if both servers are the same (avoid duplicate operations)
$dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

$error_list = [];
$counter_all = 0;
$success_counter = 0;
$error_counter = 0;


foreach ($stb_macs as $mac)
{

    $counter_all++;

    // Send to Server 2 (only if dual server mode)
    if($dual_server_mode)
    {
        api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    }

    // Send to Server 1 (primary)
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded = json_decode($res);

    if($decoded && $decoded->status == 'OK')
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
    // Audit log: Partial bulk message sent (v1.13.0)
    if($success_counter > 0) {
        logAuditEvent($pdo, 'send', 'stb_message', null, "Bulk: {$success_counter}/{$counter_all} devices", null,
            ['message' => $msg, 'success_count' => $success_counter, 'failed_count' => $error_counter, 'failed_devices' => $error_list],
            "Bulk message sent to {$success_counter}/{$counter_all} devices ({$error_counter} failed)");
    }

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
    // Audit log: Bulk message sent to STBs (v1.13.0)
    $mac_list = implode(', ', $stb_macs);
    logAuditEvent($pdo, 'send', 'stb_message', null, "Bulk: {$success_counter} devices", null,
        ['message' => $msg, 'devices' => $stb_macs], "Bulk message sent to {$success_counter} devices");

    $response['error']=0;
    $response['err_msg']="";

    echo json_encode($response);
    exit();
}






?>