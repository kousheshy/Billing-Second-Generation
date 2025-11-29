<?php
/**
 * Mail Helper Functions
 * Core email sending functionality using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

/**
 * Get mail settings for a user with admin fallback
 */
function getMailSettings($pdo, $user_id) {
    // Try to get user's own settings first
    $stmt = $pdo->prepare("SELECT * FROM _mail_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user has settings and they are configured (has smtp_username), use them
    if ($settings && !empty($settings['smtp_username'])) {
        return $settings;
    }

    // Fall back to admin settings
    $stmt = $pdo->query("SELECT ms.* FROM _mail_settings ms
                         JOIN _users u ON ms.user_id = u.id
                         WHERE u.super_user = 1
                         ORDER BY ms.id LIMIT 1");
    $adminSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adminSettings) {
        return $adminSettings;
    }

    // Return default settings if nothing found
    return [
        'smtp_host' => 'mail.showboxtv.tv',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => '',
        'from_name' => 'ShowBox',
        'auto_send_new_account' => 1,
        'auto_send_renewal' => 1,
        'auto_send_expiry' => 1,
        'notify_admin' => 1,
        'notify_reseller' => 1
    ];
}

/**
 * Get admin email addresses for CC
 */
function getAdminEmails($pdo) {
    $stmt = $pdo->query("SELECT email FROM _users WHERE role = 'super_admin' AND email IS NOT NULL AND email != ''");
    $emails = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $emails[] = $row['email'];
        }
    }
    return $emails;
}

/**
 * Get reseller email by user_id
 */
function getResellerEmail($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT email FROM _users WHERE id = ? AND email IS NOT NULL AND email != ''");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        return $row['email'];
    }
    return null;
}

/**
 * Log email attempt to database
 */
function logMailAttempt($pdo, $to, $cc, $subject, $body, $type, $sent_by, $status, $error = null, $account_id = null, $recipient_name = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO _mail_logs (account_id, recipient_email, recipient_name, cc_emails, subject, body, message_type, sent_by, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $account_id,
            $to,
            $recipient_name,
            is_array($cc) ? json_encode($cc) : $cc,
            $subject,
            $body,
            $type,
            $sent_by,
            $status,
            $error
        ]);
    } catch (Exception $e) {
        error_log("Failed to log mail attempt: " . $e->getMessage());
    }
}

/**
 * Core email sending function using PHPMailer
 */
function sendEmail($pdo, $to, $subject, $body, $cc = [], $user_id, $type, $account_id = null, $recipient_name = null) {
    $settings = getMailSettings($pdo, $user_id);

    // Check if mail is configured
    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        logMailAttempt($pdo, $to, $cc, $subject, $body, $type, $user_id, 'failed', 'Mail settings not configured', $account_id, $recipient_name);
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->Port = intval($settings['smtp_port']);

        // Set encryption
        if ($settings['smtp_secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Recipients
        $mail->setFrom($settings['from_email'], $settings['from_name']);
        $mail->addAddress($to, $recipient_name ?? '');

        // Add CC recipients
        if (!empty($cc)) {
            foreach ($cc as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();

        // Log success
        logMailAttempt($pdo, $to, $cc, $subject, $body, $type, $user_id, 'sent', null, $account_id, $recipient_name);

        return true;
    } catch (Exception $e) {
        // Log failure
        logMailAttempt($pdo, $to, $cc, $subject, $body, $type, $user_id, 'failed', $mail->ErrorInfo, $account_id, $recipient_name);
        error_log("Mail sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Personalize template with variables
 */
function personalizeTemplate($template, $variables) {
    $search = ['{name}', '{mac}', '{expiry_date}', '{plan_name}', '{username}', '{password}'];
    $replace = [
        $variables['name'] ?? '',
        $variables['mac'] ?? '',
        $variables['expiry_date'] ?? '',
        $variables['plan_name'] ?? '',
        $variables['username'] ?? '',
        $variables['password'] ?? ''
    ];
    return str_replace($search, $replace, $template);
}

/**
 * Get template by name for a user
 */
function getMailTemplate($pdo, $user_id, $template_name) {
    // Try user's template first
    $stmt = $pdo->prepare("SELECT * FROM _mail_templates WHERE user_id = ? AND name = ? AND is_active = 1");
    $stmt->execute([$user_id, $template_name]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($template) {
        return $template;
    }

    // Fall back to admin template
    $stmt = $pdo->prepare("
        SELECT mt.* FROM _mail_templates mt
        JOIN _users u ON mt.user_id = u.id
        WHERE u.super_user = 1 AND mt.name = ? AND mt.is_active = 1
        ORDER BY mt.id LIMIT 1
    ");
    $stmt->execute([$template_name]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Send welcome email for new account
 */
function sendWelcomeMail($pdo, $user_id, $customer_name, $customer_email, $mac, $plan_name, $expiry_date, $account_id, $username = null, $password = null) {
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $settings = getMailSettings($pdo, $user_id);

    // Check if auto-send is enabled
    if (empty($settings['auto_send_new_account'])) {
        return false;
    }

    // Get template
    $template = getMailTemplate($pdo, $user_id, 'New Account Welcome');
    if (!$template) {
        error_log("Welcome mail template not found for user $user_id");
        return false;
    }

    // Personalize template
    $variables = [
        'name' => $customer_name,
        'mac' => $mac,
        'expiry_date' => $expiry_date,
        'plan_name' => $plan_name,
        'username' => $username,
        'password' => $password
    ];

    $subject = personalizeTemplate($template['subject'], $variables);
    $body = personalizeTemplate($template['body_html'], $variables);

    // Build CC list
    $cc = [];
    if (!empty($settings['notify_admin'])) {
        $cc = array_merge($cc, getAdminEmails($pdo));
    }
    if (!empty($settings['notify_reseller'])) {
        $resellerEmail = getResellerEmail($pdo, $user_id);
        if ($resellerEmail && $resellerEmail !== $customer_email) {
            $cc[] = $resellerEmail;
        }
    }

    return sendEmail($pdo, $customer_email, $subject, $body, $cc, $user_id, 'new_account', $account_id, $customer_name);
}

/**
 * Send renewal confirmation email
 */
function sendRenewalMail($pdo, $user_id, $customer_name, $customer_email, $mac, $plan_name, $expiry_date, $account_id) {
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $settings = getMailSettings($pdo, $user_id);

    // Check if auto-send is enabled
    if (empty($settings['auto_send_renewal'])) {
        return false;
    }

    // Get template
    $template = getMailTemplate($pdo, $user_id, 'Renewal Confirmation');
    if (!$template) {
        error_log("Renewal mail template not found for user $user_id");
        return false;
    }

    // Personalize template
    $variables = [
        'name' => $customer_name,
        'mac' => $mac,
        'expiry_date' => $expiry_date,
        'plan_name' => $plan_name
    ];

    $subject = personalizeTemplate($template['subject'], $variables);
    $body = personalizeTemplate($template['body_html'], $variables);

    // Build CC list
    $cc = [];
    if (!empty($settings['notify_admin'])) {
        $cc = array_merge($cc, getAdminEmails($pdo));
    }
    if (!empty($settings['notify_reseller'])) {
        $resellerEmail = getResellerEmail($pdo, $user_id);
        if ($resellerEmail && $resellerEmail !== $customer_email) {
            $cc[] = $resellerEmail;
        }
    }

    return sendEmail($pdo, $customer_email, $subject, $body, $cc, $user_id, 'renewal', $account_id, $customer_name);
}

/**
 * Send expiry reminder email
 */
function sendExpiryMail($pdo, $user_id, $customer_name, $customer_email, $mac, $expiry_date, $account_id, $stage = 1) {
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $settings = getMailSettings($pdo, $user_id);

    // Check if auto-send is enabled
    if (empty($settings['auto_send_expiry'])) {
        return false;
    }

    // Check if already sent at this stage
    $stmt = $pdo->prepare("SELECT id FROM _mail_reminder_tracking WHERE account_id = ? AND reminder_stage = ?");
    $stmt->execute([$account_id, $stage]);
    if ($stmt->fetch()) {
        return false; // Already sent
    }

    // Get template based on stage
    $template_name = $stage == 4 ? 'Account Expired' : 'Expiry Reminder';
    $template = getMailTemplate($pdo, $user_id, $template_name);
    if (!$template) {
        error_log("Expiry mail template '$template_name' not found for user $user_id");
        return false;
    }

    // Personalize template
    $variables = [
        'name' => $customer_name,
        'mac' => $mac,
        'expiry_date' => $expiry_date
    ];

    $subject = personalizeTemplate($template['subject'], $variables);
    $body = personalizeTemplate($template['body_html'], $variables);

    // Build CC list (usually just reseller for expiry reminders)
    $cc = [];
    if (!empty($settings['notify_reseller'])) {
        $resellerEmail = getResellerEmail($pdo, $user_id);
        if ($resellerEmail && $resellerEmail !== $customer_email) {
            $cc[] = $resellerEmail;
        }
    }

    $result = sendEmail($pdo, $customer_email, $subject, $body, $cc, $user_id, 'expiry_reminder', $account_id, $customer_name);

    // Track this reminder to prevent duplicates
    if ($result) {
        try {
            $stmt = $pdo->prepare("INSERT INTO _mail_reminder_tracking (account_id, reminder_stage) VALUES (?, ?)");
            $stmt->execute([$account_id, $stage]);
        } catch (Exception $e) {
            // Ignore duplicate key errors
        }
    }

    return $result;
}

/**
 * Initialize mail settings for a new reseller
 */
function initializeResellerMail($pdo, $user_id) {
    try {
        // Check if settings already exist
        $stmt = $pdo->prepare("SELECT id FROM _mail_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return true; // Already initialized
        }

        // Create default mail settings (empty credentials - will use admin fallback)
        $stmt = $pdo->prepare("
            INSERT INTO _mail_settings (user_id, smtp_host, smtp_port, smtp_secure, from_name)
            VALUES (?, 'mail.showboxtv.tv', 587, 'tls', 'ShowBox')
        ");
        $stmt->execute([$user_id]);

        // Copy default templates from admin
        $stmt = $pdo->query("
            SELECT name, subject, body_html, description FROM _mail_templates mt
            JOIN _users u ON mt.user_id = u.id
            WHERE u.super_user = 1
            ORDER BY mt.id
        ");
        $adminTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($adminTemplates)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO _mail_templates (user_id, name, subject, body_html, description)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($adminTemplates as $template) {
                $insertStmt->execute([
                    $user_id,
                    $template['name'],
                    $template['subject'],
                    $template['body_html'],
                    $template['description']
                ]);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize reseller mail: " . $e->getMessage());
        return false;
    }
}

/**
 * Test SMTP connection
 */
function testMailConnection($settings) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->Port = intval($settings['smtp_port']);

        if ($settings['smtp_secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Test connection
        $smtp = $mail->getSMTPInstance();
        $smtp->setTimeout(10);

        if (!$smtp->connect($settings['smtp_host'], $settings['smtp_port'])) {
            return ['success' => false, 'message' => 'Could not connect to SMTP server'];
        }

        if (!$smtp->hello(gethostname())) {
            return ['success' => false, 'message' => 'HELLO command failed'];
        }

        // Try STARTTLS if using TLS
        if ($settings['smtp_secure'] === 'tls') {
            if (!$smtp->startTLS()) {
                return ['success' => false, 'message' => 'STARTTLS failed'];
            }
            $smtp->hello(gethostname());
        }

        // Try authentication
        if (!$smtp->authenticate($settings['smtp_username'], $settings['smtp_password'])) {
            return ['success' => false, 'message' => 'Authentication failed - check username/password'];
        }

        $smtp->quit();

        return ['success' => true, 'message' => 'Connection successful'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>
