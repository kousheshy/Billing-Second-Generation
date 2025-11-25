<?php

session_start();

include(__DIR__ . '/../config.php');

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
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['login'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time(); // Set activity timestamp for auto-logout

            $response['error'] = 0;
            $response['message'] = 'Login successful';
        }
        else
        {
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
