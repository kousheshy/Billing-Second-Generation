<?php

session_start();

include('config.php');

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

    // If super_user (admin), show all plans
    if($user_info['super_user'] == 1) {
        $stmt = $pdo->prepare('SELECT * FROM _plans ORDER BY id DESC');
        $stmt->execute([]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // For resellers, only show assigned plans
        $assigned_plans = $user_info['plans'];

        if(empty($assigned_plans)) {
            // If no plans assigned, show empty array
            $plans = [];
        } else {
            // Parse plan assignments (format: "planID-currency,planID-currency")
            $plan_combinations = explode(',', $assigned_plans);
            $plans = [];

            foreach($plan_combinations as $combination) {
                $parts = explode('-', $combination);
                if(count($parts) == 2) {
                    $plan_id = $parts[0];
                    $currency = $parts[1];

                    $stmt = $pdo->prepare("SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?");
                    $stmt->execute([$plan_id, $currency]);
                    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

                    if($plan) {
                        $plans[] = $plan;
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
