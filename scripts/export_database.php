<?php

session_start();

include(__DIR__ . '/../config.php');

// Check if user is logged in
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['message'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user info to check permissions
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

    // Check if user is admin or reseller admin
    $stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    $isSuperAdmin = ($user_info['super_user'] == 1);

    // Parse permissions to check if reseller has admin-level permissions
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0');
    $isResellerAdmin = isset($permissions[2]) && $permissions[2] === '1';

    if(!$isSuperAdmin && !$isResellerAdmin) {
        $response['error'] = 1;
        $response['message'] = 'Permission denied. Only admin and reseller admin can export database.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Create backup directory if it doesn't exist
    $backup_dir = __DIR__ . '/backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    // Generate filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "showbox_backup_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;

    // Execute mysqldump command with password via environment variable to avoid warnings
    // Set MYSQL_PWD environment variable separately
    putenv("MYSQL_PWD=" . $pass);

    $command = sprintf(
        'mysqldump -h%s -u%s %s --single-transaction --skip-lock-tables --set-gtid-purged=OFF > %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($db),
        escapeshellarg($filepath)
    );

    exec($command, $output, $return_code);

    // Clear password from environment
    putenv("MYSQL_PWD");

    if ($return_code !== 0) {
        $response['error'] = 1;
        $response['message'] = 'Database export failed: ' . implode("\n", $output);
    } else {
        // Check if file was created successfully
        if (file_exists($filepath) && filesize($filepath) > 0) {
            $response['error'] = 0;
            $response['message'] = 'Database exported successfully';
            $response['filename'] = $filename;
            $response['file_url'] = 'scripts/backups/' . $filename;
            $response['file_size'] = filesize($filepath);
        } else {
            $response['error'] = 1;
            $response['message'] = 'Database export failed: File was not created';
        }
    }

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch(Exception $e) {
    $response['error'] = 1;
    $response['message'] = 'Export failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
