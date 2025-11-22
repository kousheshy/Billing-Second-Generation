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

        if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
            return false; // SMS not configured
        }

        // Get welcome template
        $stmt = $pdo->prepare('SELECT template FROM _sms_templates WHERE user_id = ? AND name = "New Account Welcome" LIMIT 1');
        $stmt->execute([$user_id]);
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
            $user_id,
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
        // Get SMS settings
        $stmt = $pdo->prepare('SELECT * FROM _sms_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || empty($settings['api_token']) || empty($settings['sender_number'])) {
            return false;
        }

        // Get renewal template
        $stmt = $pdo->prepare('SELECT template FROM _sms_templates WHERE user_id = ? AND name = "Renewal Confirmation" LIMIT 1');
        $stmt->execute([$user_id]);
        $template_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template_row) {
            return false;
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

        // Log SMS
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
            $user_id,
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
?>
