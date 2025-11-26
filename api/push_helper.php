<?php
/**
 * Push Notification Helper (v1.11.47)
 *
 * Uses minishlink/web-push library for proper Web Push support
 * Handles notifications for admin alerts when ANY user adds/renews accounts
 * Both super admins and reseller admins receive all notifications
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// VAPID Keys - Generated and validated v1.11.46
define('VAPID_PUBLIC_KEY', 'BI8Gdm9PK3LeO2mvhV9yt5NzIBFhSrlKRbfHbaDFfvMqJGmI0T0R-huUK7yeo6aPoasqBnu7SLjNUjqb4J_j5L0');
define('VAPID_PRIVATE_KEY', '3L3e1dOnku3pE746ek4IkAdscb4MU7W6rJVtKm4CuCk');
define('VAPID_SUBJECT', 'https://billing.apamehnet.com');

/**
 * Send push notification to a subscription
 *
 * @param array $subscription - Contains endpoint, p256dh, auth
 * @param array $payload - Notification data (title, body, icon, etc.)
 * @return bool - Success or failure
 */
function sendPushNotification($subscription, $payload) {
    try {
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        $webPush = new WebPush($auth);

        $sub = Subscription::create([
            'endpoint' => $subscription['endpoint'],
            'publicKey' => $subscription['p256dh'],
            'authToken' => $subscription['auth'],
        ]);

        $report = $webPush->sendOneNotification(
            $sub,
            json_encode($payload)
        );

        if ($report->isSuccess()) {
            error_log('[Push] Notification sent successfully to: ' . substr($subscription['endpoint'], 0, 50));
            return true;
        } else {
            error_log('[Push] Notification failed: ' . $report->getReason());
            return false;
        }

    } catch (Exception $e) {
        error_log('[Push] Exception sending notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to all admins and reseller admins
 *
 * @param PDO $pdo - Database connection
 * @param string $title - Notification title
 * @param string $body - Notification body
 * @param array $data - Additional data
 */
function notifyAdmins($pdo, $title, $body, $data = []) {
    try {
        // Get all admin and reseller admin subscriptions
        // Permissions format: can_edit|can_add|is_reseller_admin|can_delete|reserved
        // Position 3 (1-indexed) is is_reseller_admin flag
        $stmt = $pdo->prepare("
            SELECT ps.*, u.username, u.super_user, u.permissions
            FROM _push_subscriptions ps
            JOIN _users u ON ps.user_id = u.id
            WHERE u.super_user = 1
               OR SUBSTRING_INDEX(SUBSTRING_INDEX(u.permissions, '|', 3), '|', -1) = '1'
        ");
        $stmt->execute();
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("[Push] Found " . count($subscriptions) . " admin subscriptions");

        if (count($subscriptions) === 0) {
            error_log("[Push] No admin subscriptions found");
            return ['sent' => 0, 'failed' => 0];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-72x72.png',
            'data' => $data,
            'timestamp' => time() * 1000
        ];

        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $sub) {
            error_log("[Push] Sending to user {$sub['username']} (ID: {$sub['user_id']})");

            $success = sendPushNotification([
                'endpoint' => $sub['endpoint'],
                'p256dh' => $sub['p256dh'],
                'auth' => $sub['auth']
            ], $payload);

            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        error_log("[Push] Notified admins: sent=$sent, failed=$failed");
        return ['sent' => $sent, 'failed' => $failed];

    } catch (Exception $e) {
        error_log('[Push] Error notifying admins: ' . $e->getMessage());
        return ['sent' => 0, 'failed' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Notify about new account creation
 * @param string $actorName - Name of user who created the account (admin, reseller admin, or reseller)
 */
function notifyNewAccount($pdo, $actorName, $accountName, $planName) {
    $title = '📱 New Account Created';
    $body = "$actorName added: $accountName ($planName)";

    return notifyAdmins($pdo, $title, $body, [
        'type' => 'new_account',
        'actor' => $actorName,
        'account' => $accountName,
        'plan' => $planName,
        'url' => '/dashboard.php?tab=accounts'
    ]);
}

/**
 * Notify about account renewal
 * @param string $actorName - Name of user who renewed the account (admin, reseller admin, or reseller)
 */
function notifyAccountRenewal($pdo, $actorName, $accountName, $planName, $newExpiry) {
    $title = '🔄 Account Renewed';
    $body = "$actorName renewed: $accountName ($planName) until $newExpiry";

    return notifyAdmins($pdo, $title, $body, [
        'type' => 'renewal',
        'actor' => $actorName,
        'account' => $accountName,
        'plan' => $planName,
        'expiry' => $newExpiry,
        'url' => '/dashboard.php?tab=accounts'
    ]);
}
?>
