<?php
/**
 * Send Mail API
 * Send manual emails to single recipient or multiple accounts
 */

session_start();
require_once('config.php');
require_once('mail_helper.php');

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['error' => 1, 'message' => 'Not authenticated']);
    exit;
}

// Only super admin can send manual mail (v1.18.0)
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied. Only super admin can send mail.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 1, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$mode = $data['mode'] ?? 'manual'; // 'manual' or 'accounts'
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');

// Validate required fields
if (empty($subject)) {
    echo json_encode(['error' => 1, 'message' => 'Subject is required']);
    exit;
}

if (empty($message)) {
    echo json_encode(['error' => 1, 'message' => 'Message is required']);
    exit;
}

try {
    $sent_count = 0;
    $failed_count = 0;
    $errors = [];

    if ($mode === 'manual') {
        // Single email mode
        $email = trim($data['email'] ?? '');
        $recipient_name = trim($data['recipient_name'] ?? '');

        if (empty($email)) {
            echo json_encode(['error' => 1, 'message' => 'Email address is required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 1, 'message' => 'Invalid email address']);
            exit;
        }

        // Wrap message in basic HTML if it doesn't look like HTML
        if (strpos($message, '<') === false) {
            $message = '<div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; padding: 20px;">' .
                       nl2br(htmlspecialchars($message)) .
                       '</div>';
        }

        $result = sendEmail($pdo, $email, $subject, $message, [], $user_id, 'manual', null, $recipient_name);

        if ($result) {
            $sent_count = 1;
        } else {
            $failed_count = 1;
            $errors[] = "Failed to send to $email";
        }

    } elseif ($mode === 'accounts') {
        // Multiple accounts mode
        $account_ids = $data['account_ids'] ?? [];

        if (empty($account_ids) || !is_array($account_ids)) {
            echo json_encode(['error' => 1, 'message' => 'No accounts selected']);
            exit;
        }

        // Get accounts with email addresses
        $placeholders = str_repeat('?,', count($account_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, mac, expire_billing_date
            FROM _accounts
            WHERE id IN ($placeholders) AND email IS NOT NULL AND email != ''
        ");
        $stmt->execute($account_ids);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($accounts)) {
            echo json_encode(['error' => 1, 'message' => 'No valid email addresses found for selected accounts']);
            exit;
        }

        foreach ($accounts as $account) {
            // Personalize message for this account
            $personalized_message = personalizeTemplate($message, [
                'name' => $account['full_name'],
                'mac' => $account['mac'],
                'expiry_date' => $account['expire_billing_date']
            ]);

            $personalized_subject = personalizeTemplate($subject, [
                'name' => $account['full_name'],
                'mac' => $account['mac'],
                'expiry_date' => $account['expire_billing_date']
            ]);

            // Wrap in HTML if needed
            if (strpos($personalized_message, '<') === false) {
                $personalized_message = '<div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; padding: 20px;">' .
                                       nl2br(htmlspecialchars($personalized_message)) .
                                       '</div>';
            }

            $result = sendEmail(
                $pdo,
                $account['email'],
                $personalized_subject,
                $personalized_message,
                [],
                $user_id,
                'manual',
                $account['id'],
                $account['full_name']
            );

            if ($result) {
                $sent_count++;
            } else {
                $failed_count++;
                $errors[] = "Failed to send to {$account['email']}";
            }

            // Small delay to avoid overwhelming SMTP server
            usleep(100000); // 100ms
        }
    }

    $response = [
        'error' => 0,
        'message' => "Sent: $sent_count, Failed: $failed_count",
        'sent_count' => $sent_count,
        'failed_count' => $failed_count
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 1, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
