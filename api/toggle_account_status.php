<?php
session_start();

// Set JSON header first
header('Content-Type: application/json');

// Disable error display, log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

try {
    include(__DIR__ . '/../config.php');
    include('api.php');

    // Check if user is logged in
    if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
        exit;
    }

    $session_username = $_SESSION['username'];

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

    // Get user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$session_username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found.']);
        exit;
    }

    // Get and validate input parameters
    if(!isset($_GET['username']) || !isset($_GET['status'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required parameters']);
        exit;
    }

    $target_username = trim($_GET['username']);
    $new_status = intval($_GET['status']); // 1 = active, 0 = disabled

    // Validate status value
    if($new_status !== 0 && $new_status !== 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid status value']);
        exit;
    }

    // Check if target account exists and get its MAC address, reseller, and full name
    $stmt = $pdo->prepare('SELECT mac, reseller, full_name FROM _accounts WHERE username = ?');
    $stmt->execute([$target_username]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$account) {
        echo json_encode(['error' => 1, 'err_msg' => 'Account not found']);
        exit;
    }

    $mac = $account['mac'];
    $full_name = $account['full_name'] ?? $target_username; // Fallback to username if no full name

    // Parse permissions: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $can_toggle_status = isset($permissions[5]) && $permissions[5] === '1';

    // Permission check: Super admin OR reseller admin can toggle any account
    // Regular resellers need explicit can_toggle_status permission AND can only toggle their own accounts
    if($user_info['super_user'] != 1) {
        // Reseller admins have full access to toggle any account
        if($is_reseller_admin) {
            // Reseller admin can toggle any account - no further checks needed
        } else {
            // Regular resellers need explicit permission
            if(!$can_toggle_status) {
                echo json_encode(['error' => 1, 'err_msg' => 'Permission denied. You do not have permission to toggle account status.']);
                exit;
            }

            // Regular resellers can only toggle their own accounts
            if($account['reseller'] != $user_info['id']) {
                echo json_encode(['error' => 1, 'err_msg' => 'Permission denied. You can only toggle status of your own accounts.']);
                exit;
            }
        }
    }

    // Update status on Stalker Portal server using API
    $data = 'status=' . $new_status;
    $case = 'accounts';
    $op = "PUT";

    // Check if both servers are the same (avoid duplicate operations)
    $dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

    // Update Server 2 first (only if dual server mode)
    if($dual_server_mode) {
        $res2 = api_send_request($WEBSERVICE_2_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
        $decoded2 = json_decode($res2);

        if(!$decoded2 || $decoded2->status != 'OK') {
            error_log("Warning: Server 2 status update failed for $target_username: " . ($decoded2->error ?? 'Unknown error'));
            // Continue to update Server 1
        }
    }

    // Update Server 1 (primary)
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);
    $decoded = json_decode($res);

    if(!$decoded || $decoded->status != 'OK') {
        echo json_encode([
            'error' => 1,
            'err_msg' => $decoded->error ?? 'Failed to update status'
        ]);
        exit();
    }

    // Update status in local database
    $stmt = $pdo->prepare('UPDATE _accounts SET status = ? WHERE username = ?');
    $stmt->execute([$new_status, $target_username]);

    error_log("Status toggle for {$target_username} (MAC: {$mac}): Updated to {$new_status}");

    $status_text = $new_status == 1 ? 'active' : 'disabled';
    echo json_encode([
        'error' => 0,
        'err_msg' => '',
        'message' => "{$full_name} is now {$status_text}"
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'error' => 1,
        'err_msg' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'error' => 1,
        'err_msg' => 'Error: ' . $e->getMessage()
    ]);
}
?>
