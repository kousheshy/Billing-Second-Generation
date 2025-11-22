<?php

session_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set JSON header first
header('Content-Type: application/json');

try {
    include('config.php');

    if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
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

    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);

    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user_info['super_user']!=1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Unauthorized. Admin only.']);
        exit();
    }

    // Validate required fields
    if(empty($_POST['id']) || empty($_POST['username']) || empty($_POST['name'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required fields']);
        exit();
    }

    $id = trim($_POST['id']);

    $stmt = $pdo->prepare('SELECT * FROM _users WHERE id = ?');
    $stmt->execute([$id]);

    $reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$reseller_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'Reseller not found']);
        exit();
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if(empty($password)) {
        $password = $reseller_info['password'];
    } else {
        $password = md5($password);
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $max_users = trim($_POST['max_users']);

    if(empty($max_users)) {
        $max_users = 0;
    }

    $theme = trim($_POST['theme']);
    $use_ip_ranges = isset($_POST['use_ip_ranges']) ? $_POST['use_ip_ranges'] : '';
    $currency = $_POST['currency'];
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : '0|0|0|0|1|0';
    $is_observer = isset($_POST['is_observer']) ? intval($_POST['is_observer']) : 0;

    // Check if theme has changed
    $theme_changed = ($reseller_info['theme'] !== $theme);

    // All resellers remain with super_user = 0
    // Admin-level permissions are stored in permissions string (index 2)
    // Delete permission is stored in permissions string (index 3)
    // Observer status is stored in is_observer field
    // Note: Plans are NOT updated here - they are managed separately via assign_plans.php
    $stmt = $pdo->prepare('UPDATE _users SET username=?, password=?, name=?, email=?, max_users=?, theme=?, ip_ranges=?, currency_id=?, super_user=?, is_observer=?, permissions=? WHERE id=?');
    $stmt->execute([$username, $password, $name, $email, $max_users, $theme, $use_ip_ranges, $currency, 0, $is_observer, $permissions, $id]);

    // If theme changed, update all accounts under this reseller
    if ($theme_changed && !empty($theme)) {
        error_log("Theme changed for reseller {$id} from '{$reseller_info['theme']}' to '{$theme}'. Updating all accounts...");

        // Get all accounts under this reseller
        $stmt = $pdo->prepare('SELECT username FROM _accounts WHERE reseller = ?');
        $stmt->execute([$id]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated_accounts = 0;
        $failed_accounts = 0;

        foreach ($accounts as $account) {
            $account_username = $account['username'];

            // Update theme on Stalker Portal server
            $data = "key=f4H75Sgf53GH4dd&login={$account_username}&theme={$theme}";
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
            curl_close($curl);

            if ($result === 'OK') {
                $updated_accounts++;
                error_log("✓ Updated theme for account: {$account_username}");
            } else {
                $failed_accounts++;
                error_log("✗ Failed to update theme for account: {$account_username} - Response: {$result}");
            }

            // Small delay to avoid overwhelming the server
            usleep(100000); // 0.1 second delay
        }

        error_log("Theme update summary: {$updated_accounts} updated, {$failed_accounts} failed out of " . count($accounts) . " total accounts");

        if ($failed_accounts > 0) {
            echo json_encode([
                'error' => 0,
                'warning' => 1,
                'err_msg' => "Reseller updated. Theme changed for {$updated_accounts}/" . count($accounts) . " accounts ({$failed_accounts} failed)."
            ]);
        } else {
            echo json_encode([
                'error' => 0,
                'err_msg' => "Reseller updated successfully. Theme changed for all {$updated_accounts} accounts."
            ]);
        }
    } else {
        echo json_encode(['error' => 0, 'err_msg' => '']);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}
?>