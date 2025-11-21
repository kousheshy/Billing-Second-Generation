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

    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user_info['super_user'] == 1)
    {
        $stmt = $pdo->prepare('SELECT a.*, p.name as plan_name FROM _accounts a LEFT JOIN _plans p ON a.plan = p.id ORDER BY a.id DESC');
        $stmt->execute([]);
    }
    else
    {
        $stmt = $pdo->prepare('SELECT a.*, p.name as plan_name FROM _accounts a LEFT JOIN _plans p ON a.plan = p.id WHERE a.reseller = ? ORDER BY a.id DESC');
        $stmt->execute([$user_info['id']]);
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Data is now stored locally - no need to fetch from Stalker Portal every time
    // All account data (full_name, tariff_plan, end_date) is already in the local database
    // It gets synced when the admin clicks "Sync Accounts from Server"

    $response['error'] = 0;
    $response['accounts'] = $accounts;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
