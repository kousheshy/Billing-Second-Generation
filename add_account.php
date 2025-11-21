<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
include('config.php');
include('api.php');

use PHPMailer\PHPMailer\PHPMailer;
require 'PHPMailer/src/PHPMailer.php';

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


function send_email($to, $subject, $body)
{
    global $PANEL_NAME;
    $headers = 'From: '.$PANEL_NAME.' <info@showboxtv.tv>' . "\r\n" ;
    $headers .='Reply-To: DONOTREPLY@showboxtv.tv' . "\r\n" ;
    $headers .='X-Mailer: PHP/' . phpversion();
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";   

    
    mail($to,$subject,$body,$headers);
    
}


function send_request($url, $op, $data)
{


    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $op);
    curl_setopt($curl, CURLOPT_POSTREDIR, 3);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;

}



$session_username = $_SESSION['username'];

// Log session info
error_log("=== ADD ACCOUNT REQUEST ===");
error_log("Session username: " . $session_username);

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

$stmt = $pdo->prepare('SELECT us.id,us.name,us.email,us.balance,us.permissions,us.max_users,us.super_user,us.currency_id,us.theme,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.username = ?');
$stmt->execute([$session_username]);

$user_info = $stmt->fetch(PDO::FETCH_ASSOC);



if($user_info['super_user']==1)
{
    if(!empty($_POST['reseller']))
    {

        $stmt = $pdo->prepare('SELECT us.id,us.name,us.email,us.balance,us.permissions,us.max_users,us.super_user,us.currency_id,us.theme,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.id = ?');
        $stmt->execute([$_POST['reseller']]);
        $reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);

    }else
    {
        $reseller_info=$user_info;
    }
}else{
    $reseller_info=$user_info;
}



// Parse permissions: can_edit|can_add|is_reseller_admin|reserved|reserved
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

$price = 0;
$discount = 0;

// Super admins and reseller admins have full access
if($user_info['super_user']==0 && !$is_reseller_admin)
{
    // Check "can add accounts" permission (index 1)
    if($permissions[1]==0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied. You do not have permission to add accounts.";

        echo json_encode($response);
        exit();
    }


    $stmt = $pdo->prepare('SELECT * FROM _accounts WHERE reseller = ?');
    $stmt->execute([$user_info['id']]);

    $count = $stmt->rowCount();

    $max_users = (int)$user_info['max_users'];

    if($max_users > 0)
    {
        if($count >= $max_users)
        {
            $response['error']=1;
            $response['err_msg']="You can't add a new account cause you have reached the maximum number of accounts.";

            echo json_encode($response);
            exit();
        }
    }



    // Parse plan value (format: "planID-currency")
    $plan_parts = explode('-', $_POST['plan']);
    if(count($plan_parts) == 2) {
        $plan_id = $plan_parts[0];
        $plan_currency = $plan_parts[1];
    } else {
        // Fallback to old format (just planID, use user's currency)
        $plan_id = $_POST['plan'];
        $plan_currency = $user_info['currency_id'];
    }

    $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id=? AND currency_id=?');
    $stmt->execute([$plan_id, $plan_currency]);


    $count = $stmt->rowCount();

    if($count == 0)
    {
        $response['error']=1;
        $response['err_msg']="Error. Plan not found.";

        echo json_encode($response);
        exit();
    }


    $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);


    if($plan_info['price'] > $user_info['balance'])
    {
        $response['error']=1;
        $response['err_msg']="Not enough credit.";

        echo json_encode($response);
        exit();
    }

    $price = (int)$plan_info['price'];


}else
{

    $discount = isset($_POST['discount']) ? (int)$_POST['discount'] : 0;

    if($_POST['plan']!=0)
    {
        // Parse plan value (format: "planID-currency")
        $plan_parts = explode('-', $_POST['plan']);
        if(count($plan_parts) == 2) {
            $plan_id = $plan_parts[0];
            $plan_currency = $plan_parts[1];
        } else {
            // Fallback to old format (just planID, use reseller's currency)
            $plan_id = $_POST['plan'];
            $plan_currency = $reseller_info['currency_id'];
        }

        $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id=? AND currency_id=?');
        $stmt->execute([$plan_id, $plan_currency]);

        $count = $stmt->rowCount();

        if($count == 0)
        {
            $response['error']=1;
            $response['err_msg']="Selected plan can not be used for this reseller.";

            echo json_encode($response);
            exit();
        }


        $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Only check credit if admin is adding account for a reseller
        // Admin doesn't need credit when adding their own accounts
        if($user_info['super_user']==1 && !empty($_POST['reseller']))
        {
            // Admin is adding account for a specific reseller - check reseller's credit
            if($plan_info['price'] > $reseller_info['balance'])
            {
                $response['error']=1;
                $response['err_msg']="Not enough credit.";

                echo json_encode($response);
                exit();
            }
        }
        elseif($user_info['super_user']==0)
        {
            // Reseller is adding their own account - check their credit
            if($plan_info['price'] > $reseller_info['balance'])
            {
                $response['error']=1;
                $response['err_msg']="Not enough credit.";

                echo json_encode($response);
                exit();
            }
        }
        // Admin adding their own account (no reseller selected) - no credit check needed

        $price = (int)$plan_info['price'];
        
        
        if($discount > $price)
        {
            $response['error']=1;
            $response['err_msg']="Discount can not be larger than plan price.";
    
            echo json_encode($response);
            exit();
        }
        
        $price = $price - $discount;
        
    }

}













$stmt = $pdo->prepare('SELECT * FROM _accounts ORDER BY id DESC LIMIT 0, 1');
$stmt->execute([]);

$row_info = $stmt->fetch(PDO::FETCH_ASSOC);
$last_id = $row_info ? (int)$row_info['id'] : 0;



$case = 'tariffs';
$op = "GET";

$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

$decoded = json_decode($res);

$plans = [];
$plan_names = [];

foreach ($decoded->results as $plan)
{
    $plans[$plan->id]=$plan->external_id;
    $plan_names[$plan->id]=$plan->name;
}





$username=trim($_POST['username']);
$password=trim($_POST['password']);
$name=trim($_POST['name']);
$email=trim($_POST['email']);
$phone_number=trim($_POST['phone_number']);
$account_number=10000000+$last_id;
$mac=trim($_POST['mac']);

if($_POST['plan'] == 0)
{
    $plan="";
}else
{
    $plan=$plans[$_POST['plan']];
}

$status=$_POST['status'];
$expire_billing_date=trim($_POST['expire_billing_date']);


if($user_info['super_user']==1)
{
    if(empty($expire_billing_date))
    {
        if($_POST['plan'] == 0)
        {
            $expire_billing_date="";
        }else
        {
            $now = time();
            $expire = $now+($plan_info['days']*86400);
            $expire_billing_date=date('Y/m/d', $expire);
        }

    }
}else
{
    $now = time();
    $expire = $now+($plan_info['days']*86400);
    $expire_billing_date=date('Y/m/d', $expire);
}



$comment=trim($_POST['comment']);




// DISABLED: Second server creation - will be enabled in future
/*
$data = 'login='.$username.'&password='.$password.'&full_name='.$name.'&account_number='.$account_number.'&tariff_plan='.$plan.'&status='.$status.'&stb_mac='.$mac.'&end_date='.$expire_billing_date.'&comment='.$comment.'';
$case = 'accounts';
$op = "POST";
$res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, $data);

$decoded = json_decode($res);

if(!$decoded || $decoded->status != 'OK')
{

    $data = null;
    $case = 'accounts';
    $op = "DELETE";

    $res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

    $decoded = json_decode($res);


    $response['error']=1;
    $response['err_msg']='Failed to create account on server. ' . ($decoded && isset($decoded->error) ? $decoded->error : 'Connection error');

    echo json_encode($response);
    exit();

}
*/




$data = 'login='.$username.'&password='.$password.'&full_name='.$name.'&account_number='.$account_number.'&tariff_plan='.$plan.'&status='.$status.'&stb_mac='.$mac.'&end_date='.$expire_billing_date.'&comment='.$comment.'';
$case = 'accounts';
$op = "POST";
$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, $data);

$decoded = json_decode($res);

if($decoded->status == 'OK')
{

    $plan_id = ($_POST['plan'] != 0 && isset($plan_info['id'])) ? $plan_info['id'] : null;

    // Log reseller info for debugging
    error_log("Adding account for reseller - ID: " . $reseller_info['id'] . ", Name: " . $reseller_info['name'] . ", Account Username: " . $username);

    $stmt = $pdo->prepare('INSERT INTO _accounts (username, mac, email, reseller, plan, timestamp) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$username, $mac, $email, $reseller_info['id'], $plan_id, time()]);

    if($price>0)
    {
        $new_balance = $reseller_info['balance'] - $price;

        if($new_balance < 0)
        {
            $new_balance = 0;
        }

        $stmt = $pdo->prepare('UPDATE _users SET balance=? WHERE id=?');
        $stmt->execute([$new_balance, $reseller_info['id']]);
        
        
        $tmp = '';

        if(!empty($name))
        {
            $tmp = ' ,'.$name.' , '.$mac.'';
        }else
        {
            $tmp = ' ,'.$mac.'';
        }

        // Use plan name from local database instead of Stalker Portal
        $plan_name = isset($plan_info['name']) ? $plan_info['name'] : 'Plan #'.$_POST['plan'];
        $details = 'Plan "'.$plan_name.'" assigned for '.$username.$tmp;

        $stmt = $pdo->prepare('INSERT INTO _transactions (creator, for_user, amount, currency, type, details, timestamp) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute(['system', $reseller_info['id'], $price, $reseller_info['currency_name'], 0, $details, time()]);

    }




    // DISABLED: Second server update - will be enabled in future
    // $data = "key=f4H75Sgf53GH4dd&login=".$username."&theme=".$reseller_info['theme'];
    // $url = $SERVER_2_ADDRESS."/stalker_portal/update_account.php";
    // send_request($url, "POST", $data);

    $data = "key=f4H75Sgf53GH4dd&login=".$username."&theme=".$reseller_info['theme'];
    $url = $SERVER_1_ADDRESS."/stalker_portal/update_account.php";

    $res = send_request($url, "POST", $data);

    // Log the update_account response
    error_log("update_account.php response: " . $res);

    if($res == 'OK')
    {


        
        $plan_name = "";

        if($_POST['plan'] == 0)
        {
            $plan_name = "Unlimited";
        }else
        {
            $plan_name = $plan_names[$_POST['plan']];
        }

        $msg_body = '<p>'.$PANEL_NAME.' account successfully created.</p><br /><p>Here are the details</p><p><span style="text-decoration: bold;">Name: </span><span>'.$name.'</span></p><p><span style="text-decoration: bold;">Username: </span><span>'.$username.'</span></p><p><span style="text-decoration: bold;">MAC: </span><span>'.$mac.'</span></p><p><span style="text-decoration: bold;">Password: </span><span>'.$password.'</span></p><p><span style="text-decoration: bold;">Plan: </span><span>'.$plan_name.'</span></p><p><span style="text-decoration: bold;">Valid To: </span><span>'.$expire_billing_date.'</span></p>';


        if(!empty($reseller_info['email']))
        {
            send_email($reseller_info['email'],''.$PANEL_NAME.' Account Created', $msg_body);
        }
        

        if(!empty($email))
        {
            send_email($email,''.$PANEL_NAME.' Account Created', $msg_body);
        }


        $msg_body = '<p>'.$PANEL_NAME.' account successfully created.</p><br /><p>Here are the details</p><p><span style="text-decoration: bold;">Reseller: </span><span>'.$reseller_info['name'].'</span></p><p><span style="text-decoration: bold;">Name: </span><span>'.$name.'</span></p><p><span style="text-decoration: bold;">Username: </span><span>'.$username.'</span></p><p><span style="text-decoration: bold;">MAC: </span><span>'.$mac.'</span></p><p><span style="text-decoration: bold;">Password: </span><span>'.$password.'</span></p><p><span style="text-decoration: bold;">Plan: </span><span>'.$plan_name.'</span></p><p><span style="text-decoration: bold;">Valid To: </span><span>'.$expire_billing_date.'</span></p>';


        $stmt = $pdo->prepare('SELECT * FROM _users WHERE super_user = ? AND username <> "admin"');
        $stmt->execute([1]);
        
        $to = "";

        foreach ($stmt as $row)
        {

             $to = $to.$row['email'].",";

        }
        
        $to = rtrim($to, ",");

        send_email($to,''.$PANEL_NAME.' Account Created', $msg_body);
        
        
        
        $data = 'msg='.$WELCOME_MSG.'';
        $case = 'stb_msg';
        $op = "POST";

        // DISABLED: Second server welcome message - will be enabled in future
        // $res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

        $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        
    

        $response['error']=0;
        $response['err_msg']='';

        echo json_encode($response);

    }else
    {



        $data = null;
        $case = 'accounts';
        $op = "DELETE";

        $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

        $decoded = json_decode($res);

        if($decoded->status == 'OK')
        {

            $stmt = $pdo->prepare('DELETE FROM _accounts WHERE username = ?');
            $stmt->execute([$username]);

            $response['error']=1;
            $response['err_msg']='An error occured. Please try again later.';

            echo json_encode($response);
            exit();


        }else
        {

            $response['error']=1;
            $response['err_msg']='An error occured. Please try again later.';

            echo json_encode($response);
            exit();
        }



    }




}else
{

    $response['error']=1;
    $response['err_msg']=$decoded->error;

    echo json_encode($response);
}




?>




