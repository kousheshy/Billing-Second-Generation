<?php
/**
 * Get SMS Settings
 * Returns SMS configuration for the logged-in user
 * For reseller admins without their own settings, falls back to admin settings
 */

session_start();
include(__DIR__ . '/../config.php');

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

    // Get user info including permissions
    $stmt = $pdo->prepare('SELECT id, super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $user_id = $user_data['id'];
    $is_super_user = $user_data['super_user'] == 1;

    // Check if user is reseller admin
    $permissions = explode('|', $user_data['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Get SMS settings for this user
    $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

    // If reseller admin has no settings OR has empty api_token, fall back to admin settings (super_user=1)
    // This handles cases where reseller has a record but with NULL/empty values
    if ($is_reseller_admin && (!$settings || empty($settings['api_token']))) {
        $stmt = $pdo->prepare('SELECT s.* FROM _sms_settings s JOIN _users u ON s.user_id = u.id WHERE u.super_user = 1 AND s.api_token IS NOT NULL AND s.api_token != "" LIMIT 1');
        $stmt->execute();
        $admin_settings = $stmt->fetch();
        if ($admin_settings) {
            $settings = $admin_settings;
        }
    }

    // Get SMS templates for this user
    $stmt = $pdo->prepare('SELECT id, name, template, description FROM _sms_templates WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$user_id]);
    $templates = $stmt->fetchAll();

    // If reseller admin has no templates, fall back to admin templates
    if (empty($templates) && $is_reseller_admin) {
        $stmt = $pdo->prepare('SELECT t.id, t.name, t.template, t.description FROM _sms_templates t JOIN _users u ON t.user_id = u.id WHERE u.super_user = 1 ORDER BY t.name ASC');
        $stmt->execute();
        $templates = $stmt->fetchAll();
    }

    // Get SMS statistics
    // Super admins and reseller admins see all SMS logs, regular users see only their own
    if ($is_super_user || $is_reseller_admin) {
        $stmt = $pdo->prepare('SELECT
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
            FROM _sms_logs');
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare('SELECT
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
            FROM _sms_logs WHERE sent_by = ?');
        $stmt->execute([$user_id]);
    }
    $stats = $stmt->fetch();

    echo json_encode([
        'error' => 0,
        'settings' => $settings ? $settings : [
            'api_token' => '',
            'sender_number' => '',
            'base_url' => 'https://edge.ippanel.com/v1',
            'auto_send_enabled' => 0,
            'days_before_expiry' => 7,
            'expiry_template' => ''
        ],
        'templates' => $templates,
        'stats' => $stats
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
