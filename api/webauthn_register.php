<?php
/**
 * WebAuthn Register - Register biometric credentials for a user
 *
 * Two endpoints:
 * 1. GET - Returns challenge for registration
 * 2. POST - Stores the credential after successful registration
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

    // Ensure _webauthn_credentials table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS _webauthn_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id TEXT NOT NULL,
        public_key TEXT NOT NULL,
        counter INT DEFAULT 0,
        device_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used TIMESTAMP NULL,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Get user ID
    $stmt = $pdo->prepare('SELECT id, name FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $user_id = $user_data['id'];
    $user_name = $user_data['name'] ?: $username;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Generate challenge for registration
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);

        // Relying Party info (your website)
        $rpId = $_SERVER['HTTP_HOST'];
        // Remove port if present for rpId
        $rpId = preg_replace('/:\d+$/', '', $rpId);

        echo json_encode([
            'error' => 0,
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => 'ShowBox Billing',
                'id' => $rpId
            ],
            'user' => [
                'id' => base64_encode($user_id . '_' . $username),
                'name' => $username,
                'displayName' => $user_name
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257] // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform', // Use device's built-in authenticator (Face ID, Touch ID)
                'userVerification' => 'required',
                'residentKey' => 'preferred'
            ],
            'timeout' => 60000,
            'attestation' => 'none'
        ]);
    }
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Store credential
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['credential_id']) || !isset($input['public_key'])) {
            echo json_encode(['error' => 1, 'message' => 'Missing credential data']);
            exit();
        }

        // Verify challenge
        if (!isset($_SESSION['webauthn_challenge'])) {
            echo json_encode(['error' => 1, 'message' => 'No challenge found. Please try again.']);
            exit();
        }

        // Check if credential already exists for this user
        $stmt = $pdo->prepare('SELECT id FROM _webauthn_credentials WHERE user_id = ? AND credential_id = ?');
        $stmt->execute([$user_id, $input['credential_id']]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 1, 'message' => 'This biometric is already registered']);
            exit();
        }

        // Store the credential
        $device_name = $input['device_name'] ?? 'Unknown Device';
        $stmt = $pdo->prepare('INSERT INTO _webauthn_credentials (user_id, credential_id, public_key, device_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user_id, $input['credential_id'], $input['public_key'], $device_name]);

        // Clear the challenge
        unset($_SESSION['webauthn_challenge']);

        echo json_encode([
            'error' => 0,
            'message' => 'Biometric registered successfully'
        ]);
    }

} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
