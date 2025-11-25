<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include('config.php');

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

$pdo = new PDO($dsn, $user, $pass, $opt);

// Get current user info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is admin or reseller admin
// Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

// Only super users or reseller admins can assign plans
if($user_info['super_user'] != 1 && !$is_reseller_admin)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied. Admin or Reseller Admin only.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$reseller_id = $_POST['reseller_id'];
$plans = isset($_POST['plans']) ? $_POST['plans'] : '';

// Get reseller info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE id = ?');
$stmt->execute([$reseller_id]);
$reseller = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$reseller)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Reseller not found';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Validate that all assigned plans match the reseller's currency
if(!empty($plans)) {
    $reseller_currency = $reseller['currency_id'];
    $plan_combinations = explode(',', $plans);
    $invalid_plans = [];

    foreach($plan_combinations as $combination) {
        $parts = explode('-', $combination);
        if(count($parts) == 2) {
            $plan_id = $parts[0];
            $plan_currency = $parts[1];

            // Check if plan currency matches reseller currency
            if($plan_currency !== $reseller_currency) {
                // Get plan name for better error message
                $stmt = $pdo->prepare('SELECT name, external_id FROM _plans WHERE external_id = ? AND currency_id = ?');
                $stmt->execute([$plan_id, $plan_currency]);
                $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);

                $plan_name = $plan_info ? $plan_info['name'] : "Plan $plan_id";
                $invalid_plans[] = "$plan_name ($plan_currency)";
            }
        }
    }

    // If there are invalid plans, return error
    if(!empty($invalid_plans)) {
        $response['error'] = 1;
        $response['err_msg'] = 'Currency mismatch: Reseller currency is ' . $reseller_currency . ', but the following plans have different currencies: ' . implode(', ', $invalid_plans) . '. Please only assign plans that match the reseller\'s currency.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Update plans
$stmt = $pdo->prepare('UPDATE _users SET plans = ? WHERE id = ?');
$stmt->execute([$plans, $reseller_id]);

// Debug: Log plan assignment
error_log('[assign_plans.php] Assigned plans to reseller ID ' . $reseller_id . ': ' . ($plans ?: 'EMPTY'));

$response['error'] = 0;
$response['err_msg'] = '';
$response['plans'] = $plans;

header('Content-Type: application/json');
echo json_encode($response);

?>
