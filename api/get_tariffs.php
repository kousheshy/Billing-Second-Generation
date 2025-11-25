<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit;
}

include(__DIR__ . '/../config.php');
include('api.php');

header('Content-Type: application/json');

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

    // Check if user is admin or reseller admin
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        echo json_encode(['error' => 1, 'message' => 'User not found.']);
        exit;
    }

    // Check permissions: super_user OR reseller admin (index 2 in permissions string)
    $permissions = explode('|', $user_data['permissions'] ?? '0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if ($user_data['super_user'] != 1 && !$is_reseller_admin) {
        echo json_encode(['error' => 1, 'message' => 'Unauthorized access. Admin or Reseller Admin only.']);
        exit;
    }

    // Get tariffs from Stalker Portal Server 1
    $case = 'tariffs';
    $op = "GET";

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

    $decoded = json_decode($res);

    if (!$decoded || $decoded->status != 'OK') {
        echo json_encode([
            'error' => 1,
            'message' => 'Failed to fetch tariff plans from Stalker Portal'
        ]);
        exit;
    }

    $tariffs = [];

    foreach ($decoded->results as $tariff) {
        $tariffs[] = [
            'id' => $tariff->id ?? '',
            'name' => $tariff->name ?? "Tariff {$tariff->id}",
            'days' => $tariff->days_to_expires ?? 30,
            'description' => $tariff->description ?? ''
        ];
    }

    echo json_encode([
        'error' => 0,
        'tariffs' => $tariffs,
        'count' => count($tariffs)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 1,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

?>
