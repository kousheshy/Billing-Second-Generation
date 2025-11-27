<?php
/**
 * Edit Transaction API
 * Version: 1.16.0
 *
 * Allows admins and reseller admins to make corrections to transactions.
 * Transactions are NEVER deleted - only corrected with mandatory comments.
 *
 * Permission Matrix:
 * - Super Admin: Can edit any transaction
 * - Reseller Admin: Can edit any transaction
 * - Reseller: READ-ONLY (cannot edit)
 * - Observer: READ-ONLY (cannot edit)
 *
 * POST Parameters:
 * - transaction_id: The ID of the transaction to correct
 * - correction_amount: Amount to add/subtract (positive=increase, negative=decrease)
 * - correction_note: MANDATORY explanation for the correction
 * - status: 'active', 'corrected', or 'voided'
 */

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');
include('audit_helper.php');

$response = ['error' => 0, 'message' => ''];

// Check login
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['message'] = 'Not logged in';
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

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        $response['error'] = 1;
        $response['message'] = 'User not found';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Check permissions
    $is_super_admin = ($user_info['super_user'] == 1);
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
    $is_observer = isset($user_info['is_observer']) && $user_info['is_observer'] == 1;

    // Only super admin and reseller admin can edit transactions
    if (!$is_super_admin && !$is_reseller_admin) {
        $response['error'] = 1;
        if ($is_observer) {
            $response['message'] = 'Observers cannot edit transactions. This is a read-only account.';
        } else {
            $response['message'] = 'Permission denied. Only Admin or Reseller Admin can edit transactions.';
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Validate input
    $transaction_id = $_POST['transaction_id'] ?? null;
    $correction_amount = $_POST['correction_amount'] ?? null;
    $correction_note = trim($_POST['correction_note'] ?? '');
    $new_status = $_POST['status'] ?? 'corrected';

    if (empty($transaction_id)) {
        $response['error'] = 1;
        $response['message'] = 'Transaction ID is required';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // MANDATORY: correction_note is required
    if (empty($correction_note)) {
        $response['error'] = 1;
        $response['message'] = 'Correction note is MANDATORY. Please explain why this correction is being made.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Validate status
    $valid_statuses = ['active', 'corrected', 'voided'];
    if (!in_array($new_status, $valid_statuses)) {
        $response['error'] = 1;
        $response['message'] = 'Invalid status. Must be: active, corrected, or voided';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Get current transaction
    $stmt = $pdo->prepare('SELECT * FROM _transactions WHERE id = ?');
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        $response['error'] = 1;
        $response['message'] = 'Transaction not found';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Store old values for audit log
    $old_values = [
        'correction_amount' => $transaction['correction_amount'],
        'correction_note' => $transaction['correction_note'],
        'corrected_by' => $transaction['corrected_by'],
        'corrected_by_username' => $transaction['corrected_by_username'],
        'corrected_at' => $transaction['corrected_at'],
        'status' => $transaction['status'] ?? 'active'
    ];

    // Parse correction amount
    $correction_amount_value = null;
    if ($correction_amount !== null && $correction_amount !== '') {
        $correction_amount_value = floatval($correction_amount);
    }

    // Update transaction with correction
    $stmt = $pdo->prepare('
        UPDATE _transactions
        SET correction_amount = ?,
            correction_note = ?,
            corrected_by = ?,
            corrected_by_username = ?,
            corrected_at = NOW(),
            status = ?
        WHERE id = ?
    ');

    $stmt->execute([
        $correction_amount_value,
        $correction_note,
        $user_info['id'],
        $username,
        $new_status,
        $transaction_id
    ]);

    // Calculate net amount for response
    $original_amount = floatval($transaction['amount']);
    $net_amount = $original_amount;
    if ($new_status === 'voided') {
        $net_amount = 0;
    } elseif ($correction_amount_value !== null) {
        $net_amount = $original_amount + $correction_amount_value;
    }

    // Prepare new values for audit log
    $new_values = [
        'correction_amount' => $correction_amount_value,
        'correction_note' => $correction_note,
        'corrected_by' => $user_info['id'],
        'corrected_by_username' => $username,
        'corrected_at' => date('Y-m-d H:i:s'),
        'status' => $new_status,
        'net_amount' => $net_amount
    ];

    // Log to audit
    try {
        logAuditEvent($pdo, 'update', 'transaction', $transaction_id, $transaction['details'] ?? '',
            $old_values,
            $new_values,
            'Transaction correction: ' . $correction_note);
    } catch (Exception $e) {
        error_log("Audit log failed for transaction edit: " . $e->getMessage());
    }

    $response['error'] = 0;
    $response['message'] = 'Transaction corrected successfully';
    $response['transaction'] = [
        'id' => $transaction_id,
        'original_amount' => $original_amount,
        'correction_amount' => $correction_amount_value,
        'net_amount' => $net_amount,
        'status' => $new_status,
        'correction_note' => $correction_note,
        'corrected_by' => $username,
        'corrected_at' => date('Y-m-d H:i:s')
    ];

} catch (PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
