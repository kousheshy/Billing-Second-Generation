<?php
/**
 * Get SMS Logs
 * Returns SMS sending history for the logged-in user
 */

session_start();
include('config.php');

header('Content-Type: application/json');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
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

    // Get user ID
    $stmt = $pdo->prepare('SELECT id, super_user FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $user_id = $user_data['id'];
    $is_super_user = $user_data['super_user'];

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;

    // Filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    $type_filter = isset($_GET['type']) ? $_GET['type'] : null;
    $date_filter = isset($_GET['date']) ? $_GET['date'] : null;

    // Build query
    $where_clauses = [];
    $params = [];

    if (!$is_super_user) {
        $where_clauses[] = 'sl.sent_by = ?';
        $params[] = $user_id;
    }

    if ($status_filter && in_array($status_filter, ['sent', 'failed', 'pending'])) {
        $where_clauses[] = 'sl.status = ?';
        $params[] = $status_filter;
    }

    if ($type_filter && in_array($type_filter, ['manual', 'expiry_reminder', 'renewal', 'new_account'])) {
        $where_clauses[] = 'sl.message_type = ?';
        $params[] = $type_filter;
    }

    if ($date_filter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
        $where_clauses[] = 'DATE(sl.sent_at) = ?';
        $params[] = $date_filter;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM _sms_logs sl $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get logs
    $sql = "SELECT
                sl.*,
                u.username as sent_by_username,
                u.name as sent_by_name
            FROM _sms_logs sl
            LEFT JOIN _users u ON sl.sent_by = u.id
            $where_sql
            ORDER BY sl.sent_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    echo json_encode([
        'error' => 0,
        'logs' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
