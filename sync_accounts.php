<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include('config.php');
include('api.php');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1)
{
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
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

// Get current user info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = ($user_info['super_user'] == 1);
$user_id = $user_info['id'];

try {
    // IMPORTANT: Get existing account-to-reseller mappings BEFORE deleting accounts
    // Note: We DON'T preserve phone numbers - they must come from Stalker Portal as the single source of truth
    // Use BOTH username AND MAC for lookup to ensure we preserve reseller assignments even if username changes

    // PERSISTENT BACKUP: Save to file to survive multiple sync cycles
    $backup_file = __DIR__ . '/reseller_assignments_backup.json';

    $existing_resellers = [];
    $stmt = $pdo->prepare('SELECT username, mac, reseller FROM _accounts WHERE reseller IS NOT NULL');
    $stmt->execute();
    $db_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If database has assignments, use them AND save to backup file
    if(count($db_assignments) > 0) {
        foreach($db_assignments as $row) {
            // Store by both username AND MAC address (MAC is primary key as it never changes)
            $existing_resellers['mac_' . $row['mac']] = $row['reseller'];
            $existing_resellers['user_' . $row['username']] = $row['reseller'];
        }

        // Save to persistent backup file
        $backup_data = [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'count' => count($db_assignments),
            'assignments' => $existing_resellers
        ];
        file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        error_log("[SYNC] Saved " . count($db_assignments) . " assignments to backup file");
    }
    // If database is empty, try to load from backup file
    else if(file_exists($backup_file)) {
        $backup_data = json_decode(file_get_contents($backup_file), true);
        if($backup_data && isset($backup_data['assignments'])) {
            $existing_resellers = $backup_data['assignments'];
            error_log("[SYNC] Database empty! Restored " . count($existing_resellers) . " assignments from backup file (saved: " . $backup_data['date'] . ")");
        } else {
            error_log("[SYNC] WARNING: Backup file exists but is invalid");
        }
    } else {
        error_log("[SYNC] WARNING: No assignments in database AND no backup file found!");
    }

    // For admins: Delete all accounts
    // For resellers: Delete only their accounts
    if($is_admin) {
        $stmt = $pdo->prepare('DELETE FROM _accounts');
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare('DELETE FROM _accounts WHERE reseller = ?');
        $stmt->execute([$user_id]);
    }

    // Use the 'accounts' endpoint to fetch all accounts from Stalker Portal
    $case = 'accounts';
    $op = "GET";

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

    // DEBUG: Log raw Stalker API response (first 500 chars to see structure)
    error_log("=== STALKER API GET ACCOUNTS ===");
    error_log("Raw response (first 500 chars): " . substr($res, 0, 500));

    $decoded = json_decode($res);

    if($decoded->status != 'OK') {
        $response['error'] = 1;
        $response['err_msg'] = 'Failed to fetch accounts from Stalker Portal: ' . ($decoded->error ?? 'Unknown error');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $synced_count = 0;
    $skipped_count = 0;

    // The API returns an array of accounts in results
    $stalker_accounts = [];

    // Handle both array and object response formats
    if(isset($decoded->results)) {
        if(is_array($decoded->results)) {
            $stalker_accounts = $decoded->results;
        } elseif(is_object($decoded->results)) {
            $stalker_accounts = [$decoded->results];
        }
    }

    // Process each account
    foreach($stalker_accounts as $stalker_user) {
        // Extract login and MAC from the user object
        $login = $stalker_user->login ?? null;
        $mac = $stalker_user->stb_mac ?? null;

        // Skip if no valid login or MAC
        if(empty($login) || empty($mac)) {
            $skipped_count++;
            continue;
        }

        // Extract additional data from Stalker Portal
        $full_name = $stalker_user->full_name ?? $stalker_user->name ?? '';
        $tariff_plan = $stalker_user->tariff_plan ?? $stalker_user->tariff ?? '';
        $email = $stalker_user->email ?? '';
        $status = $stalker_user->status ?? 1; // Default to 1 (ON) if not set

        // Handle end_date - convert invalid dates to NULL
        $end_date = $stalker_user->end_date ?? null;
        if($end_date && (strpos($end_date, '0000-00-00') === 0 || empty($end_date))) {
            $end_date = null;
        }

        // Use current timestamp for creation date
        $created_timestamp = time();

        // Determine reseller priority:
        // 1. Use reseller from Stalker if available (Stalker is source of truth)
        // 2. Fallback to existing local mapping by MAC address (most reliable - MAC never changes)
        // 3. Fallback to existing local mapping by username
        // 4. If no mapping exists, leave as NULL (Not Assigned)
        $stalker_reseller = $stalker_user->reseller ?? null;

        // DEBUG: Log what Stalker returned for reseller field
        error_log("=== SYNC ACCOUNT: $login (MAC: $mac) ===");
        error_log("Stalker reseller field value: " . ($stalker_reseller !== null ? $stalker_reseller : 'NULL'));
        error_log("Existing local mapping by MAC: " . ($existing_resellers['mac_' . $mac] ?? 'NONE'));
        error_log("Existing local mapping by username: " . ($existing_resellers['user_' . $login] ?? 'NONE'));

        if($stalker_reseller && is_numeric($stalker_reseller) && $stalker_reseller > 0) {
            $reseller_id = (int)$stalker_reseller;
            error_log("Using Stalker reseller: " . $reseller_id);
        } else if(isset($existing_resellers['mac_' . $mac])) {
            // MAC-based lookup is most reliable
            $reseller_id = $existing_resellers['mac_' . $mac];
            error_log("Using existing local mapping by MAC: " . $reseller_id);
        } else if(isset($existing_resellers['user_' . $login])) {
            // Username-based fallback
            $reseller_id = $existing_resellers['user_' . $login];
            error_log("Using existing local mapping by username: " . $reseller_id);
        } else {
            $reseller_id = null;  // Not assigned to any reseller
            error_log("No reseller assignment - setting to NULL (Not Assigned)");
        }

        // For resellers: only sync accounts that belong to them
        if(!$is_admin && $reseller_id != $user_id) {
            $skipped_count++;
            continue;
        }

        // Phone number - ONLY from Stalker Portal (single source of truth)
        // If Stalker doesn't have a phone, local database won't have it either
        $phone_number = $stalker_user->phone ?? null;

        // Insert account into local database
        $stmt = $pdo->prepare('INSERT INTO _accounts (username, email, mac, phone_number, full_name, tariff_plan, end_date, status, reseller, timestamp) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $login,
            $email,
            $mac,
            $phone_number,
            $full_name,
            $tariff_plan,
            $end_date,
            $status,
            $reseller_id,
            $created_timestamp
        ]);

        $synced_count++;
    }

    // Get total accounts count
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM _accounts');
    $stmt->execute();
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['error'] = 0;
    $response['err_msg'] = '';
    $response['synced'] = $synced_count;
    $response['skipped'] = $skipped_count;
    $response['total_accounts'] = $total_result['total'];

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Sync failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
