<?php
/**
 * Get Dates With Data API (v1.17.4)
 *
 * Returns an array of dates that have data for a given section
 * Used by Flatpickr to show indicators on dates with data
 *
 * GET Parameters:
 * - type: 'sms_history', 'reminder_history', 'audit_log', 'payments', 'transactions'
 * - start_date: Start of date range (YYYY-MM-DD)
 * - end_date: End of date range (YYYY-MM-DD)
 * - reseller_id: (optional) Filter by reseller
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'err_msg' => 'Not logged in']);
    exit;
}

include(__DIR__ . '/../config.php');

try {
    $dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $ub_db_username, $ub_db_password, $opt);

    // Get user info
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare('SELECT id, super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch();

    if (!$user_info) {
        echo json_encode(['error' => 1, 'err_msg' => 'User not found']);
        exit;
    }

    $is_super_user = $user_info['super_user'] == 1;
    $user_id = $user_info['id'];
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Get request parameters
    $type = $_GET['type'] ?? '';
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: start of current month
    $end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default: end of current month
    $reseller_id = isset($_GET['reseller_id']) && $_GET['reseller_id'] !== '' ? (int)$_GET['reseller_id'] : null;

    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        echo json_encode(['error' => 1, 'err_msg' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }

    $dates = [];

    switch ($type) {
        case 'sms_history':
            // Get dates with SMS logs
            $sql = "SELECT DISTINCT DATE(sent_at) as date_value
                    FROM _sms_logs
                    WHERE DATE(sent_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];

            // Filter by reseller if not super user
            if (!$is_super_user && !$is_reseller_admin) {
                $sql .= " AND reseller_id = ?";
                $params[] = $user_id;
            } elseif ($reseller_id !== null) {
                $sql .= " AND reseller_id = ?";
                $params[] = $reseller_id;
            }

            $sql .= " ORDER BY date_value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;

        case 'reminder_history':
            // Get dates with reminder tracking
            $sql = "SELECT DISTINCT DATE(sent_at) as date_value
                    FROM _sms_reminder_tracking
                    WHERE DATE(sent_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];

            // Filter by reseller if not super user
            if (!$is_super_user && !$is_reseller_admin) {
                $sql .= " AND reseller_id = ?";
                $params[] = $user_id;
            } elseif ($reseller_id !== null) {
                $sql .= " AND reseller_id = ?";
                $params[] = $reseller_id;
            }

            $sql .= " ORDER BY date_value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;

        case 'audit_log':
            // Only super admin can see audit log
            if (!$is_super_user) {
                echo json_encode(['error' => 1, 'err_msg' => 'Permission denied']);
                exit;
            }

            $sql = "SELECT DISTINCT DATE(timestamp) as date_value
                    FROM _audit_log
                    WHERE DATE(timestamp) BETWEEN ? AND ?
                    ORDER BY date_value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;

        case 'payments':
            // Get dates with payment transactions
            $sql = "SELECT DISTINCT DATE(payment_date) as date_value
                    FROM _reseller_payments
                    WHERE DATE(payment_date) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];

            // Filter by reseller
            if (!$is_super_user && !$is_reseller_admin) {
                $sql .= " AND reseller_id = ?";
                $params[] = $user_id;
            } elseif ($reseller_id !== null) {
                $sql .= " AND reseller_id = ?";
                $params[] = $reseller_id;
            }

            $sql .= " ORDER BY date_value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;

        case 'transactions':
            // Get dates with transactions
            $sql = "SELECT DISTINCT DATE(FROM_UNIXTIME(timestamp)) as date_value
                    FROM _transactions
                    WHERE DATE(FROM_UNIXTIME(timestamp)) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];

            // Filter by reseller
            if (!$is_super_user && !$is_reseller_admin) {
                $sql .= " AND for_user = ?";
                $params[] = $user_id;
            } elseif ($reseller_id !== null) {
                $sql .= " AND for_user = ?";
                $params[] = $reseller_id;
            }

            $sql .= " ORDER BY date_value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;

        default:
            echo json_encode(['error' => 1, 'err_msg' => 'Invalid type. Use: sms_history, reminder_history, audit_log, payments, transactions']);
            exit;
    }

    echo json_encode([
        'error' => 0,
        'dates' => $dates,
        'count' => count($dates),
        'range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ]);

} catch (PDOException $e) {
    error_log("get_dates_with_data error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Database error']);
} catch (Exception $e) {
    error_log("get_dates_with_data error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}
?>
