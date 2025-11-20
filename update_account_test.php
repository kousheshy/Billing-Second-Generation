<?php

session_start();
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

include('../reqs/config.php');
include('../api.php');

error_reporting(E_ERROR | E_PARSE);

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

$stmt = $pdo->prepare('SELECT us.id,us.name,us.balance,us.permissions,us.max_users,us.super_user,us.currency_id,us.theme,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.username = ?');
$stmt->execute([$username]);

$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$reseller_selected = false;

if($user_info['super_user']==1)
{
    if(!empty($_POST['reseller']))
    {

        $stmt = $pdo->prepare('SELECT us.id,us.name,us.balance,us.permissions,us.max_users,us.super_user,us.currency_id,us.theme,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.id = ?');
        $stmt->execute([$_POST['reseller']]);
        $reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $reseller_selected = true;

    }else
    {
        $reseller_info=$user_info;
    }
}else{
    $reseller_info=$user_info;
}



$permissions = explode('|', $user_info['permissions']);

$price = 0;
$discount = 0;

if($user_info['super_user']==0)
{

    if($permissions[0]==0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }


    if($permissions[3]==0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }


    $username=$_POST['username'];

    $stmt = $pdo->prepare('SELECT * FROM _accounts WHERE reseller = ? AND username = ?');
    $stmt->execute([$user_info['id'], $username]);

    $count = $stmt->rowCount();

    if($count == 0)
    {
        $response['error']=1;
        $response['err_msg']="Permission denied.";

        echo json_encode($response);
        exit();
    }


    if(!empty($_POST['plan']))
    {
        $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id=? AND currency_id=?');
        $stmt->execute([$_POST['plan'], $user_info['currency_id']]);


        $count = $stmt->rowCount();

        if($count == 0)
        {
            $response['error']=1;
            $response['err_msg']="Error.";

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
    }


}else
{
    $plan_info = null;
    
    $discount = (int)$_POST['discount'];

    if(!empty($_POST['plan']))
    {
        
        if($_POST['plan']!='0')
        {
            $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id=? AND currency_id=?');
            $stmt->execute([$_POST['plan'], $reseller_info['currency_id']]);
    
    
            $count = $stmt->rowCount();
    
            if($count == 0)
            {
                $response['error']=1;
                $response['err_msg']="Selected plan can not be used for this reseller.";
    
                echo json_encode($response);
                exit();
            }
    
            $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($plan_info['price'] > $reseller_info['balance'])
            {
                $response['error']=1;
                $response['err_msg']="Not enough credit.";
        
                echo json_encode($response);
                exit();
            }
    
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

}




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




$username=$_POST['username'];
$password=$_POST['password'];
$name=$_POST['name'];
$email=$_POST['email'];
$phone_number=$_POST['phone_number'];
$mac=$_POST['mac'];




if($user_info['super_user']==0)
{
    
    $last_valid_mac=$_SESSION['last_valid_mac'];
    
    if($last_valid_mac!=$mac)
    {
        $response['error']=1;
        $response['err_msg']="Wrong MAC address.";

        echo json_encode($response);
        exit();
    }
    
}

$plan=$_POST['plan'];
$status=$_POST['status'];
$expire_billing_date=$_POST['expire_billing_date'];

if($user_info['super_user']==1)
{
    
    if(!empty($plan))
    {
            if($plan == 0)
            {
                $plan="";
                $expire_billing_date="";
            }else
            {
                
                $plan=$plans[$_POST['plan']];
                
                $now = time();
                $expire = $now + $plan_info['days']*86400;
                $expire_billing_date=date('Y/m/d', $expire);
                $status=1;
            }
    
    }


}else
{
    
    if(!empty($plan))
    {
        
        if($plan == 0)
        {
            $plan="";
        }else
        {
            $plan=$plans[$_POST['plan']];
        }
        
        $now = time();
        $expire = $now + $plan_info['days']*86400;
        $expire_billing_date=date('Y/m/d', $expire);
    }

}


$comment=$_POST['comment'];





$data = "key=f4H75Sgf53GH4dd&login=".$username."&theme=".$reseller_info['theme'];
$url = "http://51.255.71.232/stalker_portal/update_account.php";


$res = send_request($url, "POST", $data);



if($res == 'OK')
{

// echo 'ooooooooooook<br>'.$plan.'<br>';
$plan = '70';

    if(empty($plan))
    {
        $data = 'login='.$username.'&password='.$password.'&full_name='.$name.'&status='.$status.'&stb_mac='.$mac.'&comment='.$comment.'';
    }else
    {
        $data = 'login='.$username.'&password='.$password.'&full_name='.$name.'&tariff_plan='.$plan.'&status='.$status.'&stb_mac='.$mac.'&end_date='.$expire_billing_date.'&comment='.$comment.'';
    }
echo $data.'<br>';

    $case = 'accounts';
    $op = "PUT";
    
    
    
    $res = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    
    $decoded = json_decode($res);
    
    
    if($decoded->status != 'OK')
    {
        $response['error']=1;
        $response['err_msg']=$decoded->error;

        echo json_encode($response);
    }
    
    
    
    

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

    $decoded = json_decode($res);

    if($decoded->status == 'OK')
    {



        $stmt = $pdo->prepare('SELECT * FROM _accounts WHERE username = ?');
        $stmt->execute([$username]);

        $count = $stmt->rowCount();

        if($count == 0)
        {
            
            if($reseller_selected)
            {
                $rs = $reseller_info['id'];
            }else
            {
                $rs = null;
            }
            
            $stmt = $pdo->prepare('INSERT INTO _accounts (username, mac, email, reseller, timestamp) VALUES (?,?,?,?,?)');
            $stmt->execute([$username, $mac, $email, $rs, time()]);
        }else
        {
            $stmt = $pdo->prepare('UPDATE _accounts SET reseller=?, mac=?, email=? WHERE username=?');
            $stmt->execute([$reseller_info['id'], $mac, $email, $username]);
        }



        if($_POST['plan']!=0)
        {


            if($price>0)
            {
                $new_balance = $user_info['balance'] - $price;

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
                
                $details = 'Plan "'.$plan_names[$_POST['plan']].'" assigned for '.$username.$tmp;

                $stmt = $pdo->prepare('INSERT INTO _transactions (creator, for_user, amount, currency, type, details, timestamp) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute(['system', $reseller_info['id'], $price, $reseller_info['currency_name'], 0, $details, time()]);

            }

        }


        $response['error']=0;
        $response['err_msg']='';

        echo json_encode($response);


    }else
    {

        $response['error']=1;
        $response['err_msg']=$decoded->error;

        echo json_encode($response);
    }



}else
{

    $response['error']=1;
    $response['err_msg']='An error occured. Please try again later.';

    echo json_encode($response);
    exit();

}






?>