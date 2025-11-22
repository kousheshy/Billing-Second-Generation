<?php
/**
 * Send Message to STB Device
 *
 * Sends text messages to Set-Top Box devices via Stalker Portal API
 */

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include('config.php');
include('api.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
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

    // Check permissions
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $can_control_stb = isset($permissions[4]) && $permissions[4] === '1';

    // Super admin can always send STB messages
    // Reseller admin can only send if they have can_control_stb permission
    // Regular resellers can only send if they have can_control_stb permission
    if($user_info['super_user'] != 1) {
        if(!$can_control_stb) {
            $response['error'] = 1;
            $response['err_msg'] = 'Permission denied. You do not have permission to send STB messages.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }

    // Get parameters
    $mac = trim($_POST['mac'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if(empty($mac)) {
        $response['error'] = 1;
        $response['err_msg'] = 'MAC address is required';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if(empty($message)) {
        $response['error'] = 1;
        $response['err_msg'] = 'Message is required';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Verify account belongs to this reseller (if not super admin)
    if($user_info['super_user'] != 1) {
        $stmt = $pdo->prepare('SELECT reseller FROM _accounts WHERE mac = ?');
        $stmt->execute([$mac]);
        $account = $stmt->fetch();

        if(!$account) {
            $response['error'] = 1;
            $response['err_msg'] = 'Device not found';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        if($account['reseller'] != $user_info['id']) {
            $response['error'] = 1;
            $response['err_msg'] = 'Permission denied. This device does not belong to you.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }

    // Build message data
    $data = 'msg=' . urlencode($message);

    // Send message to Stalker Portal
    $case = 'stb_msg';
    $op = "POST";

    error_log("=== SENDING STB MESSAGE ===");
    error_log("MAC: " . $mac);
    error_log("Message: " . $message);

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

    error_log("Stalker API Response: " . $res);

    $decoded = json_decode($res);

    if(isset($decoded->status) && $decoded->status == 'OK') {
        $response['error'] = 0;
        $response['message'] = 'Message sent successfully to device ' . $mac;

        // Log the action
        error_log("User {$username} sent message to device {$mac}: {$message}");
    } else {
        $response['error'] = 1;
        $response['err_msg'] = 'Failed to send message: ' . ($decoded->error ?? 'Unknown error');
    }

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
