<?php
/**
 * Bulk Assign Reseller API (v1.17.2)
 *
 * Assigns multiple accounts to a reseller at once
 *
 * POST Parameters (JSON body):
 * - usernames: array of account usernames to update
 * - reseller_id: ID of reseller to assign (null for "Not Assigned")
 */

session_start();
header('Content-Type: application/json');

// Disable error display, log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    include(__DIR__ . '/../config.php');
    include('audit_helper.php');

    // Check if user is logged in
    if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
        echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
        exit;
    }

    $session_username = $_SESSION['username'];

    // Database connection
    $dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $ub_db_username, $ub_db_password, $opt);

    // Check if user is super admin or reseller admin
    $stmt = $pdo->prepare('SELECT id, super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$session_username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found']);
        exit;
    }

    // Check permissions
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if ($user_info['super_user'] != 1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'Only administrators can bulk assign accounts to resellers']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['usernames']) || !is_array($input['usernames'])) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid request: usernames array required']);
        exit;
    }

    $usernames = $input['usernames'];
    $reseller_id = isset($input['reseller_id']) && $input['reseller_id'] !== '' ? (int)$input['reseller_id'] : null;

    if (count($usernames) === 0) {
        echo json_encode(['error' => 1, 'err_msg' => 'No accounts specified']);
        exit;
    }

    // Validate reseller exists if specified
    $reseller_name = 'Not Assigned';
    if ($reseller_id !== null) {
        $stmt = $pdo->prepare('SELECT name FROM _users WHERE id = ?');
        $stmt->execute([$reseller_id]);
        $reseller = $stmt->fetch();

        if (!$reseller) {
            echo json_encode(['error' => 1, 'err_msg' => 'Reseller not found']);
            exit;
        }
        $reseller_name = $reseller['name'];
    }

    // Build placeholders for IN clause
    $placeholders = str_repeat('?,', count($usernames) - 1) . '?';

    // Update all accounts
    $stmt = $pdo->prepare("UPDATE _accounts SET reseller = ? WHERE username IN ($placeholders)");

    // Merge parameters: reseller_id first, then all usernames
    $params = array_merge([$reseller_id], $usernames);
    $stmt->execute($params);

    $updated_count = $stmt->rowCount();

    // Log the bulk assignment
    error_log("Bulk assign by '{$session_username}': Assigned {$updated_count} accounts to reseller: {$reseller_name} (ID: " . ($reseller_id ?? 'NULL') . ")");

    // Audit log
    try {
        logAuditEvent($pdo, 'bulk_update', 'accounts', null, null,
            ['count' => count($usernames)],
            ['reseller_id' => $reseller_id, 'reseller_name' => $reseller_name],
            "Bulk assigned {$updated_count} accounts to reseller: {$reseller_name}"
        );
    } catch (Exception $e) {
        // Audit logging should not break the main flow
        error_log("Bulk assign audit error: " . $e->getMessage());
    }

    echo json_encode([
        'error' => 0,
        'message' => "Successfully assigned {$updated_count} account(s) to {$reseller_name}",
        'updated_count' => $updated_count,
        'reseller_name' => $reseller_name
    ]);

} catch (PDOException $e) {
    error_log("Bulk assign DB error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Bulk assign error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}
?>
