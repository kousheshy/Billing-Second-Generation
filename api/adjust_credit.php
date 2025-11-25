<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');

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

// Only super users or reseller admins can adjust credit
if($user_info['super_user'] != 1 && !$is_reseller_admin)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied. Admin or Reseller Admin only.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$reseller_id = $_POST['reseller_id'];
$action = $_POST['action'];
$amount = floatval($_POST['amount']);

// Get reseller info
$stmt = $pdo->prepare('SELECT us.*, cr.name as currency_name FROM _users AS us LEFT OUTER JOIN _currencies AS cr ON us.currency_id=cr.id WHERE us.id = ?');
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

$current_balance = floatval($reseller['balance']);
$new_balance = $current_balance;

// Calculate new balance based on action
if($action === 'add')
{
    $new_balance = $current_balance + $amount;
}
elseif($action === 'deduct')
{
    $new_balance = $current_balance - $amount;
    if($new_balance < 0) $new_balance = 0;
}
elseif($action === 'set')
{
    $new_balance = $amount;
}

// Update balance
$stmt = $pdo->prepare('UPDATE _users SET balance = ? WHERE id = ?');
$stmt->execute([$new_balance, $reseller_id]);

// Log transaction
$details = 'Credit adjustment by admin: ' . $action . ' ' . $amount . ' (Previous: ' . $current_balance . ', New: ' . $new_balance . ')';
$stmt = $pdo->prepare('INSERT INTO _transactions (creator, for_user, amount, currency, type, details, timestamp) VALUES (?,?,?,?,?,?,?)');

$tx_type = ($new_balance > $current_balance) ? 1 : 0; // 1 = credit, 0 = debit
$tx_amount = abs($new_balance - $current_balance);

$stmt->execute(['admin', $reseller_id, $tx_amount, $reseller['currency_name'], $tx_type, $details, time()]);

$response['error'] = 0;
$response['err_msg'] = '';
$response['new_balance'] = $new_balance;

header('Content-Type: application/json');
echo json_encode($response);

?>
