<?php
/**
 * Push Subscription API (v1.11.40)
 *
 * POST - Subscribe to push notifications
 * DELETE - Unsubscribe from push notifications
 * GET - Check subscription status
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
$pdo = new PDO($dsn, $ub_db_username, $ub_db_password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$userId = $_SESSION['user_id']; // Fixed: was 'userid', should be 'user_id' (v1.11.44)
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Check if user has any subscriptions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM _push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        echo json_encode([
            'error' => 0,
            'subscribed' => $result['count'] > 0,
            'count' => (int)$result['count']
        ]);

    } elseif ($method === 'POST') {
        // Subscribe to push notifications
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['endpoint']) || !isset($input['keys'])) {
            echo json_encode(['error' => 1, 'message' => 'Invalid subscription data']);
            exit();
        }

        $endpoint = $input['endpoint'];
        $p256dh = $input['keys']['p256dh'];
        $auth = $input['keys']['auth'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if subscription already exists
        $stmt = $pdo->prepare("SELECT id FROM _push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);

        if ($stmt->fetch()) {
            // Update existing subscription
            $stmt = $pdo->prepare("UPDATE _push_subscriptions SET user_id = ?, p256dh = ?, auth = ?, user_agent = ? WHERE endpoint = ?");
            $stmt->execute([$userId, $p256dh, $auth, $userAgent, $endpoint]);
        } else {
            // Insert new subscription
            $stmt = $pdo->prepare("INSERT INTO _push_subscriptions (user_id, endpoint, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
        }

        echo json_encode([
            'error' => 0,
            'message' => 'Subscribed to notifications'
        ]);

    } elseif ($method === 'DELETE') {
        // Unsubscribe from push notifications
        $input = json_decode(file_get_contents('php://input'), true);

        if ($input && isset($input['endpoint'])) {
            // Delete specific subscription
            $stmt = $pdo->prepare("DELETE FROM _push_subscriptions WHERE user_id = ? AND endpoint = ?");
            $stmt->execute([$userId, $input['endpoint']]);
        } else {
            // Delete all subscriptions for user
            $stmt = $pdo->prepare("DELETE FROM _push_subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        echo json_encode([
            'error' => 0,
            'message' => 'Unsubscribed from notifications'
        ]);

    } else {
        echo json_encode(['error' => 1, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
