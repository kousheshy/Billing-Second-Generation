<?php
/**
 * Cancel Reseller Payment API
 * Version: 1.17.0
 *
 * Cancels (soft delete) a payment record.
 * Payment is NOT deleted - just marked as cancelled with a reason.
 * Only Super Admin and Reseller Admin can cancel payments.
 *
 * POST Parameters:
 * - payment_id: The payment to cancel
 * - reason: Mandatory reason for cancellation
 */

session_start();
ini_set('display_errors', 0);
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

try {
    $pdo = new PDO(
        "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4",
        $ub_db_username,
        $ub_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch();

    if (!$user_info) {
        throw new Exception('User not found');
    }

    // Check permissions
    $is_super_admin = ($user_info['super_user'] == 1);
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if (!$is_super_admin && !$is_reseller_admin) {
        throw new Exception('Permission denied. Only Admin or Reseller Admin can cancel payments.');
    }

    // Validate input
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($payment_id <= 0) {
        throw new Exception('Payment ID is required');
    }

    if (empty($reason)) {
        throw new Exception('Cancellation reason is MANDATORY');
    }

    // Get current payment
    $stmt = $pdo->prepare('
        SELECT p.*, u.name as reseller_name
        FROM _reseller_payments p
        LEFT JOIN _users u ON p.reseller_id = u.id
        WHERE p.id = ?
    ');
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    if ($payment['status'] === 'cancelled') {
        throw new Exception('Payment is already cancelled');
    }

    // Store old values for audit
    $old_values = [
        'status' => $payment['status'],
        'amount' => $payment['amount'],
        'reseller_name' => $payment['reseller_name']
    ];

    // Cancel the payment
    $stmt = $pdo->prepare("
        UPDATE _reseller_payments
        SET status = 'cancelled',
            cancelled_by = ?,
            cancelled_at = NOW(),
            cancellation_reason = ?
        WHERE id = ?
    ");
    $stmt->execute([$user_info['id'], $reason, $payment_id]);

    // Log to audit
    try {
        logAuditEvent($pdo, 'update', 'reseller_payment', $payment_id,
            "Cancelled Payment #{$payment_id}",
            $old_values,
            [
                'status' => 'cancelled',
                'cancelled_by' => $username,
                'cancellation_reason' => $reason
            ],
            "Cancelled payment of {$payment['amount']} {$payment['currency']} from {$payment['reseller_name']}: $reason"
        );
    } catch (Exception $e) {
        error_log("Audit log failed for payment cancellation: " . $e->getMessage());
    }

    $response['error'] = 0;
    $response['message'] = 'Payment cancelled successfully';
    $response['payment_id'] = $payment_id;

} catch (Exception $e) {
    $response['error'] = 1;
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
