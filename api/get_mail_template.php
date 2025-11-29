<?php
/**
 * Get Mail Template API
 * Returns full template details including body
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can access mail templates (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can access mail templates.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$template_id) {
    echo json_encode(['error' => 1, 'message' => 'Template ID is required']);
    exit;
}

try {
    // Get template
    $stmt = $pdo->prepare("SELECT * FROM _mail_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo json_encode(['error' => 1, 'message' => 'Template not found']);
        exit;
    }

    // Check permissions - owner or super admin can view any, others can only view their own or admin's
    $is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;

    if ($template['user_id'] != $user_id && !$is_super_admin) {
        // Check if it's an admin template (fallback templates are viewable)
        $stmt = $pdo->prepare("SELECT super_user FROM _users WHERE id = ?");
        $stmt->execute([$template['user_id']]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$owner || $owner['super_user'] != 1) {
            echo json_encode(['error' => 1, 'message' => 'Permission denied']);
            exit;
        }
    }

    echo json_encode([
        'error' => 0,
        'template' => $template
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
