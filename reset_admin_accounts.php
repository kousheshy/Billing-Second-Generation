<?php
/**
 * Reset Admin-Assigned Accounts Script
 *
 * This script sets all accounts currently assigned to the admin (ID=1)
 * to "Not Assigned" (NULL), except for accounts that were explicitly
 * created by resellers.
 */

include('config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

echo "=== RESET ADMIN-ASSIGNED ACCOUNTS ===\n\n";

// Count accounts currently assigned to admin
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts WHERE reseller = 1');
$stmt->execute();
$result = $stmt->fetch();
$admin_count = $result['count'];

echo "Found $admin_count accounts assigned to Admin User (ID=1)\n";
echo "These will be set to 'Not Assigned' (NULL)\n\n";

$confirm = readline("Do you want to proceed? (yes/no): ");

if(strtolower(trim($confirm)) !== 'yes') {
    echo "Operation cancelled.\n";
    exit(0);
}

// Update all admin-assigned accounts to NULL
$stmt = $pdo->prepare('UPDATE _accounts SET reseller = NULL WHERE reseller = 1');
$stmt->execute();

$updated = $stmt->rowCount();

echo "\n✓ Successfully updated $updated accounts\n";
echo "All accounts previously assigned to 'Admin User' are now 'Not Assigned'\n";
echo "\nYou can now manually assign them to specific resellers using the 'Assign Reseller' button in the dashboard.\n";

?>
