<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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

$pdo = new PDO($dsn, $user, $pass, $opt);

// Get current user info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Only super users (admins) can sync accounts
if($user_info['super_user'] != 1)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied. Only admins can sync accounts.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    // Delete all existing accounts from local database
    $stmt = $pdo->prepare('DELETE FROM _accounts');
    $stmt->execute();

    // Use the 'accounts' endpoint to fetch all accounts from Stalker Portal
    $case = 'accounts';
    $op = "GET";

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);
    $decoded = json_decode($res);

    if($decoded->status != 'OK') {
        $response['error'] = 1;
        $response['err_msg'] = 'Failed to fetch accounts from Stalker Portal: ' . ($decoded->error ?? 'Unknown error');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $synced_count = 0;
    $skipped_count = 0;

    // The API returns an array of accounts in results
    $stalker_accounts = [];

    // Handle both array and object response formats
    if(isset($decoded->results)) {
        if(is_array($decoded->results)) {
            $stalker_accounts = $decoded->results;
        } elseif(is_object($decoded->results)) {
            $stalker_accounts = [$decoded->results];
        }
    }

    // Process each account
    foreach($stalker_accounts as $stalker_user) {
        // Extract login and MAC from the user object
        $login = $stalker_user->login ?? null;
        $mac = $stalker_user->stb_mac ?? null;

        // Skip if no valid login or MAC
        if(empty($login) || empty($mac)) {
            $skipped_count++;
            continue;
        }

        // Extract additional data from Stalker Portal
        $full_name = $stalker_user->full_name ?? $stalker_user->name ?? '';
        $tariff_plan = $stalker_user->tariff_plan ?? $stalker_user->tariff ?? '';
        $email = $stalker_user->email ?? '';
        $status = $stalker_user->status ?? 1; // Default to 1 (ON) if not set

        // Handle end_date - convert invalid dates to NULL
        $end_date = $stalker_user->end_date ?? null;
        if($end_date && (strpos($end_date, '0000-00-00') === 0 || empty($end_date))) {
            $end_date = null;
        }

        // Use current timestamp for creation date
        $created_timestamp = time();

        // Insert account into local database (all accounts are fresh after DELETE)
        // Default to admin user (ID 1) as reseller for synced accounts
        $stmt = $pdo->prepare('INSERT INTO _accounts (username, email, mac, full_name, tariff_plan, end_date, status, reseller, timestamp) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $login,
            $email,
            $mac,
            $full_name,
            $tariff_plan,
            $end_date,
            $status,
            1, // Assign to admin user by default
            $created_timestamp
        ]);

        $synced_count++;
    }

    // Get total accounts count
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM _accounts');
    $stmt->execute();
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['error'] = 0;
    $response['err_msg'] = '';
    $response['synced'] = $synced_count;
    $response['skipped'] = $skipped_count;
    $response['total_accounts'] = $total_result['total'];

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Sync failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
