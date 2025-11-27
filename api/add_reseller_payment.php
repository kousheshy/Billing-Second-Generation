<?php
/**
 * Add Reseller Payment API
 * Version: 1.17.0
 *
 * Records a new payment/deposit from a reseller.
 * Only Super Admin and Reseller Admin can add payments.
 *
 * POST Parameters:
 * - reseller_id: The reseller who made the payment
 * - amount: Payment amount (positive number)
 * - currency: Currency code (IRR, GBP, USD, EUR)
 * - payment_date: Date of payment (YYYY-MM-DD)
 * - bank_name: Bank name from predefined list
 * - reference_number: (optional) Bank reference number
 * - description: (optional) Notes about the payment
 * - receipt: (optional) Receipt image file
 */

session_start();
ini_set('display_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');
include('audit_helper.php');
include('push_helper.php');

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

    // Check permissions - only super admin and reseller admin can add payments
    $is_super_admin = ($user_info['super_user'] == 1);
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if (!$is_super_admin && !$is_reseller_admin) {
        $response['error'] = 1;
        $response['message'] = 'Permission denied. Only Admin or Reseller Admin can record payments.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Validate required fields
    $reseller_id = intval($_POST['reseller_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'IRR');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if ($reseller_id <= 0) {
        throw new Exception('Reseller is required');
    }

    if ($amount <= 0) {
        throw new Exception('Amount must be a positive number');
    }

    if (empty($payment_date)) {
        throw new Exception('Payment date is required');
    }

    if (empty($bank_name)) {
        throw new Exception('Bank name is required');
    }

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $payment_date) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }

    // Verify reseller exists
    $stmt = $pdo->prepare('SELECT id, name, username FROM _users WHERE id = ? AND super_user = 0');
    $stmt->execute([$reseller_id]);
    $reseller = $stmt->fetch();

    if (!$reseller) {
        throw new Exception('Reseller not found');
    }

    // Handle receipt upload
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/receipts/';

        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $fileType = mime_content_type($_FILES['receipt']['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, PDF');
        }

        // Generate unique filename
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $reseller_id . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
            $receipt_path = 'uploads/receipts/' . $filename;
        }
    }

    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO _reseller_payments
        (reseller_id, amount, currency, payment_date, bank_name, reference_number,
         receipt_path, description, recorded_by, recorded_by_username, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $stmt->execute([
        $reseller_id,
        $amount,
        $currency,
        $payment_date,
        $bank_name,
        $reference_number ?: null,
        $receipt_path,
        $description ?: null,
        $user_info['id'],
        $username
    ]);

    $payment_id = $pdo->lastInsertId();

    // Log to audit
    try {
        logAuditEvent($pdo, 'create', 'reseller_payment', $payment_id,
            "Payment #{$payment_id} for {$reseller['name']}",
            null,
            [
                'reseller_id' => $reseller_id,
                'reseller_name' => $reseller['name'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_date' => $payment_date,
                'bank_name' => $bank_name,
                'reference_number' => $reference_number
            ],
            "Recorded payment of {$amount} {$currency} from {$reseller['name']}"
        );
    } catch (Exception $e) {
        error_log("Audit log failed for payment: " . $e->getMessage());
    }

    // Send push notification to the reseller
    try {
        $formattedAmount = number_format($amount, 0) . ' ' . $currency;
        notifyResellerPaymentRecorded($pdo, $reseller_id, $formattedAmount, $payment_date);
    } catch (Exception $e) {
        error_log("Push notification failed: " . $e->getMessage());
    }

    $response['error'] = 0;
    $response['message'] = 'Payment recorded successfully';
    $response['payment_id'] = $payment_id;
    $response['payment'] = [
        'id' => $payment_id,
        'reseller_id' => $reseller_id,
        'reseller_name' => $reseller['name'],
        'amount' => $amount,
        'currency' => $currency,
        'payment_date' => $payment_date,
        'bank_name' => $bank_name,
        'reference_number' => $reference_number,
        'receipt_path' => $receipt_path,
        'description' => $description,
        'recorded_by' => $username,
        'status' => 'active'
    ];

} catch (Exception $e) {
    $response['error'] = 1;
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
