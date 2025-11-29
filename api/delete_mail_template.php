<?php
/**
 * Delete Mail Template API
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can delete mail templates (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can delete mail templates.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 1, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$template_id = isset($data['template_id']) ? intval($data['template_id']) : 0;

if (!$template_id) {
    echo json_encode(['error' => 1, 'message' => 'Template ID is required']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id, name FROM _mail_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo json_encode(['error' => 1, 'message' => 'Template not found']);
        exit;
    }

    // Only owner or super admin can delete
    if ($template['user_id'] != $user_id && $_SESSION['role'] !== 'super_admin') {
        echo json_encode(['error' => 1, 'message' => 'Permission denied']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM _mail_templates WHERE id = ?");
    $stmt->execute([$template_id]);

    echo json_encode([
        'error' => 0,
        'message' => 'Template "' . $template['name'] . '" deleted successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
