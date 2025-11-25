<?php
include(__DIR__ . '/../config.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

echo "=== CHECKING RESELLER ASSIGNMENTS ===\n\n";

// Get sample accounts with their reseller info
$stmt = $pdo->prepare('
    SELECT a.username, a.reseller, r.name as reseller_name, r.username as reseller_username
    FROM _accounts a
    LEFT JOIN _users r ON a.reseller = r.id
    LIMIT 10
');
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Sample accounts:\n";
foreach($accounts as $acc) {
    echo sprintf("  %-20s | Reseller ID: %-5s | Reseller Name: %s\n",
        $acc['username'],
        $acc['reseller'] ?? 'NULL',
        $acc['reseller_name'] ?? 'Not Assigned'
    );
}

echo "\n=== RESELLER SUMMARY ===\n";
$stmt = $pdo->query('
    SELECT
        a.reseller,
        r.name as reseller_name,
        COUNT(*) as account_count
    FROM _accounts a
    LEFT JOIN _users r ON a.reseller = r.id
    GROUP BY a.reseller
    ORDER BY account_count DESC
');
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($summary as $row) {
    echo sprintf("  Reseller ID: %-5s (%-20s) - %d accounts\n",
        $row['reseller'] ?? 'NULL',
        $row['reseller_name'] ?? 'Not Assigned',
        $row['account_count']
    );
}

?>
