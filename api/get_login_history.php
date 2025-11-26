<?php
/**
 * Get Login History API
 * Version: 1.12.0
 *
 * Fetches login history with pagination.
 * - All users can view their own login history
 * - Super admins can view any user's login history
 *
 * Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20, max: 100)
 * - user_id: (Super admin only) Specific user's history to view
 */

session_start();
header('Content-Type: application/json');

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
    $per_page = isset($_GET['per_page']) ? min(100, max(1, intval($_GET['per_page']))) : 20;
    $offset = ($page - 1) * $per_page;

    // Determine which user's history to fetch
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin = $_SESSION['super_user'] == 1;

    // Target user (default: current user)
    $target_user_id = $current_user_id;
    $view_all_users = false;

    // Super admin can view any user's history or ALL users
    if ($is_super_admin) {
        if (isset($_GET['user_id'])) {
            if ($_GET['user_id'] === 'all') {
                $view_all_users = true;
            } else if ($_GET['user_id'] !== '') {
                $target_user_id = intval($_GET['user_id']);
            }
        }
    }

    // For non-super-admins, only allow viewing own history
    if (!$is_super_admin && isset($_GET['user_id']) && $_GET['user_id'] !== '' && intval($_GET['user_id']) != $current_user_id) {
        echo json_encode(['error' => 1, 'message' => 'Permission denied']);
        exit;
    }

    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE '_login_history'");
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
            'message' => 'Login history table not initialized'
        ]);
        exit;
    }

    // Get total count and fetch history based on view mode
    if ($view_all_users) {
        // Super admin viewing ALL users
        $countStmt = $pdo->query('SELECT COUNT(*) as total FROM _login_history');
        $totalRecords = $countStmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $per_page);

        $stmt = $pdo->prepare("
            SELECT
                lh.id,
                lh.user_id,
                lh.username,
                lh.login_time,
                lh.ip_address,
                lh.user_agent,
                lh.login_method,
                lh.status,
                lh.failure_reason,
                u.name as user_name
            FROM _login_history lh
            LEFT JOIN _users u ON lh.user_id = u.id
            ORDER BY lh.login_time DESC
            LIMIT " . intval($per_page) . " OFFSET " . intval($offset) . "
        ");
        $stmt->execute();
    } else {
        // Viewing specific user
        $countStmt = $pdo->prepare('SELECT COUNT(*) as total FROM _login_history WHERE user_id = ?');
        $countStmt->execute([$target_user_id]);
        $totalRecords = $countStmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $per_page);

        $stmt = $pdo->prepare("
            SELECT
                lh.id,
                lh.user_id,
                lh.username,
                lh.login_time,
                lh.ip_address,
                lh.user_agent,
                lh.login_method,
                lh.status,
                lh.failure_reason,
                u.name as user_name
            FROM _login_history lh
            LEFT JOIN _users u ON lh.user_id = u.id
            WHERE lh.user_id = ?
            ORDER BY lh.login_time DESC
            LIMIT " . intval($per_page) . " OFFSET " . intval($offset) . "
        ");
        $stmt->execute([$target_user_id]);
    }
    $history = $stmt->fetchAll();

    // Get target user info for display (super admin viewing other users)
    $target_user_info = null;
    if ($is_super_admin && !$view_all_users && $target_user_id != $current_user_id) {
        $userStmt = $pdo->prepare('SELECT id, username, name FROM _users WHERE id = ?');
        $userStmt->execute([$target_user_id]);
        $target_user_info = $userStmt->fetch();
    }

    echo json_encode([
        'error' => 0,
        'data' => $history,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => (int)$totalRecords,
            'total_pages' => (int)$totalPages
        ],
        'target_user' => $target_user_info,
        'is_own_history' => !$view_all_users && $target_user_id == $current_user_id,
        'view_all_users' => $view_all_users
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
