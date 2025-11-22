<?php
/**
 * Send Event to STB Device
 *
 * Sends various control events to Set-Top Box devices via Stalker Portal API
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

    // Super admin can always send STB events
    // Reseller admin can only send if they have can_control_stb permission
    // Regular resellers can only send if they have can_control_stb permission
    if($user_info['super_user'] != 1) {
        if(!$can_control_stb) {
            $response['error'] = 1;
            $response['err_msg'] = 'Permission denied. You do not have permission to send STB events.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }

    // Get parameters
    $mac = trim($_POST['mac'] ?? '');
    $event = trim($_POST['event'] ?? '');
    $channel_id = trim($_POST['channel_id'] ?? '');

    if(empty($mac)) {
        $response['error'] = 1;
        $response['err_msg'] = 'MAC address is required';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if(empty($event)) {
        $response['error'] = 1;
        $response['err_msg'] = 'Event type is required';
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

    // Build event data based on event type
    $data = '';

    switch($event) {
        case 'reboot':
            $data = 'event=reboot';
            break;
        case 'reload_portal':
            $data = 'event=reload_portal';
            break;
        case 'update_channels':
            $data = 'event=update_channels';
            break;
        case 'play_channel':
            if(empty($channel_id)) {
                $response['error'] = 1;
                $response['err_msg'] = 'Channel ID is required for Play Channel event';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            // Note: Stalker Portal uses $ as separator for channel events, not &
            $data = 'event=play_channel$channel_number=' . $channel_id;
            break;
        case 'play_radio_channel':
            if(empty($channel_id)) {
                $response['error'] = 1;
                $response['err_msg'] = 'Channel ID is required for Play Radio Channel event';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
            // Note: Stalker Portal uses $ as separator for channel events, not &
            $data = 'event=play_radio_channel$channel_number=' . $channel_id;
            break;
        case 'update_image':
            $data = 'event=update_image';
            break;
        case 'show_menu':
            $data = 'event=show_menu';
            break;
        case 'cut_off':
            $data = 'event=cut_off';
            break;
        default:
            $response['error'] = 1;
            $response['err_msg'] = 'Invalid event type';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
    }

    // Send event to Stalker Portal
    $case = 'send_event';
    $op = "POST";

    error_log("=== SENDING STB EVENT ===");
    error_log("MAC: " . $mac);
    error_log("Event: " . $event);
    error_log("Data: " . $data);

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $mac, $data);

    error_log("Stalker API Response: " . $res);

    $decoded = json_decode($res);

    if(isset($decoded->status) && $decoded->status == 'OK') {
        $response['error'] = 0;
        $response['message'] = 'Event sent successfully to device ' . $mac;

        // Log the action
        error_log("User {$username} sent event '{$event}' to device {$mac}");
    } else {
        $response['error'] = 1;
        $response['err_msg'] = 'Failed to send event: ' . ($decoded->error ?? 'Unknown error');
    }

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
