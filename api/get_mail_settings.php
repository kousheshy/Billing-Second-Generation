<?php
/**
 * Get Mail Settings API
 * Returns mail settings, templates, and statistics for current user
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can access mail settings (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can access mail settings.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'reseller';

try {
    // Get user's mail settings
    $stmt = $pdo->prepare("SELECT * FROM _mail_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no settings, get defaults (don't expose admin password)
    if (!$settings) {
        $settings = [
            'id' => null,
            'user_id' => $user_id,
            'smtp_host' => 'mail.showboxtv.tv',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'from_email' => '',
            'from_name' => 'ShowBox',
            'auto_send_new_account' => 1,
            'auto_send_renewal' => 1,
            'auto_send_expiry' => 1,
            'notify_admin' => 1,
            'notify_reseller' => 1,
            'days_before_expiry' => 7,
            'enable_multistage_reminders' => 1
        ];
    } else {
        // Mask password for security (show asterisks if set)
        $settings['smtp_password_set'] = !empty($settings['smtp_password']);
        $settings['smtp_password'] = ''; // Don't send actual password
    }

    // Get templates for this user
    $stmt = $pdo->prepare("SELECT id, name, subject, description, is_active, created_at, updated_at FROM _mail_templates WHERE user_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no templates, check if admin templates exist (for reference)
    if (empty($templates)) {
        $stmt = $pdo->query("
            SELECT id, name, subject, description, is_active FROM _mail_templates mt
            JOIN _users u ON mt.user_id = u.id
            WHERE u.super_user = 1
            ORDER BY mt.name
        ");
        $adminTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($adminTemplates)) {
            $settings['using_admin_templates'] = true;
            $templates = $adminTemplates;
        }
    }

    // Get statistics
    $stats = [
        'total' => 0,
        'sent' => 0,
        'failed' => 0,
        'pending' => 0
    ];

    // Build query based on role
    if ($role === 'super_admin') {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count FROM _mail_logs GROUP BY status
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count FROM _mail_logs WHERE sent_by = ? GROUP BY status
        ");
        $stmt->execute([$user_id]);
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = intval($row['count']);
        $stats['total'] += intval($row['count']);
    }

    echo json_encode([
        'error' => 0,
        'settings' => $settings,
        'templates' => $templates,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
