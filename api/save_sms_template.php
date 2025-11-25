<?php
/**
 * Save/Update SMS Template
 * Creates new template or updates existing one
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

    // Get POST data
    $template_id = isset($_POST['template_id']) && $_POST['template_id'] !== '' ? (int)$_POST['template_id'] : null;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $template = isset($_POST['template']) ? trim($_POST['template']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate
    if (empty($name)) {
        echo json_encode(['error' => 1, 'message' => 'Template name is required']);
        exit();
    }

    if (empty($template)) {
        echo json_encode(['error' => 1, 'message' => 'Template message is required']);
        exit();
    }

    if ($template_id) {
        // Update existing template
        $stmt = $pdo->prepare("UPDATE _sms_templates
                              SET name = ?, template = ?, description = ?, updated_at = NOW()
                              WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $template, $description, $template_id, $user_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'error' => 0,
                'message' => 'Template updated successfully',
                'template_id' => $template_id
            ]);
        } else {
            echo json_encode(['error' => 1, 'message' => 'Template not found or no changes made']);
        }
    } else {
        // Create new template
        $stmt = $pdo->prepare("INSERT INTO _sms_templates (user_id, name, template, description, created_at, updated_at)
                              VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$user_id, $name, $template, $description]);

        echo json_encode([
            'error' => 0,
            'message' => 'Template created successfully',
            'template_id' => $pdo->lastInsertId()
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
