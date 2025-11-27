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

    // Add MAC address and formatted description to each transaction
    foreach ($transactions as &$tx) {
        $macAddress = '';
        $fullName = '';
        $planName = '';
        $transactionType = '';
        $details = $tx['details'] ?? '';

        // Determine transaction type and extract info
        if (preg_match('/Account renewal:\s*([a-zA-Z0-9]+)\s*-\s*Plan:\s*([^-]+)/i', $details, $matches)) {
            // Renewal transaction
            $transactionType = 'Account renewal';
            $accountUsername = trim($matches[1]);
            $planName = trim($matches[2]);

            // Look up MAC and full_name from accounts table
            $acctStmt = $pdo->prepare('SELECT mac, full_name FROM _accounts WHERE username = ? LIMIT 1');
            $acctStmt->execute([$accountUsername]);
            $accountRow = $acctStmt->fetch();
            if ($accountRow) {
                $macAddress = !empty($accountRow['mac']) ? strtoupper($accountRow['mac']) : '';
                $fullName = $accountRow['full_name'] ?? '';
            }
        }
        elseif (preg_match('/Plan\s+"([^"]+)"\s+assigned\s+for\s+([a-zA-Z0-9]+)/i', $details, $matches)) {
            // New account transaction with quoted plan name: Plan "Name" assigned for username
            $transactionType = 'New account';
            $planName = trim($matches[1]);
            $accountUsername = trim($matches[2]);

            // Look up MAC and full_name from accounts table using username
            $acctStmt = $pdo->prepare('SELECT mac, full_name FROM _accounts WHERE username = ? LIMIT 1');
            $acctStmt->execute([$accountUsername]);
            $accountRow = $acctStmt->fetch();
            if ($accountRow) {
                $macAddress = !empty($accountRow['mac']) ? strtoupper($accountRow['mac']) : '';
                $fullName = $accountRow['full_name'] ?? '';
            }
        }
        elseif (preg_match('/Plan\s+([^\s]+)\s+assigned\s+to\s+([0-9A-Fa-f:]+)/i', $details, $matches)) {
            // New account transaction: Plan Name assigned to MAC
            $transactionType = 'New account';
            $planName = trim($matches[1]);
            $macAddress = strtoupper(trim($matches[2]));

            // Look up full_name from accounts table using MAC
            $acctStmt = $pdo->prepare('SELECT full_name FROM _accounts WHERE mac = ? LIMIT 1');
            $acctStmt->execute([$macAddress]);
            $accountRow = $acctStmt->fetch();
            if ($accountRow) {
                $fullName = $accountRow['full_name'] ?? '';
            }
        }
        else {
            // Try to find MAC address directly in details
            if (preg_match('/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/', $details, $matches)) {
                $macAddress = strtoupper($matches[1]);
            }
        }

        $tx['mac_address'] = $macAddress;

        // Format description: "{Type}, Plan: {Plan Name}, {Full Name}"
        if (!empty($transactionType) && !empty($planName)) {
            $formattedDesc = $transactionType . ', Plan: ' . $planName;
            if (!empty($fullName)) {
                $formattedDesc .= ', ' . $fullName;
            }
            $tx['formatted_description'] = $formattedDesc;
        } else {
            // Keep original details if can't parse
            $tx['formatted_description'] = $details;
        }
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
