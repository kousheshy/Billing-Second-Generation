<?php
/**
 * Save Mail Template API
 * Create or update email templates
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can manage mail templates (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can manage mail templates.']);
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

$template_id = isset($data['template_id']) ? intval($data['template_id']) : null;
$name = trim($data['name'] ?? '');
$subject = trim($data['subject'] ?? '');
$body_html = $data['body_html'] ?? '';
$body_plain = $data['body_plain'] ?? '';
$description = trim($data['description'] ?? '');
$is_active = isset($data['is_active']) ? (intval($data['is_active']) ? 1 : 0) : 1;

// Validate required fields
if (empty($name)) {
    echo json_encode(['error' => 1, 'message' => 'Template name is required']);
    exit;
}

if (empty($subject)) {
    echo json_encode(['error' => 1, 'message' => 'Subject is required']);
    exit;
}

if (empty($body_html)) {
    echo json_encode(['error' => 1, 'message' => 'Email body is required']);
    exit;
}

try {
    if ($template_id) {
        // Update existing template
        // Verify ownership
        $stmt = $pdo->prepare("SELECT user_id FROM _mail_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['error' => 1, 'message' => 'Template not found']);
            exit;
        }

        // Only owner or super admin can edit
        if ($existing['user_id'] != $user_id && $_SESSION['role'] !== 'super_admin') {
            echo json_encode(['error' => 1, 'message' => 'Permission denied']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE _mail_templates SET
                name = ?,
                subject = ?,
                body_html = ?,
                body_plain = ?,
                description = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $subject, $body_html, $body_plain, $description, $is_active, $template_id]);

        echo json_encode([
            'error' => 0,
            'message' => 'Template updated successfully',
            'template_id' => $template_id
        ]);
    } else {
        // Create new template
        $stmt = $pdo->prepare("
            INSERT INTO _mail_templates (user_id, name, subject, body_html, body_plain, description, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $name, $subject, $body_html, $body_plain, $description, $is_active]);

        $new_id = $pdo->lastInsertId();

        echo json_encode([
            'error' => 0,
            'message' => 'Template created successfully',
            'template_id' => $new_id
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
