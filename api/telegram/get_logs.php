<?php
/**
 * Get Telegram Message Logs
 * Returns message history with filtering options
 */

session_start();
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../telegram_helper.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit;
}

$host = $ub_db_host;
$db = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Get current user info
    $stmt = $pdo->prepare('SELECT id, username, super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();

    if (!$current_user) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit;
    }

    // Parse permissions to check if reseller has admin-level permissions
    $permissions = explode('|', $current_user['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Check access
    if ($current_user['super_user'] != 1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'message' => 'Access denied']);
        exit;
    }

    // Get filters
    $date = $_GET['date'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? '';
    $message_type = $_GET['message_type'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = min(100, max(10, intval($_GET['per_page'] ?? 25)));

    // Build query
    $where_conditions = ["DATE(tl.sent_at) = ?"];
    $params = [$date];

    if (!empty($status)) {
        $where_conditions[] = "tl.status = ?";
        $params[] = $status;
    }

    if (!empty($message_type)) {
        $where_conditions[] = "tl.message_type = ?";
        $params[] = $message_type;
    }

    if (!empty($search)) {
        $where_conditions[] = "(u.username LIKE ? OR tl.message LIKE ? OR tl.related_account_mac LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM _telegram_logs tl
                  LEFT JOIN _users u ON tl.user_id = u.id
                  WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Calculate pagination
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;

    // Get logs
    $sql = "SELECT tl.*, u.username as recipient_username
            FROM _telegram_logs tl
            LEFT JOIN _users u ON tl.user_id = u.id
            WHERE $where_clause
            ORDER BY tl.sent_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get stats for the selected date
    $stats_sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                  FROM _telegram_logs
                  WHERE DATE(sent_at) = ?";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute([$date]);
    $stats = $stmt->fetch();

    // Get available dates that have logs
    $dates_sql = "SELECT DISTINCT DATE(sent_at) as log_date FROM _telegram_logs ORDER BY log_date DESC LIMIT 30";
    $stmt = $pdo->query($dates_sql);
    $available_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'error' => 0,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_items' => $total,
            'total_pages' => $total_pages
        ],
        'stats' => $stats,
        'available_dates' => $available_dates
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
