<?php
/**
 * STB Action Log Helper (v1.17.4)
 *
 * Provides functions to log all STB device control actions
 *
 * Usage:
 *   include('stb_log_helper.php');
 *   logStbAction($pdo, 'event', $mac, 'reboot', null, 'success');
 *   logStbAction($pdo, 'message', $mac, 'Welcome!', 'Full message text here', 'success');
 */

/**
 * Log an STB action (event or message)
 *
 * @param PDO $pdo Database connection
 * @param string $actionType 'event' or 'message'
 * @param string $mac MAC address of the device
 * @param string $actionDetail Event type or message preview (max 255 chars)
 * @param string|null $fullMessage Full message content (for messages only)
 * @param string $status 'success' or 'failed'
 * @param string|null $errorMessage Error message if failed
 * @return bool Success status
 */
function logStbAction($pdo, $actionType, $mac, $actionDetail, $fullMessage = null, $status = 'success', $errorMessage = null) {
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE '_stb_action_logs'");
        if ($tableCheck->rowCount() == 0) {
            // Table doesn't exist - create it
            createStbLogsTable($pdo);
        }

        // Get user info from session
        $userId = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'system';

        // Truncate action detail to 255 chars
        $actionDetail = mb_substr($actionDetail, 0, 255);

        $stmt = $pdo->prepare('
            INSERT INTO _stb_action_logs
            (user_id, username, action_type, mac_address, action_detail, full_message, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $userId,
            $username,
            $actionType,
            $mac,
            $actionDetail,
            $fullMessage,
            $status,
            $errorMessage
        ]);

        return true;

    } catch (PDOException $e) {
        error_log("STB action log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an STB event
 */
function logStbEvent($pdo, $mac, $eventType, $status = 'success', $errorMessage = null) {
    $eventLabels = [
        'reboot' => 'Reboot Device',
        'reload_portal' => 'Reload Portal',
        'update_channels' => 'Update Channels',
        'play_channel' => 'Play Channel',
        'play_radio_channel' => 'Play Radio Channel',
        'update_image' => 'Update Image',
        'show_menu' => 'Show Menu',
        'cut_off' => 'Cut Off'
    ];

    $detail = $eventLabels[$eventType] ?? $eventType;
    return logStbAction($pdo, 'event', $mac, $detail, null, $status, $errorMessage);
}

/**
 * Log an STB message
 */
function logStbMessage($pdo, $mac, $message, $status = 'success', $errorMessage = null) {
    // Create preview (first 100 chars)
    $preview = mb_strlen($message) > 100 ? mb_substr($message, 0, 97) . '...' : $message;
    return logStbAction($pdo, 'message', $mac, $preview, $message, $status, $errorMessage);
}

/**
 * Create the STB logs table if it doesn't exist
 */
function createStbLogsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `_stb_action_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `username` VARCHAR(100) NOT NULL,
        `action_type` ENUM('event', 'message') NOT NULL,
        `mac_address` VARCHAR(17) NOT NULL,
        `action_detail` VARCHAR(255) NOT NULL,
        `full_message` TEXT NULL,
        `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
        `error_message` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_mac` (`mac_address`),
        INDEX `idx_action_type` (`action_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
}
?>
