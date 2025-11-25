<?php

session_start();

include('config.php');

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
        $response['message'] = 'Permission denied. Only admin and reseller admin can import database.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Check if file was uploaded
    if(!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $response['error'] = 1;
        $response['message'] = 'No file uploaded or upload error occurred';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $uploaded_file = $_FILES['sql_file'];

    // Validate file extension
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    if($file_extension !== 'sql') {
        $response['error'] = 1;
        $response['message'] = 'Invalid file type. Only .sql files are allowed.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Create temp directory if it doesn't exist
    $temp_dir = __DIR__ . '/temp';
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }

    // Move uploaded file to temp directory
    $temp_filepath = $temp_dir . '/import_' . time() . '.sql';
    if (!move_uploaded_file($uploaded_file['tmp_name'], $temp_filepath)) {
        $response['error'] = 1;
        $response['message'] = 'Failed to save uploaded file';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Execute mysql import command with password via environment variable
    // Set MYSQL_PWD environment variable separately
    putenv("MYSQL_PWD=" . $pass);

    $command = sprintf(
        'mysql -h%s -u%s %s < %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($db),
        escapeshellarg($temp_filepath)
    );

    // Log the command for debugging (without password)
    error_log('[Import] Executing mysql command for database: ' . $db);
    error_log('[Import] Temp file: ' . $temp_filepath);
    error_log('[Import] File exists: ' . (file_exists($temp_filepath) ? 'yes' : 'no'));
    error_log('[Import] File size: ' . filesize($temp_filepath) . ' bytes');

    exec($command, $output, $return_code);

    // Clear password from environment
    putenv("MYSQL_PWD");

    // Log the results
    error_log('[Import] Return code: ' . $return_code);
    error_log('[Import] Output: ' . implode("\n", $output));

    // Keep temp file for debugging if import fails
    if ($return_code !== 0) {
        error_log('[Import] Keeping temp file for debugging: ' . $temp_filepath);
    } else {
        // Delete temporary file only on success
        @unlink($temp_filepath);
    }

    if ($return_code !== 0) {
        $response['error'] = 1;
        $response['message'] = 'Database import failed (code ' . $return_code . '): ' . implode("\n", $output);
        if (empty($output)) {
            $response['message'] .= ' [No error output - check if mysql client is installed]';
        }
        error_log('[Import] FAILED: ' . $response['message']);
    } else {
        $response['error'] = 0;
        $response['message'] = 'Database imported successfully. Page will reload shortly.';
        error_log('[Import] SUCCESS');
    }

} catch(PDOException $e) {
    $response['error'] = 1;
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch(Exception $e) {
    $response['error'] = 1;
    $response['message'] = 'Import failed: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

?>
