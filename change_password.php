<?php
// Change Password API (v1.10.1)
session_start();
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$dbname = 'showbox_billing';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
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
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
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
$updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
$success = $updateStmt->execute([$hashedNewPassword, $user['id']]);

if ($success) {
    echo json_encode(['error' => 0, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['error' => 1, 'message' => 'Failed to update password']);
}
?>
