<?php

use PHPMailer\PHPMailer\PHPMailer;

// Set JSON header and error handler FIRST before any includes
header('Content-Type: application/json');
ob_start(); // Start output buffering to catch any accidental output

// Global error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean(); // Clear any output buffer
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 1, 'err_msg' => 'Server error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']]);
        exit;
    }
});

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

try {

include(__DIR__ . '/../config.php');
include(__DIR__ . '/api.php');
include(__DIR__ . '/sms_helper.php');

// PHPMailer is optional - only load if available
$phpmailer_path = __DIR__ . '/PHPMailer/src/PHPMailer.php';
if (file_exists($phpmailer_path)) {
    require $phpmailer_path;
}

if(isset($_SESSION['login']))
{

    $session = $_SESSION['login'];

    if($session!=1)
    {
        echo json_encode(['error' => 1, 'err_msg' => 'Not authenticated']);
        exit();
    }

}else{
    echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
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

    // Reseller admins don't have balance - skip credit check for them
    if(!$is_reseller_admin) {
        if($plan_info['price'] > $user_info['balance'])
        {
            $response['error']=1;
            $response['err_msg']="Not enough credit.";

            echo json_encode($response);
            exit();
        }
    }

    $price = (int)$plan_info['price'];


}else
{

    $discount = isset($_POST['discount']) ? (int)$_POST['discount'] : 0;

    // Use strict check: only "0" or empty string means no plan
    $post_plan = trim($_POST['plan'] ?? '');
    if($post_plan !== '' && $post_plan !== '0')
    {
        // Parse plan value (format: "planID-currency")
        $plan_parts = explode('-', $post_plan);
        if(count($plan_parts) == 2) {
            $plan_id = $plan_parts[0];
            $plan_currency = $plan_parts[1];
        } else {
            // Fallback to old format (just planID, use reseller's currency)
            $plan_id = $post_plan;
            $plan_currency = $reseller_info['currency_id'];
        }

        error_log("add_account.php: Looking up plan - external_id=$plan_id, currency_id=$plan_currency");

        $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id=? AND currency_id=?');
        $stmt->execute([$plan_id, $plan_currency]);

        $count = $stmt->rowCount();

        error_log("add_account.php: Plan lookup found $count rows");

        if($count == 0)
        {
            $response['error']=1;
            $response['err_msg']="Selected plan can not be used for this reseller. (external_id=$plan_id, currency=$plan_currency)";

            echo json_encode($response);
            exit();
        }


        $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("add_account.php: Plan info - id=" . $plan_info['id'] . ", name=" . $plan_info['name'] . ", days=" . $plan_info['days']);

        // Only check credit if admin is adding account for a reseller
        // Admin and reseller admin don't need credit when adding their own accounts
        if($user_info['super_user']==1 && !empty($_POST['reseller']))
        {
            // Admin is adding account for a specific reseller - check reseller's credit
            // Skip check if target reseller is a reseller admin
            $stmt_check = $pdo->prepare('SELECT permissions FROM _users WHERE id = ?');
            $stmt_check->execute([$_POST['reseller']]);
            $target_user = $stmt_check->fetch(PDO::FETCH_ASSOC);
            $target_permissions = explode('|', $target_user['permissions'] ?? '0|0|0|0|0');
            $target_is_reseller_admin = isset($target_permissions[2]) && $target_permissions[2] === '1';

            if(!$target_is_reseller_admin && $plan_info['price'] > $reseller_info['balance'])
            {
                $response['error']=1;
                $response['err_msg']="Not enough credit.";

                echo json_encode($response);
                exit();
            }
        }
        elseif($user_info['super_user']==0 && !$is_reseller_admin)
        {
            // Regular reseller is adding their own account - check their credit
            // Reseller admins are exempt from credit check
            if($plan_info['price'] > $reseller_info['balance'])
            {
                $response['error']=1;
                $response['err_msg']="Not enough credit.";

                echo json_encode($response);
                exit();
            }
        }
        // Admin or reseller admin adding their own account (no reseller selected) - no credit check needed

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

// Check if API response is valid before iterating
if($decoded && isset($decoded->results)) {
    // Handle both array and object results
    $results = is_array($decoded->results) ? $decoded->results : (is_object($decoded->results) ? [$decoded->results] : []);
    foreach ($results as $plan)
    {
        if(isset($plan->id)) {
            $plans[$plan->id] = isset($plan->external_id) ? $plan->external_id : $plan->id;
            $plan_names[$plan->id] = isset($plan->name) ? $plan->name : 'Plan '.$plan->id;
        }
    }
} else {
    error_log("Warning: Failed to get tariffs from Stalker API. Response: " . $res);
}





$username=trim($_POST['username']);
$password=trim($_POST['password']);
$name=trim($_POST['name']);
$email=trim($_POST['email']);
$phone_number=trim($_POST['phone_number']);
$account_number=10000000+$last_id;
$mac=trim($_POST['mac']);

// Use strict check for plan value (consistent with above)
$plan_post_value = trim($_POST['plan'] ?? '');
if($plan_post_value === '' || $plan_post_value === '0')
{
    $plan="";
    error_log("add_account.php: No plan selected, tariff_plan will be empty");
}else
{
    // Plan is sent in format "external_id-currency" (e.g., "78-IRR")
    // We need the external_id for Stalker Portal's tariff_plan field
    $plan_parts_for_stalker = explode('-', $plan_post_value);
    $external_id_for_stalker = $plan_parts_for_stalker[0];

    // Use external_id directly for Stalker Portal
    $plan = $external_id_for_stalker;

    error_log("add_account.php: Plan value from POST: " . $plan_post_value . ", external_id for Stalker: " . $plan);
}

$status=$_POST['status'];
$expire_billing_date=trim($_POST['expire_billing_date']);

error_log("add_account.php: Calculating expiration - super_user=" . $user_info['super_user'] . ", POST expire_billing_date=" . ($_POST['expire_billing_date'] ?? 'empty') . ", POST plan=" . $_POST['plan']);
error_log("add_account.php: plan_info set? " . (isset($plan_info) ? 'YES - days=' . ($plan_info['days'] ?? 'null') : 'NO'));

// Determine if plan is "no plan" (unlimited) - use strict check
// Plan value can be "0", "78-IRR", etc. Only "0" or empty means unlimited
$plan_value = trim($_POST['plan'] ?? '');
$is_unlimited_plan = ($plan_value === '' || $plan_value === '0');

error_log("add_account.php: Plan value='$plan_value', is_unlimited=$is_unlimited_plan");

if($user_info['super_user']==1)
{
    if(empty($expire_billing_date))
    {
        if($is_unlimited_plan)
        {
            $expire_billing_date="";
            error_log("add_account.php: Admin with no plan, expire_billing_date set to empty (unlimited)");
        }else
        {
            $now = time();
            $plan_days = isset($plan_info['days']) ? (int)$plan_info['days'] : 30;
            $expire = $now+($plan_days*86400);
            $expire_billing_date=date('Y/m/d', $expire);
            error_log("add_account.php: Admin with plan, calculated expiration: $expire_billing_date (plan_days=$plan_days)");
        }

    } else {
        error_log("add_account.php: Admin provided expire_billing_date: $expire_billing_date");
    }
}else
{
    // For resellers/reseller admins, always calculate expiration from plan
    $now = time();
    $plan_days = isset($plan_info['days']) ? (int)$plan_info['days'] : 30;
    $expire = $now+($plan_days*86400);
    $expire_billing_date=date('Y/m/d', $expire);
    error_log("add_account.php: Reseller calculated expiration: $expire_billing_date (plan_days=$plan_days)");
}



$comment=trim($_POST['comment']);




// Include reseller_id and phone_number in the account data sent to Stalker
$data = 'login='.$username.'&password='.$password.'&full_name='.$name.'&account_number='.$account_number.'&tariff_plan='.$plan.'&status='.$status.'&stb_mac='.$mac.'&end_date='.$expire_billing_date.'&comment='.$comment.'&reseller='.$reseller_info['id'];

// Add phone number if provided
if(!empty($phone_number)) {
    $data .= '&phone='.$phone_number;
}

// DEBUG: Log the data being sent to Stalker
error_log("=== STALKER API CREATE ACCOUNT ===");
error_log("Data being sent: " . $data);
error_log("Reseller ID being sent: " . $reseller_info['id']);

$case = 'accounts';
$op = "POST";

// Check if both servers are the same (avoid duplicate operations)
$dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

if($dual_server_mode)
{
    // Step 1: Create account on Server 2 first
    error_log("Creating account on Server 2...");
    $res2 = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, $data);
    error_log("Server 2 Response: " . $res2);

    $decoded2 = json_decode($res2);

    if(!$decoded2 || $decoded2->status != 'OK')
    {
        $response['error'] = 1;
        $response['err_msg'] = 'Failed to create account on Server 2. ' . ($decoded2 && isset($decoded2->error) ? $decoded2->error : 'Connection error');
        echo json_encode($response);
        exit();
    }
}

// Step 2: Create account on Server 1 (primary)
error_log("Creating account on Server 1...");
$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, $data);

// DEBUG: Log Stalker's response
error_log("Server 1 Response: " . $res);

$decoded = json_decode($res);

// If Server 1 fails, rollback Server 2 (only if dual server mode)
if(!$decoded || $decoded->status != 'OK')
{
    if($dual_server_mode)
    {
        // Delete from Server 2 to rollback
        error_log("Server 1 failed, rolling back Server 2...");
        $del_case = 'accounts';
        $del_op = "DELETE";
        api_send_request($WEBSERVICE_2_URLs[$del_case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $del_case, $del_op, $mac, null);
    }

    $response['error'] = 1;
    $response['err_msg'] = 'Failed to create account on Server 1. ' . ($decoded && isset($decoded->error) ? $decoded->error : 'Connection error');
    echo json_encode($response);
    exit();
}

if($decoded->status == 'OK')
{

    // Use strict check for plan - reuse $plan_post_value from earlier
    $plan_id = (!$is_unlimited_plan && isset($plan_info['id'])) ? $plan_info['id'] : null;

    // Log reseller info for debugging
    error_log("Adding account for reseller - ID: " . $reseller_info['id'] . ", Name: " . $reseller_info['name'] . ", Account Username: " . $username);

    // Convert expire_billing_date to MySQL format for local DB (Y-m-d)
    $local_end_date = !empty($expire_billing_date) ? date('Y-m-d', strtotime($expire_billing_date)) : null;

    $stmt = $pdo->prepare('INSERT INTO _accounts (username, mac, email, phone_number, reseller, plan, end_date, timestamp) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$username, $mac, $email, $phone_number, $reseller_info['id'], $plan_id, $local_end_date, time()]);

    if($price>0)
    {
        // Skip balance deduction for reseller admins (they don't have balance)
        // Check if reseller_info is a reseller admin
        $reseller_permissions = explode('|', $reseller_info['permissions'] ?? '0|0|0|0|0');
        $reseller_is_admin = isset($reseller_permissions[2]) && $reseller_permissions[2] === '1';

        if(!$reseller_is_admin) {
            $new_balance = $reseller_info['balance'] - $price;

            if($new_balance < 0)
            {
                $new_balance = 0;
            }

            $stmt = $pdo->prepare('UPDATE _users SET balance=? WHERE id=?');
            $stmt->execute([$new_balance, $reseller_info['id']]);
        }


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
        $stmt->execute(['system', $reseller_info['id'], -$price, $reseller_info['currency_name'], 0, $details, time()]);

    }




    // Log theme information before sending
    error_log("=== THEME UPDATE DEBUG ===");
    error_log("Reseller ID: " . $reseller_info['id']);
    error_log("Reseller Name: " . $reseller_info['name']);
    error_log("Reseller Theme: " . ($reseller_info['theme'] ?? 'NULL'));
    error_log("Account Username: " . $username);

    $theme_to_apply = !empty($reseller_info['theme']) ? $reseller_info['theme'] : 'HenSoft-TV Realistic-Centered SHOWBOX';
    error_log("Theme to apply: " . $theme_to_apply);

    $data = "key=f4H75Sgf53GH4dd&login=".$username."&theme=".$theme_to_apply;

    // Update theme on Server 2 (only if dual server mode)
    if($dual_server_mode)
    {
        $url2 = $SERVER_2_ADDRESS."/stalker_portal/update_account.php";
        error_log("Updating theme on Server 2: " . $url2);
        $res2 = send_request($url2, "POST", $data);
        error_log("Server 2 theme update response: " . $res2);
    }

    // Update theme on Server 1
    $url = $SERVER_1_ADDRESS."/stalker_portal/update_account.php";
    error_log("Updating theme on Server 1: " . $url);
    $res = send_request($url, "POST", $data);
    error_log("Server 1 theme update response: " . $res);
    error_log("=== END THEME UPDATE DEBUG ===");

    if($res == 'OK')
    {


        
        $plan_name = "";

        if($_POST['plan'] == 0)
        {
            $plan_name = "Unlimited";
        }else
        {
            $plan_name = isset($plan_names[$_POST['plan']]) ? $plan_names[$_POST['plan']] : 'Plan #'.$_POST['plan'];
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

        // Send welcome message to Server 2 (only if dual server mode)
        if($dual_server_mode)
        {
            api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        }
        // Send welcome message to Server 1
        $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

        // Send welcome SMS if phone number is provided
        if (!empty($phone_number)) {
            try {
                // Get the account ID we just created
                $stmt = $pdo->prepare('SELECT id FROM _accounts WHERE mac = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$mac]);
                $account_row = $stmt->fetch(PDO::FETCH_ASSOC);
                $account_id = $account_row ? $account_row['id'] : null;

                // Send welcome SMS (non-blocking - won't affect account creation if it fails)
                // Use reseller_info['id'] to ensure the SMS is sent using the account owner's settings
                sendWelcomeSMS($pdo, $reseller_info['id'], $name, $mac, $phone_number, $expire_billing_date, $account_id);
            } catch (Exception $e) {
                // Silently fail - don't disrupt account creation
                error_log("Welcome SMS failed: " . $e->getMessage());
            }
        }

        // Send push notification to admins (v1.11.41)
        // Only notify when a reseller (not super admin) creates an account
        if ($user_info['super_user'] != 1) {
            try {
                include_once(__DIR__ . '/push_helper.php');
                notifyNewAccount($pdo, $reseller_info['name'], $name ?: $username, $plan_name);
            } catch (Exception $e) {
                // Silently fail - don't disrupt account creation
                error_log("Push notification failed: " . $e->getMessage());
            }
        }

        $response['error']=0;
        $response['err_msg']='';
        // Debug info
        $response['debug'] = [
            'plan_post' => $_POST['plan'],
            'plan_for_stalker' => $plan,
            'expire_billing_date' => $expire_billing_date,
            'plan_info_set' => isset($plan_info) ? 'YES' : 'NO',
            'plan_info_days' => isset($plan_info['days']) ? $plan_info['days'] : 'N/A',
            'super_user' => $user_info['super_user']
        ];

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
    $response['err_msg']=$decoded->error ?? 'Unknown error from Stalker Portal';

    echo json_encode($response);
}

} catch (Exception $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Exception: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}

?>




