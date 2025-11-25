<?php
/**
 * WebAuthn Manage - List and delete biometric credentials
 *
 * GET - List all credentials for the logged-in user
 * DELETE - Remove a specific credential
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

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // List all credentials for this user
        $stmt = $pdo->prepare('SELECT id, device_name, created_at, last_used FROM _webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
        $credentials = $stmt->fetchAll();

        echo json_encode([
            'error' => 0,
            'credentials' => $credentials,
            'count' => count($credentials)
        ]);
    }
    else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete a specific credential
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['credential_id'])) {
            echo json_encode(['error' => 1, 'message' => 'Missing credential ID']);
            exit();
        }

        // Make sure the credential belongs to this user
        $stmt = $pdo->prepare('DELETE FROM _webauthn_credentials WHERE id = ? AND user_id = ?');
        $stmt->execute([$input['credential_id'], $user_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'error' => 0,
                'message' => 'Biometric credential removed successfully'
            ]);
        } else {
            echo json_encode([
                'error' => 1,
                'message' => 'Credential not found or does not belong to you'
            ]);
        }
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
