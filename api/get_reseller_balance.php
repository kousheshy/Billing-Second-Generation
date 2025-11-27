<?php
/**
 * Get Reseller Balance API
 * Version: 1.17.0
 *
 * Calculates the running balance for a reseller.
 * Balance = Total Sales - Total Payments
 *
 * Positive = Reseller owes money (بدهکار)
 * Negative = Reseller has credit (طلبکار)
 *
 * GET Parameters:
 * - reseller_id: (optional) Specific reseller, or all if admin
 * - year: (optional) Filter by year for yearly report
 * - month: (optional) Filter by month for monthly report
 */

session_start();
ini_set('display_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');

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

    // Determine user role
    $is_super_admin = ($user_info['super_user'] == 1);
    $is_observer = ($user_info['is_observer'] == 1);
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    $can_view_all = $is_super_admin || $is_reseller_admin || $is_observer;

    // Get parameters
    $filter_reseller_id = isset($_GET['reseller_id']) ? intval($_GET['reseller_id']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;

    // If regular reseller, only show their own balance
    if (!$can_view_all) {
        $filter_reseller_id = $user_info['id'];
    }

    // Build date range
    $dateConditionTx = '';
    $dateConditionPmt = '';
    $dateParams = [];

    if ($year && $month) {
        // Monthly report
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate . ' 23:59:59');
        $dateConditionTx = " AND t.timestamp BETWEEN ? AND ?";
        $dateConditionPmt = " AND p.payment_date BETWEEN ? AND ?";
        $dateParams = [$startTimestamp, $endTimestamp];
        $dateParamsPmt = [$startDate, $endDate];
    } elseif ($year) {
        // Yearly report
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate . ' 23:59:59');
        $dateConditionTx = " AND t.timestamp BETWEEN ? AND ?";
        $dateConditionPmt = " AND p.payment_date BETWEEN ? AND ?";
        $dateParams = [$startTimestamp, $endTimestamp];
        $dateParamsPmt = [$startDate, $endDate];
    }

    // Default empty arrays for date params
    if (!isset($dateParamsPmt)) {
        $dateParamsPmt = [];
    }

    $balances = [];

    if ($filter_reseller_id) {
        // Single reseller balance
        $balance = calculateResellerBalance($pdo, $filter_reseller_id, $dateConditionTx, $dateConditionPmt, $dateParams, $dateParamsPmt);
        $balances[] = $balance;
    } else {
        // All resellers
        $stmt = $pdo->query("SELECT id, name, username, currency_id FROM _users WHERE super_user = 0 AND is_observer = 0 ORDER BY name");
        $resellers = $stmt->fetchAll();

        foreach ($resellers as $reseller) {
            $balance = calculateResellerBalance($pdo, $reseller['id'], $dateConditionTx, $dateConditionPmt, $dateParams, $dateParamsPmt);
            $balance['reseller_name'] = $reseller['name'];
            $balance['reseller_username'] = $reseller['username'];
            $balance['currency'] = $reseller['currency_id'];
            $balances[] = $balance;
        }
    }

    // Calculate grand totals
    $grandTotalSales = 0;
    $grandTotalPayments = 0;
    $grandBalance = 0;

    foreach ($balances as $b) {
        $grandTotalSales += $b['total_sales'];
        $grandTotalPayments += $b['total_payments'];
        $grandBalance += $b['balance'];
    }

    $response['error'] = 0;
    $response['balances'] = $balances;
    $response['grand_totals'] = [
        'total_sales' => $grandTotalSales,
        'total_payments' => $grandTotalPayments,
        'balance' => $grandBalance
    ];
    $response['period'] = [
        'year' => $year,
        'month' => $month,
        'type' => $year && $month ? 'monthly' : ($year ? 'yearly' : 'all_time')
    ];

} catch (Exception $e) {
    $response['error'] = 1;
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

/**
 * Calculate balance for a single reseller
 */
function calculateResellerBalance($pdo, $reseller_id, $dateConditionTx, $dateConditionPmt, $dateParams, $dateParamsPmt) {
    // Get total sales (transactions) - use net_amount considering corrections
    // Note: Transaction amounts are stored as negative (deductions from credit)
    // but represent positive sales, so we negate with -1 * SUM()
    $sqlSales = "
        SELECT
            COALESCE(-1 * SUM(
                CASE
                    WHEN t.status = 'voided' THEN 0
                    WHEN t.correction_amount IS NOT NULL THEN t.amount + t.correction_amount
                    ELSE t.amount
                END
            ), 0) as total_sales,
            COUNT(*) as transaction_count
        FROM _transactions t
        WHERE t.for_user = ? AND t.status != 'voided' $dateConditionTx
    ";

    $stmt = $pdo->prepare($sqlSales);
    $params = [$reseller_id];
    if (!empty($dateParams)) {
        $params = array_merge($params, $dateParams);
    }
    $stmt->execute($params);
    $salesResult = $stmt->fetch();

    // Get total payments
    $sqlPayments = "
        SELECT
            COALESCE(SUM(p.amount), 0) as total_payments,
            COUNT(*) as payment_count
        FROM _reseller_payments p
        WHERE p.reseller_id = ? AND p.status = 'active' $dateConditionPmt
    ";

    $stmt = $pdo->prepare($sqlPayments);
    $params = [$reseller_id];
    if (!empty($dateParamsPmt)) {
        $params = array_merge($params, $dateParamsPmt);
    }
    $stmt->execute($params);
    $paymentsResult = $stmt->fetch();

    $totalSales = floatval($salesResult['total_sales']);
    $totalPayments = floatval($paymentsResult['total_payments']);
    $balance = $totalSales - $totalPayments;

    // Get opening balance (for period reports)
    $openingBalance = 0;
    if (!empty($dateParamsPmt)) {
        // Calculate balance before the start date (use the date string, not timestamp)
        $openingBalance = calculateOpeningBalance($pdo, $reseller_id, $dateParamsPmt[0]);
    }

    // Get reseller info
    $stmt = $pdo->prepare('SELECT name, username, currency_id FROM _users WHERE id = ?');
    $stmt->execute([$reseller_id]);
    $reseller = $stmt->fetch();

    return [
        'reseller_id' => $reseller_id,
        'reseller_name' => $reseller['name'] ?? '',
        'reseller_username' => $reseller['username'] ?? '',
        'currency' => $reseller['currency_id'] ?? 'IRR',
        'opening_balance' => $openingBalance,
        'total_sales' => $totalSales,
        'total_payments' => $totalPayments,
        'balance' => $balance + $openingBalance,
        'closing_balance' => $balance + $openingBalance,
        'transaction_count' => intval($salesResult['transaction_count']),
        'payment_count' => intval($paymentsResult['payment_count']),
        'status' => ($balance + $openingBalance) > 0 ? 'debtor' : (($balance + $openingBalance) < 0 ? 'creditor' : 'settled')
    ];
}

/**
 * Calculate opening balance (all transactions and payments before a date)
 */
function calculateOpeningBalance($pdo, $reseller_id, $beforeDate) {
    // Sales before date - use timestamp (beforeDate is a date string like 2025-01-01)
    // Note: Negate with -1 * SUM() because amounts are stored as negative
    $beforeTimestamp = strtotime($beforeDate);
    $stmt = $pdo->prepare("
        SELECT COALESCE(-1 * SUM(
            CASE
                WHEN status = 'voided' THEN 0
                WHEN correction_amount IS NOT NULL THEN amount + correction_amount
                ELSE amount
            END
        ), 0) as total
        FROM _transactions
        WHERE for_user = ? AND timestamp < ?
    ");
    $stmt->execute([$reseller_id, $beforeTimestamp]);
    $sales = floatval($stmt->fetch()['total']);

    // Payments before date
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM _reseller_payments
        WHERE reseller_id = ? AND status = 'active' AND payment_date < ?
    ");
    $stmt->execute([$reseller_id, $beforeDate]);
    $payments = floatval($stmt->fetch()['total']);

    return $sales - $payments;
}
?>
