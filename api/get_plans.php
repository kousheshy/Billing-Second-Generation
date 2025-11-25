<?php

session_start();

include(__DIR__ . '/../config.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1)
{
    $response['error'] = 1;
    $response['message'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

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

    $username = $_SESSION['username'];

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Parse permissions: can_edit|can_add|is_reseller_admin|reserved|reserved
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $is_observer = $user_info['is_observer'] == 1;

    // Get view mode preference from request (only for reseller admins)
    // Default to false (My Accounts) for reseller admins
    $viewAllAccounts = isset($_GET['viewAllAccounts']) ? $_GET['viewAllAccounts'] === 'true' : false;

    // Super admins and observers always see all plans
    // Reseller admins see all plans OR assigned plans based on toggle
    // Regular resellers only see their assigned plans
    if($user_info['super_user'] == 1 || $is_observer) {
        $stmt = $pdo->prepare('SELECT * FROM _plans ORDER BY id DESC');
        $stmt->execute([]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if($is_reseller_admin && $viewAllAccounts) {
        // Reseller admin in "All Accounts" mode sees all system plans
        $stmt = $pdo->prepare('SELECT * FROM _plans ORDER BY id DESC');
        $stmt->execute([]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // For resellers or reseller admins viewing only their own, show assigned plans
        $assigned_plans = $user_info['plans'];

        // Debug: Log assigned plans
        error_log('[get_plans.php] User: ' . $user_info['username'] . ' | Assigned plans: ' . ($assigned_plans ?: 'NONE'));

        if(empty($assigned_plans)) {
            // If no plans assigned, show empty array
            $plans = [];
        } else {
            // Parse plan assignments (format: "planID-currency,planID-currency")
            $plan_combinations = explode(',', $assigned_plans);
            $plans = [];

            error_log('[get_plans.php] Plan combinations: ' . print_r($plan_combinations, true));

            foreach($plan_combinations as $combination) {
                $parts = explode('-', $combination);
                if(count($parts) == 2) {
                    $plan_id = $parts[0];
                    $currency = $parts[1];

                    error_log('[get_plans.php] Looking for plan: external_id=' . $plan_id . ', currency_id=' . $currency);

                    $stmt = $pdo->prepare("SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?");
                    $stmt->execute([$plan_id, $currency]);
                    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

                    if($plan) {
                        error_log('[get_plans.php] Found plan: ' . $plan['name']);
                        // Regular resellers see all their assigned plans
                        $plans[] = $plan;
                    } else {
                        error_log('[get_plans.php] Plan NOT FOUND for external_id=' . $plan_id . ', currency_id=' . $currency);
                    }
                }
            }
        }
    }

    $response['error'] = 0;
    $response['plans'] = $plans;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
