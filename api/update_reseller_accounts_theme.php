<?php

session_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set JSON header first
header('Content-Type: application/json');

try {
    include(__DIR__ . '/../config.php');

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

    // Check if user is admin or reseller admin
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if($user_info['super_user']!=1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'Unauthorized. Admin or Reseller Admin only.']);
        exit();
    }

    // Validate required fields
    if(empty($_POST['reseller_id']) || empty($_POST['theme'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Missing required fields']);
        exit();
    }

    $reseller_id = intval($_POST['reseller_id']);
    $theme = trim($_POST['theme']);

    // Get all accounts under this reseller
    $stmt = $pdo->prepare('SELECT username FROM _accounts WHERE reseller = ?');
    $stmt->execute([$reseller_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($accounts);
    $updated = 0;
    $failed = 0;
    $errors = [];

    error_log("=== UPDATE RESELLER ACCOUNTS THEME ===");
    error_log("Reseller ID: {$reseller_id}");
    error_log("New Theme: {$theme}");
    error_log("Total Accounts: {$total}");

    // Update each account's theme on Stalker Portal
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
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($result === 'OK') {
            $updated++;
            error_log("✓ Updated theme for account: {$account_username}");
        } else {
            $failed++;
            $error_msg = $curl_error ? $curl_error : $result;
            $errors[] = "{$account_username}: {$error_msg}";
            error_log("✗ Failed to update theme for account: {$account_username} - {$error_msg}");
        }

        // Small delay to avoid overwhelming the server
        usleep(100000); // 0.1 second delay
    }

    error_log("Summary: {$updated} updated, {$failed} failed");
    error_log("=== END UPDATE RESELLER ACCOUNTS THEME ===");

    if ($failed > 0) {
        echo json_encode([
            'error' => 0,
            'warning' => 1,
            'err_msg' => "Theme updated for {$updated}/{$total} accounts. {$failed} failed.",
            'details' => [
                'total' => $total,
                'updated' => $updated,
                'failed' => $failed,
                'errors' => $errors
            ]
        ]);
    } else {
        echo json_encode([
            'error' => 0,
            'err_msg' => "Theme updated successfully for all {$updated} accounts.",
            'details' => [
                'total' => $total,
                'updated' => $updated,
                'failed' => $failed
            ]
        ]);
    }

} catch(PDOException $e) {
    error_log("Database error in update_reseller_accounts_theme.php: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Error in update_reseller_accounts_theme.php: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}
?>
