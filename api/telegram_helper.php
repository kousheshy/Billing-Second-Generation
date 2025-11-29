<?php
/**
 * Telegram Helper Functions
 * Provides Telegram Bot API integration for notifications
 * Version: 1.18.0
 *
 * Access Control:
 * - Only super_admin and reseller_admin have access to Telegram features
 * - Resellers receive notifications only for their own accounts
 * - Reseller admins receive notifications for ALL accounts
 */

/**
 * Get Telegram bot settings
 */
function getTelegramSettings($pdo) {
    try {
        $stmt = $pdo->query('SELECT * FROM _telegram_settings LIMIT 1');
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send message via Telegram Bot API
 *
 * @param string $bot_token - The bot token
 * @param int $chat_id - The recipient's chat ID
 * @param string $message - The message text (supports Markdown)
 * @param array $options - Additional options (parse_mode, reply_markup, etc.)
 * @return array - Response with success status and data/error
 */
function sendTelegramMessage($bot_token, $chat_id, $message, $options = []) {
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $payload = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $options['parse_mode'] ?? 'Markdown',
        'disable_web_page_preview' => $options['disable_preview'] ?? true
    ];

    // Add inline keyboard if provided
    if (isset($options['reply_markup'])) {
        $payload['reply_markup'] = json_encode($options['reply_markup']);
    }

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => $curl_error];
    }

    $result = json_decode($response, true);

    if ($http_code == 200 && isset($result['ok']) && $result['ok']) {
        return [
            'success' => true,
            'message_id' => $result['result']['message_id'] ?? null,
            'data' => $result['result']
        ];
    }

    return [
        'success' => false,
        'error' => $result['description'] ?? 'Unknown error',
        'error_code' => $result['error_code'] ?? null
    ];
}

/**
 * Verify bot token by calling getMe
 *
 * @param string $bot_token - The bot token to verify
 * @return array - Bot info if valid, error otherwise
 */
function verifyTelegramBot($bot_token) {
    $api_url = "https://api.telegram.org/bot{$bot_token}/getMe";

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => $curl_error];
    }

    $result = json_decode($response, true);

    if ($http_code == 200 && isset($result['ok']) && $result['ok']) {
        return [
            'success' => true,
            'bot_id' => $result['result']['id'],
            'bot_username' => $result['result']['username'],
            'bot_name' => $result['result']['first_name']
        ];
    }

    return [
        'success' => false,
        'error' => $result['description'] ?? 'Invalid bot token'
    ];
}

/**
 * Get template by key and replace variables
 */
function getTelegramTemplate($pdo, $template_key, $variables = []) {
    try {
        $stmt = $pdo->prepare('SELECT template FROM _telegram_templates WHERE template_key = ?');
        $stmt->execute([$template_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $message = $row['template'];

        // Replace variables
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', $value ?? '', $message);
        }

        return $message;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Log Telegram message to database
 */
function logTelegramMessage($pdo, $user_id, $chat_id, $message, $message_type, $status, $error = null, $message_id = null, $account_id = null, $account_mac = null) {
    try {
        $sql = "INSERT INTO _telegram_logs
                (user_id, chat_id, message, message_type, related_account_id, related_account_mac, telegram_message_id, sent_at, status, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $chat_id,
            $message,
            $message_type,
            $account_id,
            $account_mac,
            $message_id,
            $status,
            $error
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Telegram log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get users who should receive notification for an account
 *
 * @param PDO $pdo
 * @param string $reseller_username - The reseller who owns the account
 * @param string $notification_type - Type of notification (notify_new_account, notify_renewal, etc.)
 * @return array - List of users with their chat_ids
 */
function getTelegramNotificationRecipients($pdo, $reseller_username, $notification_type) {
    $recipients = [];

    try {
        // Get the reseller's info (the owner of the account)
        $stmt = $pdo->prepare('SELECT id, username, telegram_chat_id, permissions FROM _users WHERE username = ?');
        $stmt->execute([$reseller_username]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Get super admins who have this notification enabled and have telegram linked
        $sql = "SELECT u.id, u.username, u.telegram_chat_id
                FROM _users u
                LEFT JOIN _telegram_notification_settings tns ON u.id = tns.user_id
                WHERE u.super_user = 1
                AND u.telegram_chat_id IS NOT NULL
                AND (tns.{$notification_type} = 1 OR tns.id IS NULL)";
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipients[$row['id']] = $row;
        }

        // 2. Get reseller admins (they see ALL accounts) who have telegram linked
        // Check permissions field index 2 for is_reseller_admin
        $stmt = $pdo->query("SELECT u.id, u.username, u.telegram_chat_id, u.permissions
                FROM _users u
                LEFT JOIN _telegram_notification_settings tns ON u.id = tns.user_id
                WHERE u.super_user = 0
                AND u.telegram_chat_id IS NOT NULL
                AND (tns.{$notification_type} = 1 OR tns.id IS NULL)");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $perms = explode('|', $row['permissions'] ?? '0|0|0|0|0|0');
            $is_reseller_admin = isset($perms[2]) && $perms[2] === '1';
            if ($is_reseller_admin) {
                $recipients[$row['id']] = $row;
            }
        }

        // 3. IMPORTANT: Also notify the reseller who owns the account (if they have telegram linked)
        if ($reseller && !empty($reseller['telegram_chat_id'])) {
            // Check if reseller has this notification enabled
            $stmt = $pdo->prepare("SELECT {$notification_type} as enabled FROM _telegram_notification_settings WHERE user_id = ?");
            $stmt->execute([$reseller['id']]);
            $reseller_prefs = $stmt->fetch(PDO::FETCH_ASSOC);

            // Default to enabled if no settings exist
            $notification_enabled = !$reseller_prefs || $reseller_prefs['enabled'] == 1;

            if ($notification_enabled && !isset($recipients[$reseller['id']])) {
                $recipients[$reseller['id']] = [
                    'id' => $reseller['id'],
                    'username' => $reseller['username'],
                    'telegram_chat_id' => $reseller['telegram_chat_id']
                ];
            }
        }

        return array_values($recipients);

    } catch (Exception $e) {
        error_log("Error getting telegram recipients: " . $e->getMessage());
        return [];
    }
}

/**
 * Send notification for new account creation
 */
function sendTelegramNewAccountNotification($pdo, $account_data, $reseller_username) {
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        return false;
    }

    $recipients = getTelegramNotificationRecipients($pdo, $reseller_username, 'notify_new_account');
    if (empty($recipients)) {
        return false;
    }

    $variables = [
        'name' => $account_data['full_name'] ?? $account_data['username'] ?? 'N/A',
        'mac' => $account_data['mac'] ?? 'N/A',
        'expiry_date' => $account_data['end_date'] ?? 'N/A',
        'plan_name' => $account_data['plan_name'] ?? 'N/A',
        'reseller_name' => $reseller_username
    ];

    $message = getTelegramTemplate($pdo, 'new_account', $variables);
    if (!$message) {
        return false;
    }

    $success_count = 0;
    foreach ($recipients as $recipient) {
        $result = sendTelegramMessage($settings['bot_token'], $recipient['telegram_chat_id'], $message);

        logTelegramMessage(
            $pdo,
            $recipient['id'],
            $recipient['telegram_chat_id'],
            $message,
            'new_account',
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $result['message_id'] ?? null,
            $account_data['id'] ?? null,
            $account_data['mac'] ?? null
        );

        if ($result['success']) {
            $success_count++;
        }
    }

    return $success_count > 0;
}

/**
 * Send notification for account renewal
 */
function sendTelegramRenewalNotification($pdo, $account_data, $reseller_username) {
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        return false;
    }

    $recipients = getTelegramNotificationRecipients($pdo, $reseller_username, 'notify_renewal');
    if (empty($recipients)) {
        return false;
    }

    $variables = [
        'name' => $account_data['full_name'] ?? $account_data['username'] ?? 'N/A',
        'mac' => $account_data['mac'] ?? 'N/A',
        'expiry_date' => $account_data['end_date'] ?? 'N/A',
        'plan_name' => $account_data['plan_name'] ?? 'N/A',
        'reseller_name' => $reseller_username
    ];

    $message = getTelegramTemplate($pdo, 'renewal', $variables);
    if (!$message) {
        return false;
    }

    $success_count = 0;
    foreach ($recipients as $recipient) {
        $result = sendTelegramMessage($settings['bot_token'], $recipient['telegram_chat_id'], $message);

        logTelegramMessage(
            $pdo,
            $recipient['id'],
            $recipient['telegram_chat_id'],
            $message,
            'renewal',
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $result['message_id'] ?? null,
            $account_data['id'] ?? null,
            $account_data['mac'] ?? null
        );

        if ($result['success']) {
            $success_count++;
        }
    }

    return $success_count > 0;
}

/**
 * Send notification for new reseller payment
 */
function sendTelegramPaymentNotification($pdo, $payment_data) {
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        return false;
    }

    // Get all super admins and reseller admins with telegram linked
    $sql = "SELECT u.id, u.username, u.telegram_chat_id
            FROM _users u
            LEFT JOIN _telegram_notification_settings tns ON u.id = tns.user_id
            WHERE (u.super_user = 1 OR u.is_reseller_admin = 1)
            AND u.telegram_chat_id IS NOT NULL
            AND (tns.notify_new_payment = 1 OR tns.id IS NULL)";

    try {
        $stmt = $pdo->query($sql);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }

    if (empty($recipients)) {
        return false;
    }

    $variables = [
        'reseller_name' => $payment_data['reseller_name'] ?? 'N/A',
        'amount' => number_format($payment_data['amount'] ?? 0),
        'currency' => $payment_data['currency'] ?? '',
        'bank_name' => $payment_data['bank_name'] ?? 'N/A',
        'description' => $payment_data['description'] ?? '',
        'new_balance' => number_format($payment_data['new_balance'] ?? 0)
    ];

    $message = getTelegramTemplate($pdo, 'new_payment', $variables);
    if (!$message) {
        return false;
    }

    $success_count = 0;
    foreach ($recipients as $recipient) {
        $result = sendTelegramMessage($settings['bot_token'], $recipient['telegram_chat_id'], $message);

        logTelegramMessage(
            $pdo,
            $recipient['id'],
            $recipient['telegram_chat_id'],
            $message,
            'new_payment',
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $result['message_id'] ?? null
        );

        if ($result['success']) {
            $success_count++;
        }
    }

    return $success_count > 0;
}

/**
 * Send low balance warning to reseller (if they have telegram linked)
 * This is triggered when balance falls below threshold after a transaction
 */
function sendTelegramLowBalanceWarning($pdo, $user_id, $balance, $currency, $threshold_accounts = 5) {
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        return false;
    }

    // Get user's telegram chat_id
    $stmt = $pdo->prepare('SELECT telegram_chat_id FROM _users WHERE id = ? AND telegram_chat_id IS NOT NULL');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // Check if notification is enabled
    $stmt = $pdo->prepare('SELECT notify_low_balance FROM _telegram_notification_settings WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prefs && $prefs['notify_low_balance'] == 0) {
        return false;
    }

    $variables = [
        'balance' => number_format($balance),
        'currency' => $currency,
        'accounts_possible' => $threshold_accounts
    ];

    $message = getTelegramTemplate($pdo, 'low_balance', $variables);
    if (!$message) {
        return false;
    }

    $result = sendTelegramMessage($settings['bot_token'], $user['telegram_chat_id'], $message);

    logTelegramMessage(
        $pdo,
        $user_id,
        $user['telegram_chat_id'],
        $message,
        'low_balance',
        $result['success'] ? 'sent' : 'failed',
        $result['error'] ?? null,
        $result['message_id'] ?? null
    );

    return $result['success'];
}

/**
 * Send broadcast message to selected users
 */
function sendTelegramBroadcast($pdo, $user_ids, $message, $sent_by_user_id) {
    $settings = getTelegramSettings($pdo);
    if (!$settings || empty($settings['bot_token'])) {
        return ['success' => false, 'error' => 'Telegram bot not configured'];
    }

    // Get users with telegram linked
    $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
    $sql = "SELECT id, username, telegram_chat_id FROM _users WHERE id IN ($placeholders) AND telegram_chat_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($user_ids);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No recipients have Telegram linked'];
    }

    // Format with broadcast template
    $formatted_message = getTelegramTemplate($pdo, 'broadcast', ['message' => $message]);
    if (!$formatted_message) {
        $formatted_message = $message;
    }

    $results = [
        'total' => count($recipients),
        'sent' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($recipients as $recipient) {
        $result = sendTelegramMessage($settings['bot_token'], $recipient['telegram_chat_id'], $formatted_message);

        logTelegramMessage(
            $pdo,
            $recipient['id'],
            $recipient['telegram_chat_id'],
            $formatted_message,
            'broadcast',
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $result['message_id'] ?? null
        );

        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'user' => $recipient['username'],
                'error' => $result['error']
            ];
        }
    }

    return ['success' => true, 'results' => $results];
}

/**
 * Check if user has access to Telegram features
 * Only super_admin and reseller_admin have access
 */
function hasAccessToTelegram($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare('SELECT super_user, is_reseller_admin FROM _users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        return $user['super_user'] == 1 || $user['is_reseller_admin'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Initialize default notification settings for a user
 */
function initializeTelegramNotificationSettings($pdo, $user_id) {
    try {
        // Check if settings already exist
        $stmt = $pdo->prepare('SELECT id FROM _telegram_notification_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return true;
        }

        // Create default settings
        $sql = "INSERT INTO _telegram_notification_settings
                (user_id, notify_new_account, notify_renewal, notify_expiry, notify_expired,
                 notify_low_balance, notify_new_payment, notify_login, notify_daily_report)
                VALUES (?, 1, 1, 1, 1, 1, 1, 0, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
