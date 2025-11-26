<?php

session_start();

include(__DIR__ . '/../config.php');

/**
 * Log login attempt to _login_history table
 */
function logLoginAttempt($pdo, $user_id, $username, $status, $method = 'password', $failure_reason = null) {
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE '_login_history'");
        if ($tableCheck->rowCount() == 0) {
            return; // Table not created yet, skip logging
        }

        // Get client IP address
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        // If multiple IPs in X-Forwarded-For, take the first one
        if ($ip_address && strpos($ip_address, ',') !== false) {
            $ip_address = trim(explode(',', $ip_address)[0]);
        }

        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare('
            INSERT INTO _login_history (user_id, username, login_time, ip_address, user_agent, login_method, status, failure_reason)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $user_id,
            $username,
            $ip_address,
            $user_agent,
            $method,
            $status,
            $failure_reason
        ]);
    } catch (PDOException $e) {
        // Silently fail - don't break login if logging fails
        error_log("Login history logging failed: " . $e->getMessage());
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));

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

        $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ? AND password = ?');
        $stmt->execute([$username, $password]);

        $count = $stmt->rowCount();

        if($count > 0)
        {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['login'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['super_user'] = $user_data['super_user'];
            $_SESSION['permissions'] = $user_data['permissions'] ?? '';
            $_SESSION['last_activity'] = time(); // Set activity timestamp for auto-logout

            // Log successful login
            logLoginAttempt($pdo, $user_data['id'], $username, 'success', 'password');

            $response['error'] = 0;
            $response['message'] = 'Login successful';
        }
        else
        {
            // Try to get user_id for failed login (if username exists)
            $userStmt = $pdo->prepare('SELECT id FROM _users WHERE username = ?');
            $userStmt->execute([$username]);
            $existingUser = $userStmt->fetch();
            $failed_user_id = $existingUser ? $existingUser['id'] : 0;

            // Log failed login attempt
            logLoginAttempt($pdo, $failed_user_id, $username, 'failed', 'password', 'Invalid credentials');

            $response['error'] = 1;
            $response['message'] = 'Invalid username or password';
        }

    } catch(PDOException $e) {
        $response['error'] = 1;
        $response['message'] = 'Database connection failed: ' . $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}

?>
