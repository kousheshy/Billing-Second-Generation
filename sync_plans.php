<?php

include('config.php');
include('api.php');

echo "Syncing plans from Stalker Portal...\n\n";

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

// Get plans from Stalker Portal
$case = 'tariffs';
$op = "GET";

$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

$decoded = json_decode($res);

if($decoded->status == 'OK') {

    $count = 0;

    foreach ($decoded->results as $plan) {

        // Insert/Update plan for each currency
        $currencies = ['GBP', 'USD', 'EUR', 'IRT'];
        $prices = ['GBP' => 10, 'USD' => 12, 'EUR' => 11, 'IRT' => 500000]; // Default prices - you can adjust

        foreach($currencies as $currency) {

            $stmt = $pdo->prepare('SELECT * FROM _plans WHERE external_id = ? AND currency_id = ?');
            $stmt->execute([$plan->id, $currency]);

            if($stmt->rowCount() > 0) {
                echo "Plan already exists: {$plan->name} ({$currency})\n";
            } else {
                $stmt = $pdo->prepare('INSERT INTO _plans (external_id, currency_id, price, days) VALUES (?,?,?,?)');
                $stmt->execute([$plan->id, $currency, $prices[$currency], $plan->days_to_expires]);

                echo "✓ Imported: {$plan->name} ({$currency}) - {$plan->days_to_expires} days\n";
                $count++;
            }
        }
    }

    echo "\n✅ Successfully synced $count plans!\n";

} else {
    echo "❌ Error fetching plans from Stalker Portal\n";
}

?>
