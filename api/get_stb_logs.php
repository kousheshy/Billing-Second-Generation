<?php
/**
 * Get STB Action Logs API (v1.17.4)
 *
 * Returns paginated list of STB device control actions
 *
 * GET Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20, max: 100)
 * - action_type: Filter by type ('event', 'message', or empty for all)
 * - mac: Filter by MAC address
 * - search: Search in MAC, username, or action detail
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
    $dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4";
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

    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE '_stb_action_logs'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist - return empty
        echo json_encode([
            'error' => 0,
            'logs' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 20,
                'total_items' => 0,
                'total_pages' => 0
            ]
        ]);
        exit;
    }

    // Get request parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $actionType = $_GET['action_type'] ?? '';
    $macFilter = trim($_GET['mac'] ?? '');
    $search = trim($_GET['search'] ?? '');

    // Build query
    $where = [];
    $params = [];

    // Super admin sees all, resellers only see their own logs
    if (!$is_super_user && !$is_reseller_admin) {
        $where[] = "user_id = ?";
        $params[] = $user_id;
    }

    // Filter by action type
    if ($actionType && in_array($actionType, ['event', 'message'])) {
        $where[] = "action_type = ?";
        $params[] = $actionType;
    }

    // Filter by MAC
    if ($macFilter) {
        $where[] = "mac_address LIKE ?";
        $params[] = '%' . $macFilter . '%';
    }

    // Search
    if ($search) {
        $where[] = "(mac_address LIKE ? OR username LIKE ? OR action_detail LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM _stb_action_logs $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalItems = (int)$stmt->fetch()['total'];
    $totalPages = ceil($totalItems / $perPage);

    // Get logs
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT
                id,
                user_id,
                username,
                action_type,
                mac_address,
                action_detail,
                full_message,
                status,
                error_message,
                created_at
            FROM _stb_action_logs
            $whereClause
            ORDER BY created_at DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Format dates
    foreach ($logs as &$log) {
        $log['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
        $log['created_at_relative'] = getRelativeTime($log['created_at']);
    }

    echo json_encode([
        'error' => 0,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]
    ]);

} catch (PDOException $e) {
    error_log("get_stb_logs error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Database error']);
} catch (Exception $e) {
    error_log("get_stb_logs error: " . $e->getMessage());
    echo json_encode(['error' => 1, 'err_msg' => 'Error: ' . $e->getMessage()]);
}

/**
 * Get relative time string (e.g., "2 hours ago")
 */
function getRelativeTime($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>
