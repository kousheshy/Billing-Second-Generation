<?php

session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../config.php');
include('api.php');
include('sms_helper.php'); // Include SMS helper functions
include('audit_helper.php'); // Include audit log helper

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
    $discount = isset($_POST['discount']) ? intval($_POST['discount']) : 0;

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

    // Debug: Log received plan_id
    error_log("edit_account.php: Received plan_id = " . $plan_id . " (type: " . gettype($plan_id) . ")");
    error_log("edit_account.php: POST plan value = " . ($_POST['plan'] ?? 'not set'));

    if($plan_id != 0) {
        // Get plan details
        $stmt = $pdo->prepare('SELECT * FROM _plans WHERE id = ?');
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: Log plan lookup result
        error_log("edit_account.php: Plan lookup result = " . ($plan ? json_encode($plan) : 'NOT FOUND'));

        if($plan) {
            // Get account's reseller info to validate currency match
            $account_reseller_id = $account['reseller'];
            if ($account_reseller_id) {
                $stmt = $pdo->prepare('SELECT currency_id FROM _users WHERE id = ?');
                $stmt->execute([$account_reseller_id]);
                $account_reseller = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($account_reseller) {
                    // Normalize IRT to IRR for comparison
                    $plan_curr = ($plan['currency_id'] === 'IRT') ? 'IRR' : $plan['currency_id'];
                    $reseller_curr = ($account_reseller['currency_id'] === 'IRT') ? 'IRR' : $account_reseller['currency_id'];

                    if ($plan_curr !== $reseller_curr) {
                        $response['error'] = 1;
                        $response['err_msg'] = "Plan currency ($plan_curr) does not match account's reseller currency ($reseller_curr). Please select a plan with matching currency.";
                        error_log("edit_account.php: Currency mismatch - Plan: $plan_curr, Reseller: $reseller_curr");
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit();
                    }
                }
            }

            // Calculate final price after discount (only super admin and reseller admin can apply discount)
            $final_price = (int)$plan['price'];
            if ($discount > 0 && ($user_info['super_user'] == 1 || $is_reseller_admin)) {
                // Validate discount doesn't exceed plan price
                if ($discount > $final_price) {
                    $response['error'] = 1;
                    $response['err_msg'] = 'Discount cannot exceed plan price';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }
                $final_price = $final_price - $discount;
                error_log("edit_account.php: Applied discount of $discount. Final price: $final_price (original: {$plan['price']})");
            }

            // For resellers (not super admins or reseller admins), check if they have enough credit
            // Reseller admins don't have balance - they use admin's resources
            if($user_info['super_user'] != 1 && !$is_reseller_admin) {
                if($user_info['balance'] < $final_price) {
                    $response['error'] = 1;
                    $response['err_msg'] = 'Insufficient balance';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }

                // Deduct credit from reseller (using final_price after discount)
                $new_balance = $user_info['balance'] - $final_price;
                $stmt = $pdo->prepare('UPDATE _users SET balance = ? WHERE id = ?');
                $stmt->execute([$new_balance, $user_info['id']]);

                // Record transaction with final price
                $details = 'Account renewal: ' . $original_username . ' - Plan: ' . $plan['name'];
                if ($discount > 0) {
                    $details .= ' (Discount: ' . $discount . ')';
                }
                $stmt = $pdo->prepare('INSERT INTO _transactions (creator, for_user, amount, type, details, timestamp) VALUES (?,?,?,?,?,?)');
                $stmt->execute([
                    $user_info['username'],
                    $user_info['id'],
                    -$final_price,
                    1, // type: 1 for renewal
                    $details,
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

            // Debug: Log expiration calculation
            error_log("edit_account.php: Plan days = " . $plan['days']);
            error_log("edit_account.php: Current expiration = " . $account['end_date'] . " (timestamp: $current_expiration)");
            error_log("edit_account.php: Base date used = " . date('Y-m-d H:i:s', $base_date) . " (expired: " . ($current_expiration < $now ? 'yes' : 'no') . ")");
            error_log("edit_account.php: New expiration = " . $new_expiration_date);
        } else {
            error_log("edit_account.php: Plan NOT FOUND for id = " . $plan_id);
        }
    } else {
        error_log("edit_account.php: No plan selected (plan_id = 0), skipping renewal");
    }

    // Update account in local database
    error_log("edit_account.php: Updating local DB - end_date = " . $new_expiration_date);
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
    error_log("edit_account.php: Local DB rows affected = " . $stmt->rowCount());

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

    // Check if both servers are the same (avoid duplicate operations)
    $dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

    // Update on Server 2 first (only if dual server mode)
    if($dual_server_mode) {
        $res2 = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        $decoded2 = json_decode($res2);

        if(!$decoded2 || $decoded2->status != 'OK') {
            error_log("Warning: Server 2 update failed for $username_to_send: " . ($decoded2->error ?? 'Unknown error'));
            // Continue to update Server 1 - don't fail the whole operation
        }
    }

    // Send request to Stalker Portal (Server 1 - primary)
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

        // Update theme on Server 2 (only if dual server mode)
        if($dual_server_mode) {
            $theme_url_2 = $SERVER_2_ADDRESS."/stalker_portal/update_account.php";
            $curl2 = curl_init();
            curl_setopt($curl2, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl2, CURLOPT_POSTREDIR, 3);
            curl_setopt($curl2, CURLOPT_POSTFIELDS, $theme_data);
            curl_setopt($curl2, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl2, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl2, CURLOPT_URL, $theme_url_2);
            curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
            $theme_result_2 = curl_exec($curl2);
            curl_close($curl2);
            error_log("edit_account.php: Server 2 theme update response: " . $theme_result_2);
        }

        // Update theme on Server 1 (primary)
        $theme_url = $SERVER_1_ADDRESS."/stalker_portal/update_account.php";
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

        error_log("edit_account.php: Server 1 theme update response: " . $theme_result);
    }

    // Send renewal SMS if account was renewed (plan_id != 0) and phone number exists
    if($plan_id != 0 && !empty($phone)) {
        try {
            // Get the account owner's ID for SMS settings
            $account_owner_id = $account['reseller'] ?: $user_info['id'];

            // Get the account ID
            $stmt = $pdo->prepare('SELECT id FROM _accounts WHERE mac = ? LIMIT 1');
            $stmt->execute([$mac]);
            $account_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $account_id = $account_row ? $account_row['id'] : null;

            // Send renewal SMS (non-blocking - won't affect account update if it fails)
            // Uses account owner's SMS settings, falls back to admin if not configured
            sendRenewalSMS($pdo, $account_owner_id, $name, $mac, $phone, $new_expiration_date, $account_id);
        } catch (Exception $e) {
            // Silently fail - don't disrupt account update
            error_log("Renewal SMS failed: " . $e->getMessage());
        }
    }

    // Send push notification to admins, reseller admins, AND the actor (v1.11.66)
    // Notify for ALL renewals regardless of who renews them
    if($plan_id != 0) {
        try {
            include_once(__DIR__ . '/push_helper.php');
            $plan_name = isset($plan) && $plan ? $plan['name'] : 'Plan #' . $plan_id;
            $account_display_name = $name ?: ($account['full_name'] ?? $original_username);
            // Use logged-in user's name as the actor (who performed the action)
            $actor_name = $user_info['name'] ?: $user_info['username'];
            $actor_id = $user_info['id']; // v1.11.66: Also notify the reseller who performed the action
            notifyAccountRenewal($pdo, $actor_name, $account_display_name, $plan_name, $new_expiration_date, $actor_id);
        } catch (Exception $e) {
            // Silently fail - don't disrupt account update
            error_log("Push notification failed: " . $e->getMessage());
        }
    }

    $response['error'] = 0;
    $response['err_msg'] = '';
    $response['message'] = 'Account updated successfully';

    if($plan_id != 0) {
        $response['message'] .= ' and renewed';
    }

    // Debug info
    $response['debug'] = [
        'received_plan' => $_POST['plan'] ?? 'not set',
        'parsed_plan_id' => $plan_id,
        'plan_found' => isset($plan) && $plan ? true : false,
        'new_expiration' => $new_expiration_date
    ];

    // Audit log: Account updated/renewed (v1.12.0)
    try {
        $old_data = [
            'name' => $account['full_name'] ?? '',
            'expiry' => $account['end_date'] ?? '',
            'status' => $account['status'] ?? 1
        ];
        $new_data = [
            'name' => $name,
            'expiry' => $new_expiration_date,
            'status' => $status,
            'plan' => isset($plan) && $plan ? $plan['name'] : null
        ];
        $audit_details = $plan_id != 0 ? 'Account renewed' : 'Account updated';
        auditAccountUpdated($pdo, $account['id'] ?? null, $account['mac'] ?? $original_username, $old_data, $new_data, $audit_details);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Update failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
