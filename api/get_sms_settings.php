<?php
/**
 * Get SMS Settings
 * Returns SMS configuration for the logged-in user
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

    // Get user ID
    $stmt = $pdo->prepare('SELECT id FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $user_id = $user_data['id'];

    // Get SMS settings
    $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

    // Get SMS templates
    $stmt = $pdo->prepare('SELECT id, name, template, description FROM _sms_templates WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$user_id]);
    $templates = $stmt->fetchAll();

    // Get SMS statistics
    $stmt = $pdo->prepare('SELECT
        COUNT(*) as total_sent,
        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
        FROM _sms_logs WHERE sent_by = ?');
    $stmt->execute([$user_id]);
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
