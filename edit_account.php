<?php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php');
include('api.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
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

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if POST data is present
    if(empty($_POST['original_username'])) {
        $response['error'] = 1;
        $response['err_msg'] = 'Missing original_username parameter';
        $response['post_data'] = $_POST;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Get form data
    $original_username = trim($_POST['original_username']);
    $new_username = trim($_POST['username']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $plan_id = isset($_POST['plan']) ? intval($_POST['plan']) : 0;
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Get the account from local database
    $stmt = $pdo->prepare('SELECT * FROM _accounts WHERE username = ?');
    $stmt->execute([$original_username]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$account) {
        $response['error'] = 1;
        $response['err_msg'] = 'Account not found';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Check permissions
    // Parse permissions: can_edit|can_add|is_reseller_admin|reserved|reserved
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Super admins and reseller admins have full access
    if($user_info['super_user'] != 1 && !$is_reseller_admin) {
        // Check if reseller owns this account
        if($account['reseller'] != $user_info['id']) {
            $response['error'] = 1;
            $response['err_msg'] = 'Permission denied. This account does not belong to you.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        // Check "can edit accounts" permission (index 0)
        if($permissions[0] == 0) {
            $response['error'] = 1;
            $response['err_msg'] = 'Permission denied. You do not have permission to edit accounts.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }

    // If plan is selected (not 0), calculate new expiration date and renew
    $new_expiration_date = $account['end_date'];
    if($plan_id != 0) {
        // Get plan details
        $stmt = $pdo->prepare('SELECT * FROM _plans WHERE id = ?');
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if($plan) {
            // For resellers, check if they have enough credit
            if($user_info['super_user'] != 1) {
                if($user_info['balance'] < $plan['price']) {
                    $response['error'] = 1;
                    $response['err_msg'] = 'Insufficient balance';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }

                // Deduct credit from reseller
                $new_balance = $user_info['balance'] - $plan['price'];
                $stmt = $pdo->prepare('UPDATE _users SET balance = ? WHERE id = ?');
                $stmt->execute([$new_balance, $user_info['id']]);

                // Record transaction
                $stmt = $pdo->prepare('INSERT INTO _transactions (reseller_id, amount, type, description, timestamp) VALUES (?,?,?,?,?)');
                $stmt->execute([
                    $user_info['id'],
                    -$plan['price'],
                    'renewal',
                    'Account renewal: ' . $original_username . ' - Plan: ' . $plan['name'],
                    time()
                ]);
            }

            // Calculate new expiration date
            $current_expiration = $account['end_date'] ? strtotime($account['end_date']) : time();
            $now = time();

            // If account is expired, start from now, otherwise extend from current expiration
            $base_date = ($current_expiration < $now) ? $now : $current_expiration;
            $new_expiration_timestamp = $base_date + ($plan['days'] * 24 * 60 * 60);
            $new_expiration_date = date('Y-m-d H:i:s', $new_expiration_timestamp);
        }
    }

    // Update account in local database
    $stmt = $pdo->prepare('UPDATE _accounts SET username=?, email=?, phone_number=?, full_name=?, end_date=?, plan=?, status=? WHERE username=?');
    $stmt->execute([
        $new_username,
        $email,
        $phone,
        $name,
        $new_expiration_date,
        ($plan_id != 0) ? $plan_id : $account['plan'],
        $status,
        $original_username
    ]);

    // Update account on Stalker Portal via API
    $mac = $account['mac'];
    $case = 'accounts';
    $op = "PUT";

    // Build URL-encoded data string (Stalker Portal API expects form data, not JSON)
    $data_parts = [];

    // Get the username to send (use new_username if changed, otherwise original)
    $username_to_send = !empty($new_username) ? $new_username : $original_username;
    $data_parts[] = 'login=' . urlencode($username_to_send);

    // Only add password if provided
    if(!empty($password)) {
        $data_parts[] = 'password=' . urlencode($password);
    }

    // Add full_name (use form value or fallback to existing)
    $full_name_value = !empty($name) ? $name : ($account['full_name'] ?? '');
    if(!empty($full_name_value)) {
        $data_parts[] = 'full_name=' . urlencode($full_name_value);
    }

    // Add email if provided
    if(!empty($email)) {
        $data_parts[] = 'email=' . urlencode($email);
    }

    // Add phone if provided
    if(!empty($phone)) {
        $data_parts[] = 'phone=' . urlencode($phone);
    }

    // Add status
    $data_parts[] = 'status=' . urlencode($status);

    // Add MAC address
    $data_parts[] = 'stb_mac=' . urlencode($mac);

    // Add end_date if we have expiration date (format: Y/m/d for Stalker Portal)
    if($new_expiration_date) {
        $data_parts[] = 'end_date=' . urlencode(date('Y/m/d', strtotime($new_expiration_date)));
    } elseif(!empty($account['end_date'])) {
        $data_parts[] = 'end_date=' . urlencode(date('Y/m/d', strtotime($account['end_date'])));
    }

    // Add comment if provided
    if(!empty($comment)) {
        $data_parts[] = 'comment=' . urlencode($comment);
    }

    // Join all parts with & to create URL-encoded string
    $data = implode('&', $data_parts);

    // Send request to Stalker Portal
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded = json_decode($res);

    // Check if Stalker Portal update was successful
    if(isset($decoded->status) && $decoded->status != 'OK') {
        $response['error'] = 1;
        $response['err_msg'] = 'Stalker Portal update failed: ' . ($decoded->error ?? 'Unknown error');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Update theme on Stalker Portal server (to ensure theme matches reseller's current theme)
    // Get reseller's current theme
    $stmt = $pdo->prepare('SELECT theme FROM _users WHERE id = ?');
    $stmt->execute([$account['reseller']]);
    $reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if($reseller_info && !empty($reseller_info['theme'])) {
        // Use the custom server-side script to update theme
        $theme_data = "key=f4H75Sgf53GH4dd&login=".$username_to_send."&theme=".$reseller_info['theme'];
        $theme_url = $SERVER_1_ADDRESS."/stalker_portal/update_account.php";

        // Helper function for simple POST request
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTREDIR, 3);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $theme_data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_URL, $theme_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $theme_result = curl_exec($curl);
        curl_close($curl);

        error_log("edit_account.php: Theme update response: " . $theme_result);
    }

    $response['error'] = 0;
    $response['err_msg'] = '';
    $response['message'] = 'Account updated successfully';

    if($plan_id != 0) {
        $response['message'] .= ' and renewed';
    }

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Update failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
