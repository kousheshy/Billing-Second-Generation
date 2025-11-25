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


$mac=$_POST['mac'];
$status=$_POST['status'];


// Parse permissions: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

// Regular resellers can only change status of their own accounts
if($user_info['super_user']==0 && !$is_reseller_admin)
{
    $stmt = $pdo->prepare('SELECT * FROM _accounts WHERE reseller = ? AND mac = ?');
    $stmt->execute([$user_info['id'], $mac]);

    $count = $stmt->rowCount();

    if($count == 0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }

}



$data = 'status='.$status.'';
$case = 'accounts';
$op = "PUT";

// Check if both servers are the same (avoid duplicate operations)
$dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

// Update Server 2 first (only if dual server mode)
if($dual_server_mode)
{
    $res2 = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded2 = json_decode($res2);

    if(!$decoded2 || $decoded2->status != 'OK')
    {
        error_log("Warning: Server 2 status change failed for MAC $mac: " . ($decoded2->error ?? 'Unknown error'));
        // Continue to update Server 1
    }
}

// Update Server 1 (primary)
$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

$decoded = json_decode($res);

if($decoded->status == 'OK')
{

    $response['error']=0;
    $response['err_msg']='';

    echo json_encode($response);


}else
{

    $response['error']=1;
    $response['err_msg']=$decoded->error;

    echo json_encode($response);
}




?>