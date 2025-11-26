<?php
/**
 * Get Audit Log API
 * Version: 1.12.0
 *
 * READ-ONLY endpoint to fetch audit log entries.
 * IMPORTANT: No DELETE capability - audit log is permanent.
 *
 * Access Control:
 * - Super admin: Can view ALL audit logs
 * - Other users: Can only view their own actions
 *
 * Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 50, max: 100)
 * - user_id: Filter by user (super admin only)
 * - action: Filter by action type (create, update, delete, etc.)
 * - target_type: Filter by target type (account, user, etc.)
 * - date_from: Filter from date (YYYY-MM-DD)
 * - date_to: Filter to date (YYYY-MM-DD)
 * - search: Search in target_name or details
 */

session_start();
header('Content-Type: application/json');

// Only allow GET requests - NO DELETE capability
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 1, 'message' => 'Method not allowed. Audit log is read-only.']);
    exit;
}

// Check authentication
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Unauthorized']);
    exit;
}

include(__DIR__ . '/../config.php');

$host = $ub_db_host;
$db = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, intval($_GET['per_page']))) : 50;
    $offset = ($page - 1) * $per_page;

    // User info
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;

    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE '_audit_log'");
    if ($tableCheck->rowCount() == 0) {
        echo json_encode([
            'error' => 0,
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $per_page,
                'total_records' => 0,
                'total_pages' => 0
            ],
            'message' => 'Audit log table not initialized'
        ]);
        exit;
    }

    // Build WHERE clause based on filters
    $where_clauses = [];
    $params = [];

    // Non-super-admins can only see their own actions
    if (!$is_super_admin) {
        $where_clauses[] = 'al.user_id = ?';
        $params[] = $current_user_id;
    } else {
        // Super admin can filter by user
        if (isset($_GET['user_id']) && $_GET['user_id'] !== '' && $_GET['user_id'] !== 'all') {
            $where_clauses[] = 'al.user_id = ?';
            $params[] = intval($_GET['user_id']);
        }
    }

    // Filter by action type
    if (isset($_GET['action']) && $_GET['action'] !== '') {
        $where_clauses[] = 'al.action = ?';
        $params[] = $_GET['action'];
    }

    // Filter by target type
    if (isset($_GET['target_type']) && $_GET['target_type'] !== '') {
        $where_clauses[] = 'al.target_type = ?';
        $params[] = $_GET['target_type'];
    }

    // Filter by date range
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $where_clauses[] = 'DATE(al.timestamp) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $where_clauses[] = 'DATE(al.timestamp) <= ?';
        $params[] = $_GET['date_to'];
    }

    // Search filter
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search = '%' . $_GET['search'] . '%';
        $where_clauses[] = '(al.target_name LIKE ? OR al.details LIKE ? OR al.username LIKE ?)';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM _audit_log al $where_sql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $per_page);

    // Fetch audit log entries
    $sql = "
        SELECT
            al.id,
            al.timestamp,
            al.user_id,
            al.username,
            al.user_type,
            al.action,
            al.target_type,
            al.target_id,
            al.target_name,
            al.old_value,
            al.new_value,
            al.ip_address,
            al.details,
            u.name as user_display_name
        FROM _audit_log al
        LEFT JOIN _users u ON al.user_id = u.id
        $where_sql
        ORDER BY al.timestamp DESC
        LIMIT " . intval($per_page) . " OFFSET " . intval($offset);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Decode JSON values for each log entry
    foreach ($logs as &$log) {
        if ($log['old_value']) {
            $log['old_value'] = json_decode($log['old_value'], true);
        }
        if ($log['new_value']) {
            $log['new_value'] = json_decode($log['new_value'], true);
        }
    }

    // Get available filter options for super admin
    $filter_options = null;
    if ($is_super_admin) {
        // Get unique actions
        $actionsStmt = $pdo->query('SELECT DISTINCT action FROM _audit_log ORDER BY action');
        $actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

        // Get unique target types
        $targetTypesStmt = $pdo->query('SELECT DISTINCT target_type FROM _audit_log ORDER BY target_type');
        $targetTypes = $targetTypesStmt->fetchAll(PDO::FETCH_COLUMN);

        // Get users who have audit entries
        $usersStmt = $pdo->query('
            SELECT DISTINCT al.user_id, al.username, u.name
            FROM _audit_log al
            LEFT JOIN _users u ON al.user_id = u.id
            ORDER BY al.username
        ');
        $users = $usersStmt->fetchAll();

        $filter_options = [
            'actions' => $actions,
            'target_types' => $targetTypes,
            'users' => $users
        ];
    }

    echo json_encode([
        'error' => 0,
        'data' => $logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => (int)$totalRecords,
            'total_pages' => (int)$totalPages
        ],
        'filter_options' => $filter_options,
        'is_super_admin' => $is_super_admin,
        'security_notice' => 'Audit log entries cannot be deleted or modified.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
