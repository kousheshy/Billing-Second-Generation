<?php
/**
 * Server 2 Failure Notification Helper
 * Version: 1.18.0
 *
 * Notifies admins when Server 2 (secondary Stalker Portal server) operations fail.
 * Server 1 remains the primary source of truth - these are warning notifications only.
 *
 * Notification Methods:
 * 1. Response Warning - Adds warning to API response
 * 2. Push Notification - Sends push notification to admins
 * 3. Audit Log - Records in audit log
 * 4. Email - Sends email to admin(s)
 */

// Include dependencies if not already included
if (!function_exists('notifyAdmins')) {
    require_once __DIR__ . '/push_helper.php';
}
if (!function_exists('logAuditEvent')) {
    require_once __DIR__ . '/audit_helper.php';
}

/**
 * Notify admins about Server 2 failure
 *
 * @param PDO $pdo - Database connection
 * @param string $operation - What operation failed (add, update, delete, message, status)
 * @param string $identifier - MAC address or username affected
 * @param string $error_message - Error message from Server 2
 * @param array &$response - Reference to response array (for adding warning)
 * @return array - Notification results
 */
function notifyServer2Failure($pdo, $operation, $identifier, $error_message, &$response = null) {
    $results = [
        'response_warning' => false,
        'push' => false,
        'audit' => false,
        'email' => false
    ];

    $timestamp = date('Y-m-d H:i:s');
    $short_error = strlen($error_message) > 100 ? substr($error_message, 0, 100) . '...' : $error_message;

    // 1. Add warning to response
    if ($response !== null) {
        $response['server2_warning'] = true;
        $response['server2_error'] = "Server 2 $operation failed for $identifier: $short_error";
        $results['response_warning'] = true;
    }

    // 2. Send Push Notification to admins (DISABLED - causes response timeout due to HTTP requests)
    // TODO: Re-enable with async/background processing
    // try {
    //     $push_result = notifyServer2FailurePush($pdo, $operation, $identifier, $short_error);
    //     $results['push'] = ($push_result['sent'] > 0);
    // } catch (Exception $e) {
    //     error_log("[Server2-Notify] Push failed: " . $e->getMessage());
    // }
    $results['push'] = false;

    // 3. Log to Audit Log
    try {
        $audit_result = logServer2Failure($pdo, $operation, $identifier, $error_message);
        $results['audit'] = $audit_result;
    } catch (Exception $e) {
        error_log("[Server2-Notify] Audit log failed: " . $e->getMessage());
    }

    // 4. Send Email to admins (DISABLED - email not configured)
    // Uncomment when email is configured:
    // try {
    //     $email_result = emailServer2Failure($pdo, $operation, $identifier, $error_message, $timestamp);
    //     $results['email'] = $email_result;
    // } catch (Exception $e) {
    //     error_log("[Server2-Notify] Email failed: " . $e->getMessage());
    // }
    $results['email'] = false;

    // Log summary
    error_log("[Server2-Notify] Notification results for $operation on $identifier: " . json_encode($results));

    return $results;
}

/**
 * Send push notification for Server 2 failure
 */
function notifyServer2FailurePush($pdo, $operation, $identifier, $error_message) {
    $title = '⚠️ Server 2 Sync Failed';
    $body = ucfirst($operation) . " failed on Server 2 for: $identifier";

    return notifyAdmins($pdo, $title, $body, [
        'type' => 'server2_failure',
        'operation' => $operation,
        'identifier' => $identifier,
        'error' => $error_message,
        'url' => '/dashboard.php?tab=settings'
    ]);
}

/**
 * Log Server 2 failure to audit log
 */
function logServer2Failure($pdo, $operation, $identifier, $error_message) {
    return logAuditEvent(
        $pdo,
        'server2_failure',
        'stalker_portal',
        null,
        $identifier,
        null,
        [
            'operation' => $operation,
            'error' => $error_message,
            'server' => 'Server 2 (secondary)'
        ],
        "Server 2 $operation failed for $identifier: $error_message"
    );
}

/**
 * Send email to admins about Server 2 failure
 */
function emailServer2Failure($pdo, $operation, $identifier, $error_message, $timestamp) {
    global $PANEL_NAME;

    try {
        // Get all admin email addresses
        $stmt = $pdo->prepare('SELECT email, name FROM _users WHERE super_user = 1 AND email IS NOT NULL AND email != ""');
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("[Server2-Email] No admin emails found");
            return false;
        }

        $panel_name = $PANEL_NAME ?? 'ShowBox';
        $subject = "⚠️ [$panel_name] Server 2 Sync Failed - Action Required";

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert { background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
                .details { background-color: #f8f9fa; border-radius: 5px; padding: 15px; }
                .label { font-weight: bold; color: #495057; }
                .error { color: #dc3545; font-family: monospace; background: #f8d7da; padding: 10px; border-radius: 3px; }
                .note { color: #6c757d; font-size: 0.9em; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='alert'>
                    <strong>⚠️ Server 2 Synchronization Failed</strong>
                    <p>A secondary server operation failed. Server 1 (primary) operation was successful.</p>
                </div>

                <div class='details'>
                    <p><span class='label'>Operation:</span> " . ucfirst($operation) . "</p>
                    <p><span class='label'>Identifier:</span> $identifier</p>
                    <p><span class='label'>Timestamp:</span> $timestamp</p>
                    <p><span class='label'>Error:</span></p>
                    <div class='error'>$error_message</div>
                </div>

                <p class='note'>
                    <strong>Note:</strong> Server 1 operation succeeded. You may need to manually sync Server 2
                    or investigate the connection. Check the Stalker Portal settings in your admin panel.
                </p>

                <p style='color: #6c757d; font-size: 0.8em; margin-top: 30px;'>
                    This is an automated notification from $panel_name Billing System.
                </p>
            </div>
        </body>
        </html>
        ";

        // Send to all admins
        $sent = false;
        foreach ($admins as $admin) {
            if (!empty($admin['email'])) {
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: $panel_name <noreply@showboxtv.tv>\r\n";
                $headers .= "Reply-To: noreply@showboxtv.tv\r\n";

                if (mail($admin['email'], $subject, $body, $headers)) {
                    $sent = true;
                    error_log("[Server2-Email] Sent to {$admin['email']}");
                } else {
                    error_log("[Server2-Email] Failed to send to {$admin['email']}");
                }
            }
        }

        return $sent;

    } catch (Exception $e) {
        error_log("[Server2-Email] Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Quick helper for common Server 2 failure scenarios
 */
function handleServer2Failure($pdo, $operation, $identifier, $decoded_response, &$response = null) {
    $error_message = 'Unknown error';

    if ($decoded_response === null) {
        $error_message = 'Connection failed or invalid response';
    } elseif (isset($decoded_response->error)) {
        $error_message = $decoded_response->error;
    } elseif (isset($decoded_response->message)) {
        $error_message = $decoded_response->message;
    }

    return notifyServer2Failure($pdo, $operation, $identifier, $error_message, $response);
}
?>
