<?php
/**
 * Update Mail Settings API
 * Save SMTP configuration and auto-send options
 */

session_start();
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can update mail settings (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can update mail settings.']);
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

// Validate required fields
$smtp_host = trim($data['smtp_host'] ?? 'mail.showboxtv.tv');
$smtp_port = intval($data['smtp_port'] ?? 587);
$smtp_secure = $data['smtp_secure'] ?? 'tls';
$smtp_username = trim($data['smtp_username'] ?? '');
$smtp_password = $data['smtp_password'] ?? '';
$from_email = trim($data['from_email'] ?? '');
$from_name = trim($data['from_name'] ?? 'ShowBox');

// Auto-send options
$auto_send_new_account = isset($data['auto_send_new_account']) ? (intval($data['auto_send_new_account']) ? 1 : 0) : 1;
$auto_send_renewal = isset($data['auto_send_renewal']) ? (intval($data['auto_send_renewal']) ? 1 : 0) : 1;
$auto_send_expiry = isset($data['auto_send_expiry']) ? (intval($data['auto_send_expiry']) ? 1 : 0) : 1;
$notify_admin = isset($data['notify_admin']) ? (intval($data['notify_admin']) ? 1 : 0) : 1;
$notify_reseller = isset($data['notify_reseller']) ? (intval($data['notify_reseller']) ? 1 : 0) : 1;
$days_before_expiry = intval($data['days_before_expiry'] ?? 7);
$enable_multistage = isset($data['enable_multistage_reminders']) ? (intval($data['enable_multistage_reminders']) ? 1 : 0) : 1;

// Validate smtp_secure
if (!in_array($smtp_secure, ['tls', 'ssl', 'none'])) {
    $smtp_secure = 'tls';
}

// Validate port
if ($smtp_port < 1 || $smtp_port > 65535) {
    $smtp_port = 587;
}

// Validate days_before_expiry
if ($days_before_expiry < 1 || $days_before_expiry > 30) {
    $days_before_expiry = 7;
}

try {
    // Check if settings exist
    $stmt = $pdo->prepare("SELECT id, smtp_password FROM _mail_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // If password is empty and settings exist, keep existing password
    if (empty($smtp_password) && $existing) {
        $smtp_password = $existing['smtp_password'];
    }

    if ($existing) {
        // Update existing settings
        $stmt = $pdo->prepare("
            UPDATE _mail_settings SET
                smtp_host = ?,
                smtp_port = ?,
                smtp_secure = ?,
                smtp_username = ?,
                smtp_password = ?,
                from_email = ?,
                from_name = ?,
                auto_send_new_account = ?,
                auto_send_renewal = ?,
                auto_send_expiry = ?,
                notify_admin = ?,
                notify_reseller = ?,
                days_before_expiry = ?,
                enable_multistage_reminders = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $smtp_host,
            $smtp_port,
            $smtp_secure,
            $smtp_username,
            $smtp_password,
            $from_email,
            $from_name,
            $auto_send_new_account,
            $auto_send_renewal,
            $auto_send_expiry,
            $notify_admin,
            $notify_reseller,
            $days_before_expiry,
            $enable_multistage,
            $user_id
        ]);
    } else {
        // Insert new settings
        $stmt = $pdo->prepare("
            INSERT INTO _mail_settings (
                user_id, smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password,
                from_email, from_name, auto_send_new_account, auto_send_renewal, auto_send_expiry,
                notify_admin, notify_reseller, days_before_expiry, enable_multistage_reminders
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $smtp_host,
            $smtp_port,
            $smtp_secure,
            $smtp_username,
            $smtp_password,
            $from_email,
            $from_name,
            $auto_send_new_account,
            $auto_send_renewal,
            $auto_send_expiry,
            $notify_admin,
            $notify_reseller,
            $days_before_expiry,
            $enable_multistage
        ]);
    }

    echo json_encode([
        'error' => 0,
        'message' => 'Mail settings saved successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
