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

    $is_observer = $user_info['is_observer'] == 1;

    // Parse permissions to check if user is reseller admin
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Get view mode preference from request (only for reseller admins)
    $viewAllAccounts = isset($_GET['viewAllAccounts']) ? $_GET['viewAllAccounts'] === 'true' : false;

    // Determine what transactions to show
    if($user_info['super_user'] == 1 || $is_observer)
    {
        // Super admins and observers always see all transactions with reseller names
        $stmt = $pdo->prepare('SELECT t.*, u.name as reseller_name, u.username as reseller_username FROM _transactions t LEFT JOIN _users u ON t.for_user = u.id ORDER BY t.id DESC LIMIT 100');
        $stmt->execute([]);
    }
    else if($is_reseller_admin && $viewAllAccounts)
    {
        // Reseller admin in "All Accounts" mode sees all transactions
        $stmt = $pdo->prepare('SELECT t.*, u.name as reseller_name, u.username as reseller_username FROM _transactions t LEFT JOIN _users u ON t.for_user = u.id ORDER BY t.id DESC LIMIT 100');
        $stmt->execute([]);
    }
    else
    {
        // Reseller admin in "My Accounts" mode or regular resellers see only their own transactions
        $stmt = $pdo->prepare('SELECT * FROM _transactions WHERE for_user = ? ORDER BY id DESC LIMIT 100');
        $stmt->execute([$user_info['id']]);
    }

    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add MAC address to each transaction
    foreach ($transactions as &$tx) {
        $macAddress = '';
        $details = $tx['details'] ?? '';

        // First try to find MAC address directly in details (format: XX:XX:XX:XX:XX:XX)
        if (preg_match('/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/', $details, $matches)) {
            $macAddress = strtoupper($matches[1]);
        }
        // For renewals, extract username and look up MAC from accounts table
        elseif (preg_match('/Account renewal:\s*([a-zA-Z0-9]+)\s*-/', $details, $matches)) {
            $accountUsername = $matches[1];
            $macStmt = $pdo->prepare('SELECT mac FROM _accounts WHERE username = ? LIMIT 1');
            $macStmt->execute([$accountUsername]);
            $accountRow = $macStmt->fetch();
            if ($accountRow && !empty($accountRow['mac'])) {
                $macAddress = strtoupper($accountRow['mac']);
            }
        }

        $tx['mac_address'] = $macAddress;
    }
    unset($tx); // Break the reference

    $response['error'] = 0;
    $response['transactions'] = $transactions;

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
