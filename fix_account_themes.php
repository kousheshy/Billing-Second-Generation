<?php

/**
 * Fix Account Themes - One-time Script
 *
 * This script updates the theme for all accounts belonging to resellers
 * to match their reseller's current theme setting.
 *
 * Run this script once to fix any accounts created before the theme feature was fully implemented.
 */

include('config.php');

echo "=== Fix Account Themes Script ===\n\n";

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

    // Get all accounts with their reseller's theme
    $stmt = $pdo->prepare('
        SELECT
            a.id,
            a.username,
            a.mac,
            a.reseller,
            u.name as reseller_name,
            u.theme as reseller_theme
        FROM _accounts a
        LEFT JOIN _users u ON a.reseller = u.id
        WHERE a.reseller IS NOT NULL
        ORDER BY a.reseller, a.username
    ');
    $stmt->execute();
    $accounts = $stmt->fetchAll();

    echo "Found " . count($accounts) . " accounts to process\n\n";

    $updated = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($accounts as $account) {
        $theme = $account['reseller_theme'] ?? 'HenSoft-TV Realistic-Centered SHOWBOX';

        if (empty($theme)) {
            $theme = 'HenSoft-TV Realistic-Centered SHOWBOX';
        }

        echo "Processing: {$account['username']} (Reseller: {$account['reseller_name']})\n";
        echo "  Theme to apply: {$theme}\n";

        // Update theme on Stalker Portal server
        $data = "key=f4H75Sgf53GH4dd&login={$account['username']}&theme={$theme}";
        $url = $SERVER_1_ADDRESS . "/stalker_portal/update_account.php";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTREDIR, 3);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($result === 'OK') {
            echo "  ✓ Theme updated successfully\n\n";
            $updated++;
        } elseif ($curl_error) {
            echo "  ✗ Error: {$curl_error}\n\n";
            $errors++;
        } else {
            echo "  ! Response: {$result}\n\n";
            $skipped++;
        }

        // Small delay to avoid overwhelming the server
        usleep(100000); // 0.1 second delay
    }

    echo "\n=== Summary ===\n";
    echo "Total accounts: " . count($accounts) . "\n";
    echo "Successfully updated: {$updated}\n";
    echo "Skipped/Failed: {$skipped}\n";
    echo "Errors: {$errors}\n";

} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>
