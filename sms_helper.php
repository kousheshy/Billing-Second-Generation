<?php
/**
 * SMS Helper Functions
 * Provides SMS sending functionality for automatic notifications
 */

/**
 * Send welcome SMS to new account
 *
 * @param int $user_id - The user/reseller who created the account
 * @param string $customer_name - Customer full name
 * @param string $mac - MAC address
 * @param string $phone - Customer phone number
 * @param string $expiry_date - Account expiry date
 * @param int $account_id - Account ID (optional)
 * @return bool - True if sent successfully, false otherwise
 */
function sendWelcomeSMS($pdo, $user_id, $customer_name, $mac, $phone, $expiry_date, $account_id = null) {
    if (empty($phone)) {
        return false; // No phone number
    }

    try {
        // Get SMS settings for this user
        $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user's SMS is not configured, fall back to admin's SMS settings
        $actual_user_id = $user_id; // Track who we're sending for
        if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
            // Find admin user (super_user = 1)
            $stmt = $pdo->prepare('SELECT id FROM _users WHERE super_user = 1 LIMIT 1');
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                // Get admin's SMS settings
                $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
                $stmt->execute([$admin['id']]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $actual_user_id = $admin['id']; // Use admin's settings

                // Still no settings? Return false
                if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
                    return false; // No SMS configured anywhere
                }
            } else {
                return false; // No admin found
            }
        }

        // Get welcome template (from the user whose settings we're using)
        $stmt = $pdo->prepare('SELECT template FROM _sms_templates WHERE user_id = ? AND name = "New Account Welcome" LIMIT 1');
        $stmt->execute([$actual_user_id]);
        $template_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template_row) {
            return false; // No template found
        }

        // Personalize message
        $message = str_replace(
            ['{name}', '{mac}', '{expiry_date}'],
            [$customer_name ?: 'Customer', $mac ?: '', $expiry_date ?: ''],
            $template_row['template']
        );

        // Prepare API request
        $api_url = rtrim($settings['base_url'], '/') . '/api/send';

        $payload = [
            'sending_type' => 'webservice',
            'from_number' => $settings['sender_number'],
            'message' => $message,
            'params' => [
                'recipients' => [$phone]
            ]
        ];

        // Send SMS via cURL
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $settings['api_token']
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Parse response
        $api_response = json_decode($response, true);

        $status = 'failed';
        $error_message = null;
        $bulk_id = null;

        if ($http_code == 200 && $api_response && isset($api_response['data'])) {
            $status = 'sent';
            $bulk_id = isset($api_response['data']['bulk_id']) ? $api_response['data']['bulk_id'] : null;
        } else {
            $error_message = $curl_error ? $curl_error : ($api_response['message'] ?? 'Unknown error');
        }

        // Log SMS in database
        // Note: sent_by is the user whose SMS settings were used (could be admin if reseller SMS not configured)
        $sql = "INSERT INTO _sms_logs
                (account_id, mac, recipient_name, recipient_number, message, message_type, sent_by, sent_at, status, api_response, bulk_id, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, 'new_account', ?, NOW(), ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $account_id,
            $mac,
            $customer_name,
            $phone,
            $message,
            $actual_user_id, // Log who actually sent it (admin if fallback was used)
            $status,
            json_encode($api_response),
            $bulk_id,
            $error_message
        ]);

        return $status === 'sent';

    } catch (Exception $e) {
        // Silently fail - don't disrupt account creation
        error_log("SMS Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send renewal SMS to account
 *
 * @param int $user_id - The user/reseller who renewed the account
 * @param string $customer_name - Customer full name
 * @param string $mac - MAC address
 * @param string $phone - Customer phone number
 * @param string $expiry_date - New expiry date
 * @param int $account_id - Account ID (optional)
 * @return bool - True if sent successfully, false otherwise
 */
function sendRenewalSMS($pdo, $user_id, $customer_name, $mac, $phone, $expiry_date, $account_id = null) {
    if (empty($phone)) {
        return false;
    }

    try {
        // Get SMS settings for this user
        $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user's SMS is not configured, fall back to admin's SMS settings
        $actual_user_id = $user_id; // Track who we're sending for
        if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
            // Find admin user (super_user = 1)
            $stmt = $pdo->prepare('SELECT id FROM _users WHERE super_user = 1 LIMIT 1');
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                // Get admin's SMS settings
                $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
                $stmt->execute([$admin['id']]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $actual_user_id = $admin['id']; // Use admin's settings

                // Still no settings? Return false
                if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
                    return false; // No SMS configured anywhere
                }
            } else {
                return false; // No admin found
            }
        }

        // Get renewal template (from the user whose settings we're using)
        $stmt = $pdo->prepare('SELECT template FROM _sms_templates WHERE user_id = ? AND name = "Renewal Confirmation" LIMIT 1');
        $stmt->execute([$actual_user_id]);
        $template_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template_row) {
            return false; // No template found
        }

        // Personalize message
        $message = str_replace(
            ['{name}', '{mac}', '{expiry_date}'],
            [$customer_name ?: 'Customer', $mac ?: '', $expiry_date ?: ''],
            $template_row['template']
        );

        // Prepare and send API request (same as welcome SMS)
        $api_url = rtrim($settings['base_url'], '/') . '/api/send';

        $payload = [
            'sending_type' => 'webservice',
            'from_number' => $settings['sender_number'],
            'message' => $message,
            'params' => [
                'recipients' => [$phone]
            ]
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $settings['api_token']
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $api_response = json_decode($response, true);
        $status = ($http_code == 200 && $api_response && isset($api_response['data'])) ? 'sent' : 'failed';
        $bulk_id = isset($api_response['data']['bulk_id']) ? $api_response['data']['bulk_id'] : null;

        // Log SMS in database
        // Note: sent_by is the user whose SMS settings were used (could be admin if reseller SMS not configured)
        $sql = "INSERT INTO _sms_logs
                (account_id, mac, recipient_name, recipient_number, message, message_type, sent_by, sent_at, status, api_response, bulk_id, created_at)
                VALUES (?, ?, ?, ?, ?, 'renewal', ?, NOW(), ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $account_id,
            $mac,
            $customer_name,
            $phone,
            $message,
            $actual_user_id, // Log who actually sent it (admin if fallback was used)
            $status,
            json_encode($api_response),
            $bulk_id
        ]);

        return $status === 'sent';

    } catch (Exception $e) {
        error_log("SMS Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize SMS settings and templates for a new reseller
 * This ensures resellers can automatically send welcome SMS when they add accounts
 *
 * @param PDO $pdo - Database connection
 * @param int $user_id - The new reseller's user ID
 * @return bool - True if initialization successful, false otherwise
 */
function initializeResellerSMS($pdo, $user_id) {
    try {
        // Check if SMS settings already exist for this user
        $stmt = $pdo->prepare('SELECT id FROM _sms_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return true; // Already initialized
        }

        // Create default SMS settings (disabled by default, reseller needs to configure)
        $stmt = $pdo->prepare('INSERT INTO _sms_settings
            (user_id, auto_send_enabled, days_before_expiry, base_url, created_at, updated_at)
            VALUES (?, 0, 7, \'https://edge.ippanel.com/v1\', NOW(), NOW())');
        $stmt->execute([$user_id]);

        // Create default SMS templates (same as admin templates)
        $templates = [
            [
                'name' => 'Expiry Reminder',
                'template' => '{name}
عزیز، سرویس شما به زودی منقضی می‌شود
تاریخ اتمام: {expiry_date}
برای تمدید با ما تماس بگیرید.

پشتیبانی: واتساپ 00447736932888',
                'description' => 'Sent automatically before account expiry'
            ],
            [
                'name' => 'New Account Welcome',
                'template' => '{name}
عزیز به خانواده شوباکس خوش آمدید.
تاریخ اتمام سرویس شما: {expiry_date}

پشتیبانی: واتساپ 00447736932888',
                'description' => 'Sent when new account is created'
            ],
            [
                'name' => 'Renewal Confirmation',
                'template' => '{name}
عزیز، سرویس شوباکس شما با موفقیت تمدید شد.

 تاریخ اتمام جدید: {expiry_date}.
از اعتماد شما سپاسگزاریم!

پشتیبانی: واتساپ 00447736932888',
                'description' => 'Sent when account is renewed'
            ],
            [
                'name' => 'Payment Reminder',
                'template' => '{name}
عزیز، زمان پرداخت شما فرا رسیده است
لطفاً قبل از {expiry_date} سرویس خود را تمدید کنید

پشتیبانی: واتساپ 00447736932888',
                'description' => 'Payment reminder message'
            ]
        ];

        foreach ($templates as $template) {
            $stmt = $pdo->prepare('INSERT INTO _sms_templates
                (user_id, name, template, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$user_id, $template['name'], $template['template'], $template['description']]);
        }

        error_log("SMS settings and templates initialized for user_id: $user_id");
        return true;

    } catch (Exception $e) {
        error_log("Failed to initialize SMS for user_id $user_id: " . $e->getMessage());
        return false;
    }
}
?>
