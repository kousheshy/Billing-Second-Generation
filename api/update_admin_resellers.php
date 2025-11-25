<?php
include(__DIR__ . '/../config.php');

$pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8", $ub_db_username, $ub_db_password);

// Update all accounts with reseller=1 to NULL
$stmt = $pdo->prepare('UPDATE _accounts SET reseller = NULL WHERE reseller = 1');
$stmt->execute();

echo "Updated " . $stmt->rowCount() . " accounts from 'Admin User' to 'Not Assigned'\n";
?>
