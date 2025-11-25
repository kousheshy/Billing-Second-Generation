<?php
// Change Password API (v1.10.1)
session_start();
header('Content-Type: application/json');

// Include config for database connection
include(__DIR__ . '/../config.php');

// Database connection using config variables
try {
    $pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4", $ub_db_username, $ub_db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database connection failed']);
    exit;
}

// Get parameters
$username = $_POST['username'] ?? '';
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';

// Validation
if (empty($username) || empty($currentPassword) || empty($newPassword)) {
    echo json_encode(['error' => 1, 'message' => 'All fields are required']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['error' => 1, 'message' => 'New password must be at least 6 characters']);
    exit;
}

// Verify current password
$stmt = $pdo->prepare("SELECT id, password FROM _users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 1, 'message' => 'User not found']);
    exit;
}

// Check if current password matches
if (!password_verify($currentPassword, $user['password'])) {
    echo json_encode(['error' => 1, 'message' => 'Current password is incorrect']);
    exit;
}

// Hash new password
$hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password
$updateStmt = $pdo->prepare("UPDATE _users SET password = ? WHERE id = ?");
$success = $updateStmt->execute([$hashedNewPassword, $user['id']]);

if ($success) {
    echo json_encode(['error' => 0, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['error' => 1, 'message' => 'Failed to update password']);
}
?>
