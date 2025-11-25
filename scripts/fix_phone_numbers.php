<?php
/**
 * Fix Phone Numbers Migration Script
 *
 * This script updates all phone numbers in the _accounts table to ensure they
 * follow the E.164 international format (starting with +).
 *
 * Usage: Run this script once via browser or command line
 * Example: php fix_phone_numbers.php
 */

session_start();
include(__DIR__ . '/../config.php');

// For security, only allow admin to run this script
// Comment out these lines if running from command line
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    die('Access denied. Please login as admin first.');
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

    // Check if user is admin
    $stmt = $pdo->prepare('SELECT super_user FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user_info['super_user'] != 1) {
        die('Access denied. Only admin can run this migration script.');
    }

    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Fix Phone Numbers</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}.stats{background:#f0f0f0;padding:15px;border-radius:5px;margin:20px 0;}";
    echo "table{width:100%;border-collapse:collapse;margin:20px 0;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#667eea;color:white;}</style>";
    echo "</head><body>";
    echo "<h1>🔧 Fix Phone Numbers Migration</h1>";
    echo "<p class='info'>This script will update all phone numbers to ensure they start with '+'</p>";

    // Get all accounts with phone numbers
    $stmt = $pdo->prepare('SELECT id, username, phone_number FROM _accounts WHERE phone_number IS NOT NULL AND phone_number != ""');
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($accounts);
    $fixed = 0;
    $skipped = 0;
    $errors = 0;
    $updates = [];

    echo "<div class='stats'>";
    echo "<strong>Total accounts with phone numbers:</strong> {$total}<br>";
    echo "</div>";

    echo "<h2>Processing...</h2>";
    echo "<table>";
    echo "<tr><th>Username</th><th>Old Phone</th><th>New Phone</th><th>Status</th></tr>";

    foreach ($accounts as $account) {
        $id = $account['id'];
        $username = $account['username'];
        $oldPhone = $account['phone_number'];

        // Skip if already starts with +
        if (strpos($oldPhone, '+') === 0) {
            $skipped++;
            echo "<tr><td>{$username}</td><td>{$oldPhone}</td><td>-</td><td style='color:gray;'>✓ Already correct</td></tr>";
            continue;
        }

        // Remove any non-digit characters except + from the beginning
        $cleaned = preg_replace('/[^\d+]/', '', $oldPhone);

        // If it's just digits (no + sign), add + at the beginning
        if (ctype_digit($cleaned)) {
            $newPhone = '+' . $cleaned;
        } else {
            // If it already has + somewhere in the middle, move it to the beginning
            $newPhone = '+' . str_replace('+', '', $cleaned);
        }

        try {
            // Update the phone number
            $updateStmt = $pdo->prepare('UPDATE _accounts SET phone_number = ? WHERE id = ?');
            $updateStmt->execute([$newPhone, $id]);

            $fixed++;
            $updates[] = [
                'username' => $username,
                'old' => $oldPhone,
                'new' => $newPhone
            ];

            echo "<tr><td>{$username}</td><td>{$oldPhone}</td><td>{$newPhone}</td><td style='color:green;'>✓ Fixed</td></tr>";
        } catch (Exception $e) {
            $errors++;
            echo "<tr><td>{$username}</td><td>{$oldPhone}</td><td>-</td><td style='color:red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
        }
    }

    echo "</table>";

    // Summary
    echo "<div class='stats'>";
    echo "<h2>Summary</h2>";
    echo "<strong>Total processed:</strong> {$total}<br>";
    echo "<strong class='success'>Fixed:</strong> {$fixed}<br>";
    echo "<strong style='color:gray;'>Already correct (skipped):</strong> {$skipped}<br>";
    if ($errors > 0) {
        echo "<strong class='error'>Errors:</strong> {$errors}<br>";
    }
    echo "</div>";

    if ($fixed > 0) {
        echo "<div class='success'>";
        echo "<h3>✓ Migration completed successfully!</h3>";
        echo "<p>All phone numbers have been updated to E.164 format (starting with +)</p>";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<h3>ℹ No changes needed</h3>";
        echo "<p>All phone numbers were already in correct format.</p>";
        echo "</div>";
    }

    // Log the changes
    error_log('[Phone Number Migration] Total: ' . $total . ', Fixed: ' . $fixed . ', Skipped: ' . $skipped . ', Errors: ' . $errors);

    echo "</body></html>";

} catch(PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    error_log('[Phone Number Migration] Database error: ' . $e->getMessage());
} catch(Exception $e) {
    echo "<div class='error'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    error_log('[Phone Number Migration] Error: ' . $e->getMessage());
}
?>
