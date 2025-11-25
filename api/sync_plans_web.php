<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit;
}

include(__DIR__ . '/../config.php');
include('api.php');

header('Content-Type: application/json');

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

    // Check if user is admin
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare('SELECT super_user FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data || $user_data['super_user'] != 1) {
        echo json_encode(['error' => 1, 'message' => 'Unauthorized access. Admin only.']);
        exit;
    }

    // Get tariffs from Stalker Portal Server 1
    $case = 'tariffs';
    $op = "GET";

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

    $decoded = json_decode($res);

    if (!$decoded || $decoded->status != 'OK') {
        echo json_encode([
            'error' => 1,
            'message' => 'Failed to fetch tariff plans from Stalker Portal'
        ]);
        exit;
    }

    $synced_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $synced_plans = [];

    foreach ($decoded->results as $plan) {

        // Get plan details
        $plan_id = $plan->id ?? '';
        $plan_name = $plan->name ?? "Plan $plan_id";
        $plan_days = $plan->days_to_expires ?? 30;

        if (empty($plan_id)) {
            continue;
        }

        // Sync plan for each currency
        $currencies = ['GBP', 'USD', 'EUR', 'IRR'];

        // Default prices per currency - you can adjust these
        $default_prices = [
            'GBP' => 10.00,
            'USD' => 12.00,
            'EUR' => 11.00,
            'IRR' => 500000
        ];

        foreach ($currencies as $currency) {

            // Check if plan already exists for this currency
            $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?');
            $stmt->execute([$plan_id, $currency]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update plan name and days if changed
                $stmt = $pdo->prepare('UPDATE _plans SET name = ?, days = ? WHERE external_id = ? AND currency_id = ?');
                $stmt->execute([$plan_name, $plan_days, $plan_id, $currency]);

                $updated_count++;

            } else {
                // Insert new plan with default price for this currency
                $stmt = $pdo->prepare('INSERT INTO _plans (external_id, name, currency_id, price, days) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $plan_id,
                    $plan_name,
                    $currency,
                    $default_prices[$currency],
                    $plan_days
                ]);

                $synced_count++;
            }
        }

        $synced_plans[] = [
            'id' => $plan_id,
            'name' => $plan_name,
            'days' => $plan_days
        ];
    }

    echo json_encode([
        'error' => 0,
        'message' => "Successfully synced plans from Stalker Portal",
        'synced_count' => $synced_count,
        'updated_count' => $updated_count,
        'total_plans' => count($synced_plans),
        'plans' => $synced_plans
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 1,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

?>
