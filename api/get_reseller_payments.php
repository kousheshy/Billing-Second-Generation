<?php
/**
 * Get Reseller Payments API
 * Version: 1.17.0
 *
 * Retrieves payment history for resellers.
 * - Admin/Reseller Admin: Can view all payments or filter by reseller
 * - Reseller: Can only view their own payments
 *
 * GET Parameters:
 * - reseller_id: (optional) Filter by specific reseller
 * - start_date: (optional) Filter from date (YYYY-MM-DD)
 * - end_date: (optional) Filter to date (YYYY-MM-DD)
 * - status: (optional) Filter by status (active, cancelled, all)
 * - limit: (optional) Number of records (default: 100)
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
    $can_edit = $is_super_admin || $is_reseller_admin;

    // Get filter parameters
    $filter_reseller_id = isset($_GET['reseller_id']) ? intval($_GET['reseller_id']) : null;
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date = trim($_GET['end_date'] ?? '');
    $status_filter = trim($_GET['status'] ?? 'active');
    $limit = min(intval($_GET['limit'] ?? 100), 500);

    // Build query
    $sql = "
        SELECT
            p.*,
            u.name as reseller_name,
            u.username as reseller_username,
            u.currency_id as reseller_currency,
            recorder.name as recorder_name
        FROM _reseller_payments p
        LEFT JOIN _users u ON p.reseller_id = u.id
        LEFT JOIN _users recorder ON p.recorded_by = recorder.id
        WHERE 1=1
    ";

    $params = [];

    // If regular reseller, only show their own payments
    if (!$can_view_all) {
        $sql .= " AND p.reseller_id = ?";
        $params[] = $user_info['id'];
    } elseif ($filter_reseller_id) {
        $sql .= " AND p.reseller_id = ?";
        $params[] = $filter_reseller_id;
    }

    // Date filters
    if (!empty($start_date)) {
        $sql .= " AND p.payment_date >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {
        $sql .= " AND p.payment_date <= ?";
        $params[] = $end_date;
    }

    // Status filter
    if ($status_filter !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }

    // LIMIT doesn't accept string parameters in MySQL, so we safely cast to int
    $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT " . intval($limit);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    // Calculate totals
    $totalActive = 0;
    $totalCancelled = 0;
    $countActive = 0;
    $countCancelled = 0;

    foreach ($payments as &$payment) {
        if ($payment['status'] === 'active') {
            $totalActive += floatval($payment['amount']);
            $countActive++;
        } else {
            $totalCancelled += floatval($payment['amount']);
            $countCancelled++;
        }

        // Convert Gregorian to Shamsi date
        $payment['payment_date_shamsi'] = gregorianToShamsi($payment['payment_date']);
    }
    unset($payment);

    $response['error'] = 0;
    $response['payments'] = $payments;
    $response['summary'] = [
        'total_active' => $totalActive,
        'total_cancelled' => $totalCancelled,
        'count_active' => $countActive,
        'count_cancelled' => $countCancelled
    ];
    $response['can_edit'] = $can_edit;
    $response['can_view_all'] = $can_view_all;

} catch (Exception $e) {
    $response['error'] = 1;
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

/**
 * Convert Gregorian date to Shamsi (Jalali)
 */
function gregorianToShamsi($gDate) {
    if (empty($gDate)) return '';

    $gYear = intval(substr($gDate, 0, 4));
    $gMonth = intval(substr($gDate, 5, 2));
    $gDay = intval(substr($gDate, 8, 2));

    $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy = $gYear - 1600;
    $gm = $gMonth - 1;
    $gd = $gDay - 1;

    $gDayNo = 365 * $gy + intval(($gy + 3) / 4) - intval(($gy + 99) / 100) + intval(($gy + 399) / 400);

    for ($i = 0; $i < $gm; ++$i) {
        $gDayNo += $gDaysInMonth[$i];
    }

    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $gDayNo++;
    }

    $gDayNo += $gd;

    $jDayNo = $gDayNo - 79;
    $jNp = intval($jDayNo / 12053);
    $jDayNo %= 12053;

    $jy = 979 + 33 * $jNp + 4 * intval($jDayNo / 1461);
    $jDayNo %= 1461;

    if ($jDayNo >= 366) {
        $jy += intval(($jDayNo - 1) / 365);
        $jDayNo = ($jDayNo - 1) % 365;
    }

    $jm = 0;
    for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; ++$i) {
        $jDayNo -= $jDaysInMonth[$i];
        $jm++;
    }

    $jd = $jDayNo + 1;

    return sprintf('%04d/%02d/%02d', $jy, $jm + 1, $jd);
}
?>
