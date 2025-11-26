<?php
/**
 * Audit Log Helper
 * Version: 1.12.0
 *
 * Provides functions to log all user actions to the audit log.
 * IMPORTANT: This log is PERMANENT - no delete capability.
 *
 * Usage:
 *   include(__DIR__ . '/audit_helper.php');
 *   logAuditEvent($pdo, 'create', 'account', $account_id, $mac_address, null, $new_data, 'Account created');
 */

/**
 * Log an audit event
 *
 * @param PDO $pdo Database connection
 * @param string $action Action type: create, update, delete, login, logout, view, export, etc.
 * @param string $target_type What was affected: account, user, reseller, plan, settings, sms, etc.
 * @param string|null $target_id ID of affected item
 * @param string|null $target_name Name/identifier of affected item
 * @param mixed $old_value Previous value (will be JSON encoded)
 * @param mixed $new_value New value (will be JSON encoded)
 * @param string|null $details Additional notes
 * @return bool Success status
 */
function logAuditEvent($pdo, $action, $target_type, $target_id = null, $target_name = null, $old_value = null, $new_value = null, $details = null) {
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE '_audit_log'");
        if ($tableCheck->rowCount() == 0) {
            return false; // Table not created yet
        }

        // Get current user info from session
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'system';

        // Determine user type
        $user_type = 'unknown';
        if (isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1) {
            $user_type = 'super_admin';
        } else if (isset($_SESSION['permissions'])) {
            $permissions = $_SESSION['permissions'];
            if (strpos($permissions, 'is_reseller_admin') !== false) {
                $user_type = 'reseller_admin';
            } else {
                $user_type = 'reseller';
            }
        }

        // Get client IP address
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip_address && strpos($ip_address, ',') !== false) {
            $ip_address = trim(explode(',', $ip_address)[0]);
        }

        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // JSON encode values if not null
        $old_value_json = $old_value !== null ? json_encode($old_value, JSON_UNESCAPED_UNICODE) : null;
        $new_value_json = $new_value !== null ? json_encode($new_value, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare('
            INSERT INTO _audit_log
            (timestamp, user_id, username, user_type, action, target_type, target_id, target_name, old_value, new_value, ip_address, user_agent, details)
            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $user_id,
            $username,
            $user_type,
            $action,
            $target_type,
            $target_id,
            $target_name,
            $old_value_json,
            $new_value_json,
            $ip_address,
            $user_agent,
            $details
        ]);

        return true;

    } catch (PDOException $e) {
        // Log error but don't break the main operation
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log account creation
 */
function auditAccountCreated($pdo, $account_id, $mac_address, $account_data) {
    return logAuditEvent($pdo, 'create', 'account', $account_id, $mac_address, null, $account_data, 'New account created');
}

/**
 * Log account update/renewal
 */
function auditAccountUpdated($pdo, $account_id, $mac_address, $old_data, $new_data, $details = 'Account updated') {
    return logAuditEvent($pdo, 'update', 'account', $account_id, $mac_address, $old_data, $new_data, $details);
}

/**
 * Log account deletion
 */
function auditAccountDeleted($pdo, $account_id, $mac_address, $account_data) {
    return logAuditEvent($pdo, 'delete', 'account', $account_id, $mac_address, $account_data, null, 'Account deleted');
}

/**
 * Log user/reseller creation
 */
function auditUserCreated($pdo, $user_id, $username, $user_data) {
    // Remove sensitive data
    $safe_data = $user_data;
    unset($safe_data['password']);
    return logAuditEvent($pdo, 'create', 'user', $user_id, $username, null, $safe_data, 'User/reseller created');
}

/**
 * Log user/reseller update
 */
function auditUserUpdated($pdo, $user_id, $username, $old_data, $new_data, $details = 'User updated') {
    // Remove sensitive data
    $safe_old = $old_data;
    $safe_new = $new_data;
    unset($safe_old['password'], $safe_new['password']);
    return logAuditEvent($pdo, 'update', 'user', $user_id, $username, $safe_old, $safe_new, $details);
}

/**
 * Log user/reseller deletion
 */
function auditUserDeleted($pdo, $user_id, $username, $user_data) {
    $safe_data = $user_data;
    unset($safe_data['password']);
    return logAuditEvent($pdo, 'delete', 'user', $user_id, $username, $safe_data, null, 'User/reseller deleted');
}

/**
 * Log SMS sent
 */
function auditSmsSent($pdo, $phone, $message_type, $details = null) {
    return logAuditEvent($pdo, 'send', 'sms', null, $phone, null, ['type' => $message_type], $details ?? 'SMS sent');
}

/**
 * Log settings change
 */
function auditSettingsChanged($pdo, $setting_name, $old_value, $new_value) {
    return logAuditEvent($pdo, 'update', 'settings', null, $setting_name, $old_value, $new_value, 'Settings changed');
}

/**
 * Log permission change
 */
function auditPermissionChanged($pdo, $user_id, $username, $old_permissions, $new_permissions) {
    return logAuditEvent($pdo, 'update', 'permissions', $user_id, $username, $old_permissions, $new_permissions, 'Permissions changed');
}

/**
 * Log message sent to STB
 */
function auditStbMessage($pdo, $mac_address, $message) {
    return logAuditEvent($pdo, 'send', 'stb_message', null, $mac_address, null, ['message' => $message], 'Message sent to STB');
}

/**
 * Log data export
 */
function auditDataExport($pdo, $export_type, $details = null) {
    return logAuditEvent($pdo, 'export', $export_type, null, null, null, null, $details ?? "Data exported: $export_type");
}
?>
