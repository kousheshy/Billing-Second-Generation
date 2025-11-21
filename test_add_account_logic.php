<?php
session_start();

// Simulate kamiksh session
$_SESSION['login'] = 1;
$_SESSION['username'] = 'kamiksh';

// Simulate POST data for adding an account
$_POST['username'] = 'testaccount123';
$_POST['plan'] = '0';
$_POST['status'] = '1';

include('config.php');

$session_username = $_SESSION['username'];
echo "Step 1: Session username = " . $session_username . "\n";

$pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8", $ub_db_username, $ub_db_password);

$stmt = $pdo->prepare('SELECT us.id,us.name,us.email,us.balance,us.permissions,us.max_users,us.super_user,us.currency_id,us.theme,cr.name as currency_name FROM '.$ub_main_db.'._users AS us LEFT OUTER JOIN '.$ub_main_db.'._currencies AS cr ON us.currency_id=cr.id WHERE us.username = ?');
$stmt->execute([$session_username]);

$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Step 2: User info fetched\n";
echo "  - User ID: " . $user_info['id'] . "\n";
echo "  - User Name: " . $user_info['name'] . "\n";
echo "  - Super User: " . $user_info['super_user'] . "\n";

if($user_info['super_user']==1)
{
    echo "Step 3: User is super admin\n";
    if(!empty($_POST['reseller']))
    {
        echo "  - POST reseller is set\n";
        $reseller_info = ['id' => $_POST['reseller']];
    }else
    {
        echo "  - POST reseller is empty, using admin's info\n";
        $reseller_info=$user_info;
    }
}else{
    echo "Step 3: User is NOT super admin (regular reseller)\n";
    $reseller_info=$user_info;
}

echo "Step 4: Reseller info\n";
echo "  - Reseller ID: " . $reseller_info['id'] . "\n";
echo "  - Reseller Name: " . $reseller_info['name'] . "\n";

$account_username = trim($_POST['username']);
echo "Step 5: Account username from POST = " . $account_username . "\n";

echo "\n✅ RESULT: Account would be saved with reseller_id = " . $reseller_info['id'] . "\n";
?>
