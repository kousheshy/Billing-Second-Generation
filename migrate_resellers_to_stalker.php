<?php
/**
 * Reseller Migration Script
 *
 * This script migrates existing reseller assignments from the billing database
 * to Stalker Portal. Run this AFTER you've added the 'reseller' column to
 * Stalker's database and updated the API to support it.
 *
 * Usage: php migrate_resellers_to_stalker.php
 */

include('config.php');
include('api.php');

echo "=== RESELLER MIGRATION TO STALKER ===\n";
echo "This will update all accounts in Stalker with their reseller assignments.\n";
echo "Make sure you've added the 'reseller' column to Stalker's database first!\n\n";

// Connect to billing database
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

    // Get all accounts with reseller assignments
    $stmt = $pdo->prepare('SELECT username, mac, reseller, full_name FROM _accounts WHERE reseller IS NOT NULL ORDER BY reseller, username');
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($accounts);
    echo "Found $total accounts with reseller assignments\n\n";

    if($total == 0) {
        echo "Nothing to migrate.\n";
        exit(0);
    }

    // Group by reseller for reporting
    $by_reseller = [];
    foreach($accounts as $account) {
        $reseller_id = $account['reseller'];
        if(!isset($by_reseller[$reseller_id])) {
            $by_reseller[$reseller_id] = [];
        }
        $by_reseller[$reseller_id][] = $account;
    }

    echo "Accounts grouped by reseller:\n";
    foreach($by_reseller as $reseller_id => $reseller_accounts) {
        // Get reseller name
        $stmt = $pdo->prepare('SELECT name FROM _users WHERE id = ?');
        $stmt->execute([$reseller_id]);
        $reseller = $stmt->fetch();
        $reseller_name = $reseller ? $reseller['name'] : "Unknown";

        echo "  Reseller #$reseller_id ($reseller_name): " . count($reseller_accounts) . " accounts\n";
    }

    echo "\nStarting migration...\n";
    echo str_repeat("-", 70) . "\n";

    $success = 0;
    $failed = 0;
    $errors = [];

    foreach($accounts as $index => $account) {
        $progress = $index + 1;
        echo sprintf("[%d/%d] %-20s (MAC: %s) -> Reseller #%d ... ",
                    $progress, $total, $account['username'],
                    substr($account['mac'], -8), $account['reseller']);

        // Update Stalker with reseller info
        $data = 'reseller=' . $account['reseller'];
        $case = 'accounts';
        $op = "PUT";

        $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME,
                               $WEBSERVICE_PASSWORD, $case, $op, $account['mac'], $data);
        $decoded = json_decode($res);

        if($decoded && $decoded->status == 'OK') {
            $success++;
            echo "✓\n";
        } else {
            $failed++;
            $error_msg = $decoded ? ($decoded->error ?? 'Unknown error') : 'Invalid response';
            echo "✗ ($error_msg)\n";
            $errors[] = [
                'username' => $account['username'],
                'mac' => $account['mac'],
                'reseller' => $account['reseller'],
                'error' => $error_msg
            ];
        }

        // Small delay to avoid overwhelming the API
        usleep(100000); // 100ms delay
    }

    echo str_repeat("-", 70) . "\n";
    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "Total accounts: $total\n";
    echo "Successful: $success\n";
    echo "Failed: $failed\n";

    if($failed > 0) {
        echo "\n=== FAILED ACCOUNTS ===\n";
        foreach($errors as $error) {
            echo "  {$error['username']} (MAC: {$error['mac']}) -> Reseller #{$error['reseller']}\n";
            echo "    Error: {$error['error']}\n";
        }
        echo "\nYou may need to manually update these accounts or investigate the errors.\n";
    } else {
        echo "\n✓ All accounts migrated successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Verify the migration by running: php test_stalker_reseller.php\n";
        echo "2. Test creating a new account as a reseller\n";
        echo "3. Test syncing accounts from Stalker Portal\n";
    }

} catch(PDOException $e) {
    echo "\n✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
