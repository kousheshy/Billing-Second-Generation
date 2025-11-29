<?php
/**
 * Get Mail Logs API
 * Returns email history with filtering and pagination
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can view mail logs (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can view mail logs.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'reseller';

// Get filter parameters
$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

try {
    // Build query
    $where = ["DATE(ml.created_at) = ?"];
    $params = [$date];

    // Super admins see all logs, others see only their own
    if ($role !== 'super_admin') {
        $where[] = "ml.sent_by = ?";
        $params[] = $user_id;
    }

    // Status filter
    if (!empty($status) && in_array($status, ['sent', 'failed', 'pending'])) {
        $where[] = "ml.status = ?";
        $params[] = $status;
    }

    // Type filter
    if (!empty($type) && in_array($type, ['manual', 'new_account', 'renewal', 'expiry_reminder'])) {
        $where[] = "ml.message_type = ?";
        $params[] = $type;
    }

    // Search filter
    if (!empty($search)) {
        $where[] = "(ml.recipient_email LIKE ? OR ml.recipient_name LIKE ? OR ml.subject LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countSql = "SELECT COUNT(*) FROM _mail_logs ml WHERE $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get logs
    $sql = "
        SELECT
            ml.id,
            ml.recipient_email,
            ml.recipient_name,
            ml.cc_emails,
            ml.subject,
            ml.message_type,
            ml.status,
            ml.error_message,
            ml.created_at,
            u.username as sent_by_username,
            u.full_name as sent_by_name
        FROM _mail_logs ml
        LEFT JOIN _users u ON ml.sent_by = u.id
        WHERE $whereClause
        ORDER BY ml.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format logs
    foreach ($logs as &$log) {
        $log['cc_emails'] = $log['cc_emails'] ? json_decode($log['cc_emails'], true) : [];
        $log['time'] = date('H:i', strtotime($log['created_at']));
        $log['sent_by'] = $log['sent_by_name'] ?? $log['sent_by_username'] ?? 'Unknown';
    }

    echo json_encode([
        'error' => 0,
        'logs' => $logs,
        'pagination' => [
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
