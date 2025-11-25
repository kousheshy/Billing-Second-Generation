<?php
/**
 * Delete SMS Template
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['error' => 1, 'message' => 'Invalid request method']);
    exit();
}

$username = $_SESSION['username'];

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8mb4';

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

    // Get template ID
    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;

    if ($template_id <= 0) {
        echo json_encode(['error' => 1, 'message' => 'Invalid template ID']);
        exit();
    }

    // Delete template (only if it belongs to this user)
    $stmt = $pdo->prepare("DELETE FROM _sms_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$template_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'error' => 0,
            'message' => 'Template deleted successfully'
        ]);
    } else {
        echo json_encode(['error' => 1, 'message' => 'Template not found or already deleted']);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
