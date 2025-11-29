<?php
/**
 * Test Mail Connection API
 * Tests SMTP connection with provided settings
 */

session_start();
require_once('config.php');
require_once('mail_helper.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can test mail connection (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can test mail connection.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 1, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$settings = [
    'smtp_host' => trim($data['smtp_host'] ?? 'mail.showboxtv.tv'),
    'smtp_port' => intval($data['smtp_port'] ?? 587),
    'smtp_secure' => $data['smtp_secure'] ?? 'tls',
    'smtp_username' => trim($data['smtp_username'] ?? ''),
    'smtp_password' => $data['smtp_password'] ?? ''
];

// Validate required fields
if (empty($settings['smtp_username'])) {
    echo json_encode(['error' => 1, 'message' => 'SMTP username (email) is required']);
    exit;
}

if (empty($settings['smtp_password'])) {
    // Try to get existing password from database
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT smtp_password FROM _mail_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && !empty($existing['smtp_password'])) {
        $settings['smtp_password'] = $existing['smtp_password'];
    } else {
        echo json_encode(['error' => 1, 'message' => 'SMTP password is required']);
        exit;
    }
}

// Test connection
$result = testMailConnection($settings);

if ($result['success']) {
    echo json_encode([
        'error' => 0,
        'message' => 'Connection successful! SMTP server is working correctly.'
    ]);
} else {
    echo json_encode([
        'error' => 1,
        'message' => 'Connection failed: ' . $result['message']
    ]);
}
?>
