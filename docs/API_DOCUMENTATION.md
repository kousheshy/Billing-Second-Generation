# ShowBox Billing Panel - API Documentation

Complete reference for all API endpoints in the ShowBox Billing Panel.

**Version:** 1.11.66
**Last Updated:** November 27, 2025
**Base URL:** `http://your-domain.com/`

---

## Table of Contents

1. [Authentication](#authentication)
2. [WebAuthn / Biometric Authentication](#webauthn--biometric-authentication)
3. [Session Management](#session-management)
4. [Account Management](#account-management)
5. [Reseller Management](#reseller-management)
6. [Plan Management](#plan-management)
7. [Transaction Management](#transaction-management)
8. [User Management](#user-management)
9. [Stalker Portal Integration](#stalker-portal-integration)
10. [STB Device Control](#stb-device-control)
11. [Theme Management](#theme-management)
12. [Push Notifications](#push-notifications)
13. [Error Codes](#error-codes)

---

## Authentication

### Login
**Endpoint:** `POST /login.php`

**Description:** Authenticate user and create session.

**Request Body:**
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "",
  "user": {
    "id": 1,
    "username": "admin",
    "full_name": "Administrator",
    "email": "admin@showbox.com",
    "super_user": 1,
    "currency": "GBP",
    "balance": 1000.00
  }
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Invalid username or password"
}
```

---

### Get User Info
**Endpoint:** `GET /get_user_info.php`

**Description:** Get current logged-in user information.

**Headers:** Requires active session cookie.

**Response:**
```json
{
  "error": 0,
  "user": {
    "id": 1,
    "username": "admin",
    "full_name": "Administrator",
    "email": "admin@showbox.com",
    "super_user": 1,
    "currency": "GBP",
    "balance": 1000.00,
    "max_users": 0
  }
}
```

---

### Logout
**Endpoint:** `GET /logout.php`

**Description:** Destroy user session and logout.

**Response:** Redirects to `/index.html`

---

## WebAuthn / Biometric Authentication

WebAuthn (Web Authentication API) enables passwordless login using biometric authentication such as Face ID, Touch ID, or Windows Hello.

**Version:** Added in v1.11.19

### Register Biometric Credential

#### Get Registration Challenge
**Endpoint:** `GET /api/webauthn_register.php`

**Description:** Get a challenge and registration options for biometric credential registration.

**Permissions:** Requires active session (logged-in user)

**Response:**
```json
{
  "error": 0,
  "challenge": "base64-encoded-32-byte-challenge",
  "rp": {
    "name": "ShowBox Billing",
    "id": "billing.apamehnet.com"
  },
  "user": {
    "id": "base64-encoded-user-id",
    "name": "admin",
    "displayName": "Administrator"
  },
  "pubKeyCredParams": [
    { "type": "public-key", "alg": -7 },
    { "type": "public-key", "alg": -257 }
  ],
  "authenticatorSelection": {
    "authenticatorAttachment": "platform",
    "userVerification": "required",
    "residentKey": "preferred"
  },
  "timeout": 60000,
  "attestation": "none"
}
```

#### Store Credential
**Endpoint:** `POST /api/webauthn_register.php`

**Description:** Store a registered biometric credential after successful registration.

**Request Body:**
```json
{
  "credential_id": "base64-encoded-credential-id",
  "public_key": "base64-encoded-public-key",
  "device_name": "iPhone 15 Pro"
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Biometric registered successfully"
}
```

**Error Response:**
```json
{
  "error": 1,
  "message": "This biometric is already registered"
}
```

---

### Authenticate with Biometric

#### Check Biometric Availability & Get Challenge
**Endpoint:** `GET /api/webauthn_authenticate.php?username=<username>`

**Description:** Check if a user has registered biometric credentials and get an authentication challenge.

**Query Parameters:**
- `username` (required): The username to check for biometric credentials

**Response (Biometric Available):**
```json
{
  "error": 0,
  "biometric_available": true,
  "challenge": "base64-encoded-32-byte-challenge",
  "rpId": "billing.apamehnet.com",
  "allowCredentials": [
    {
      "type": "public-key",
      "id": "base64-encoded-credential-id",
      "transports": ["internal"]
    }
  ],
  "timeout": 60000,
  "userVerification": "required"
}
```

**Response (No Biometric):**
```json
{
  "error": 0,
  "biometric_available": false,
  "message": "No biometric credentials found for this user"
}
```

#### Verify Biometric Authentication
**Endpoint:** `POST /api/webauthn_authenticate.php`

**Description:** Verify biometric authentication and create user session.

**Request Body:**
```json
{
  "credential_id": "base64-encoded-credential-id",
  "authenticator_data": "base64-encoded-authenticator-data",
  "client_data_json": "base64-encoded-client-data",
  "signature": "base64-encoded-signature",
  "counter": 1
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Authentication successful"
}
```

**Error Response:**
```json
{
  "error": 1,
  "message": "Invalid credentials"
}
```

---

### Manage Biometric Credentials

#### List Credentials
**Endpoint:** `GET /api/webauthn_manage.php`

**Description:** List all registered biometric credentials for the logged-in user.

**Permissions:** Requires active session

**Response:**
```json
{
  "error": 0,
  "credentials": [
    {
      "id": 1,
      "device_name": "iPhone 15 Pro",
      "created_at": "2025-11-25 10:30:00",
      "last_used": "2025-11-25 14:20:00"
    },
    {
      "id": 2,
      "device_name": "MacBook Pro",
      "created_at": "2025-11-24 09:00:00",
      "last_used": null
    }
  ],
  "count": 2
}
```

#### Delete Credential
**Endpoint:** `DELETE /api/webauthn_manage.php`

**Description:** Remove a specific biometric credential.

**Permissions:** Requires active session (can only delete own credentials)

**Request Body:**
```json
{
  "credential_id": 1
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Biometric credential removed successfully"
}
```

**Error Response:**
```json
{
  "error": 1,
  "message": "Credential not found or does not belong to you"
}
```

---

## Session Management

Session management endpoints for auto-logout functionality and session heartbeat.

**Version:** Added in v1.11.20

### Auto-Logout Settings

#### Get Auto-Logout Timeout
**Endpoint:** `GET /api/auto_logout_settings.php`

**Description:** Get the current auto-logout timeout setting.

**Permissions:** Public (needed for logout timer on login page)

**Response:**
```json
{
  "error": 0,
  "auto_logout_timeout": 5,
  "timeout_seconds": 300
}
```

#### Update Auto-Logout Timeout
**Endpoint:** `POST /api/auto_logout_settings.php`

**Description:** Update the auto-logout timeout setting.

**Permissions:** Super admin only

**Request Body:**
```json
{
  "timeout": 10
}
```

**Validation:**
- `timeout`: Must be between 0-60 minutes (0 = disabled)

**Response:**
```json
{
  "error": 0,
  "message": "Auto-logout set to 10 minutes",
  "auto_logout_timeout": 10,
  "timeout_seconds": 600
}
```

**Response (Disabled):**
```json
{
  "error": 0,
  "message": "Auto-logout disabled",
  "auto_logout_timeout": 0,
  "timeout_seconds": 0
}
```

**Error Response:**
```json
{
  "error": 1,
  "message": "Only super admin can change this setting"
}
```

---

### Session Heartbeat

#### Check Session Status
**Endpoint:** `GET /api/session_heartbeat.php`

**Description:** Check if the current session is still valid and update last activity timestamp.

**Permissions:** Requires active session

**Response (Valid Session):**
```json
{
  "error": 0,
  "expired": false,
  "timeout_minutes": 5,
  "time_remaining_seconds": 290,
  "last_activity": 1732547600
}
```

**Response (Expired Session):**
```json
{
  "error": 0,
  "expired": true,
  "message": "Session expired due to inactivity"
}
```

**Response (Not Logged In):**
```json
{
  "error": 1,
  "expired": true,
  "message": "Not logged in"
}
```

#### Send Heartbeat Ping
**Endpoint:** `POST /api/session_heartbeat.php`

**Description:** Update the last activity timestamp to keep the session alive.

**Permissions:** Requires active session

**Response:**
```json
{
  "error": 0,
  "expired": false,
  "message": "Activity recorded",
  "last_activity": 1732547800
}
```

**Behavior:**
- Called automatically by JavaScript on user activity (throttled to every 30 seconds)
- Updates `$_SESSION['last_activity']` server-side
- Returns `expired: true` if session has timed out

---

## Account Management

### Get All Accounts
**Endpoint:** `GET /get_accounts.php`

**Description:** Retrieve all accounts (filtered by reseller for non-admin users).

**Headers:** Requires active session.

**Query Parameters:**
- `viewAllAccounts` (optional, boolean) - For reseller admins: `true` to view all accounts, `false` to view only their own

**Response:**
```json
{
  "error": 0,
  "accounts": [
    {
      "id": 1,
      "username": "user001",
      "email": "user@example.com",
      "phone_number": "+447712345678",
      "mac": "00:1A:79:XX:XX:XX",
      "full_name": "John Doe",
      "tariff_plan": "1 Month Plan",
      "end_date": "2025-02-15 23:59:59",
      "status": 1,
      "reseller": 1,
      "reseller_name": "Reseller One",
      "plan_name": "Premium Monthly",
      "timestamp": 1705449600
    }
  ]
}
```

**New Fields (v1.7.1):**
- `phone_number` - Customer phone number (can be NULL)
- `reseller_name` - Name of assigned reseller (or NULL if not assigned)
- `plan_name` - Name of subscription plan

---

### Add Account
**Endpoint:** `POST /add_account.php`

**Description:** Create new IPTV account on local database and Stalker Portal.

**Request Body:**
```json
{
  "username": "user001",
  "password": "password123",
  "mac": "00:1A:79:XX:XX:XX",
  "full_name": "John Doe",
  "email": "user@example.com",
  "phone": "+447712345678",
  "tariff_plan": "1 Month Plan",
  "end_date": "2025-02-15",
  "status": 1,
  "plan": 1
}
```

**New Fields (v1.7.1):**
- `phone` (optional) - Customer phone number, sent to Stalker Portal and saved locally

**Response:**
```json
{
  "error": 0,
  "err_msg": "Account created successfully",
  "account_id": 1
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Username already exists"
}
```

---

### Update Account
**Endpoint:** `POST /update_account.php`

**Description:** Update existing account details.

**Request Body:**
```json
{
  "username": "user001",
  "full_name": "John Smith",
  "email": "john@example.com",
  "tariff_plan": "3 Month Plan",
  "end_date": "2025-05-15",
  "status": 1
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Account updated successfully"
}
```

---

### Delete Account
**Endpoint:** `POST /remove_account.php`

**Description:** Delete account from system (admin only).

**Request Body:**
```json
{
  "username": "user001"
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Account deleted successfully"
}
```

**Permissions:** Admin only (`super_user = 1`)

---

### Change Account Status
**Endpoint:** `POST /change_status.php`

**Description:** Enable or disable account (admin control).

**Request Body:**
```json
{
  "username": "user001",
  "status": 0
}
```

**Values:**
- `status: 1` = ON (enabled)
- `status: 0` = OFF (disabled)

**Response:**
```json
{
  "error": 0,
  "err_msg": "Status updated successfully"
}
```

**Note:** Status field is for admin control only, not renewal tracking.

---

### Toggle Account Status (v1.7.5)
**Endpoint:** `GET /toggle_account_status.php`

**Description:** Quick toggle account status (enable/disable) with permission-based access control.

**Headers:** Requires active session.

**Query Parameters:**
- `username` (required, string) - Target account username
- `status` (required, integer) - New status value (1 = active, 0 = disabled)

**Example:**
```
GET /toggle_account_status.php?username=user001&status=0
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "",
  "message": "John Doe is now disabled"
}
```

**Success Response Format:**
- Displays customer's full name (not username)
- Shows current status text ("active" or "disabled")

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. You do not have permission to toggle account status."
}
```

**Permission Requirements:**
- **Super Admin**: Can toggle any account status
- **Reseller Admin**: Can toggle status of accounts under them (automatic permission)
- **Regular Reseller**: Requires explicit "Can Toggle Account Status" permission
- **Observer**: Cannot toggle account status

**Security:**
- Resellers can only toggle accounts that belong to them
- Updates both Stalker Portal Server 1 and Server 2
- Uses API method with MAC address and PUT operation
- Full audit logging of all status changes

**Permissions Field:** Index 5 in permissions string (`can_toggle_status`)

**New in v1.7.5:** This endpoint provides one-click status toggle functionality with granular permission control.

---

### Sync Accounts
**Endpoint:** `POST /sync_accounts.php`

**Description:** Synchronize all accounts from Stalker Portal to local database.

**Request Body:** None required (uses session)

**Response:**
```json
{
  "error": 0,
  "err_msg": "",
  "synced": 1250,
  "skipped": 0,
  "total_accounts": 1250
}
```

**Behavior:**
- Deletes all existing accounts before sync
- Fetches from Stalker Portal `/accounts/` endpoint
- Maps fields: login → username, mac, full_name, email, end_date, status
- Assigns default reseller (admin user ID 1)
- Handles invalid dates (0000-00-00 → NULL)

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Failed to fetch accounts from Stalker Portal: Connection timeout"
}
```

---

## Reseller Management

### Get All Resellers
**Endpoint:** `GET /get_resellers.php`

**Description:** Retrieve all resellers (admin only).

**Response:**
```json
{
  "error": 0,
  "resellers": [
    {
      "id": 2,
      "username": "reseller1",
      "full_name": "Reseller One",
      "email": "reseller@example.com",
      "max_users": 100,
      "currency": "GBP",
      "theme": "light",
      "super_user": 0,
      "balance": 500.00,
      "timestamp": 1705449600
    }
  ]
}
```

---

### Add Reseller
**Endpoint:** `POST /add_reseller.php`

**Description:** Create new reseller account.

**Request Body:**
```json
{
  "username": "reseller1",
  "password": "password123",
  "full_name": "Reseller One",
  "email": "reseller@example.com",
  "max_users": 100,
  "currency": "GBP",
  "theme": "light",
  "initial_balance": 500.00
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Reseller created successfully",
  "reseller_id": 2
}
```

---

### Update Reseller
**Endpoint:** `POST /update_reseller.php`

**Description:** Update reseller details. **[v1.7.4]** Automatically propagates theme changes to all accounts under the reseller.

**Request Body:**
```json
{
  "id": "2",
  "username": "reseller1",
  "password": "newpass123",
  "name": "Reseller One Updated",
  "email": "new@example.com",
  "max_users": "200",
  "theme": "HenSoft-TV Realistic-Dark",
  "currency": "USD",
  "permissions": "1|1|0|0|1|1|1",
  "is_observer": 0
}
```

**Permission Format (v1.7.9):**
The `permissions` field is a pipe-delimited string with 7 fields:
`can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging`

- **Field 1**: `can_edit` - Can edit accounts (0 or 1)
- **Field 2**: `can_add` - Can add accounts (0 or 1)
- **Field 3**: `is_reseller_admin` - Reseller admin status (0 or 1)
- **Field 4**: `can_delete` - Can delete accounts (0 or 1)
- **Field 5**: `can_control_stb` - Can send STB events & messages (0 or 1)
- **Field 6**: `can_toggle_status` - Can toggle account status (0 or 1)
- **Field 7**: `can_access_messaging` - Can access messaging tab (0 or 1) **[Added v1.7.9]**

**Response (Normal Update):**
```json
{
  "error": 0,
  "err_msg": ""
}
```

**Response (Theme Changed - Success):**
```json
{
  "error": 0,
  "err_msg": "Reseller updated successfully. Theme changed for all 10 accounts."
}
```

**Response (Theme Changed - Partial Failure):**
```json
{
  "error": 0,
  "warning": 1,
  "err_msg": "Reseller updated. Theme changed for 8/10 accounts (2 failed)."
}
```

**Automatic Theme Propagation (v1.7.4):**
- Detects when reseller's theme has changed
- Automatically updates ALL accounts under that reseller with the new theme
- Updates are sent to Stalker Portal via `/stalker_portal/update_account.php`
- Includes 0.1 second delay between updates to prevent server overload
- Returns detailed success/failure statistics
- Comprehensive error logging for troubleshooting

**Notes:**
- If password is empty, the existing password is retained
- Theme propagation only occurs when theme value actually changes
- Plans are NOT updated here - they are managed separately via `assign_plans.php`
- Permissions format: `can_edit|can_add|is_admin|can_delete|can_control_stb|can_toggle_status`
  - v1.7.4: changed field 4 from `reserved` to `can_control_stb`
  - v1.7.5: added field 5 `can_toggle_status` for account status toggle permission

---

### Delete Reseller
**Endpoint:** `POST /remove_reseller.php`

**Description:** Delete reseller account.

**Request Body:**
```json
{
  "reseller_id": 2
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Reseller deleted successfully"
}
```

---

### Adjust Reseller Credit
**Endpoint:** `POST /adjust_credit.php`

**Description:** Add or subtract credits from reseller balance.

**Request Body:**
```json
{
  "reseller_id": 2,
  "amount": 100.00,
  "type": "credit",
  "description": "Top-up payment"
}
```

**Values:**
- `type: "credit"` = Add to balance
- `type: "debit"` = Subtract from balance

**Response:**
```json
{
  "error": 0,
  "err_msg": "Credit adjusted successfully",
  "new_balance": 600.00
}
```

---

## Plan Management

### Get All Plans
**Endpoint:** `GET /get_plans.php`

**Description:** Retrieve all subscription plans.

**Response:**
```json
{
  "error": 0,
  "plans": [
    {
      "id": 1,
      "plan_id": "1-month-gbp",
      "currency": "GBP",
      "price": 10.00,
      "expiry_days": 30,
      "timestamp": 1705449600
    },
    {
      "id": 2,
      "plan_id": "3-month-gbp",
      "currency": "GBP",
      "price": 25.00,
      "expiry_days": 90,
      "timestamp": 1705449600
    }
  ]
}
```

---

### Add Plan
**Endpoint:** `POST /add_plan.php`

**Description:** Create new subscription plan.

**Request Body:**
```json
{
  "plan_id": "1-month-gbp",
  "currency": "GBP",
  "price": 10.00,
  "expiry_days": 30
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Plan created successfully",
  "plan_id": 1
}
```

---

### Delete Plan
**Endpoint:** `POST /remove_plan.php`

**Description:** Delete subscription plan.

**Request Body:**
```json
{
  "plan_id": 1
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Plan deleted successfully"
}
```

---

## Transaction Management

### Get Transactions
**Endpoint:** `GET /get_transactions.php`

**Description:** Retrieve transaction history (filtered by user for resellers).

**Response:**
```json
{
  "error": 0,
  "transactions": [
    {
      "id": 1,
      "user_id": 2,
      "amount": 100.00,
      "type": "credit",
      "description": "Top-up payment",
      "timestamp": 1705449600,
      "date": "2025-01-17 10:00:00"
    }
  ]
}
```

---

### Create Transaction
**Endpoint:** `POST /new_transaction.php`

**Description:** Log new transaction.

**Request Body:**
```json
{
  "user_id": 2,
  "amount": 50.00,
  "type": "debit",
  "description": "Account creation charge"
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Transaction created successfully",
  "transaction_id": 10
}
```

---

## User Management

### Change Password
**Endpoint:** `POST /update_password.php`

**Description:** Update user password.

**Request Body:**
```json
{
  "old_password": "admin",
  "new_password": "new_secure_password",
  "confirm_password": "new_secure_password"
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Password updated successfully"
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Old password is incorrect"
}
```

---

## Stalker Portal Integration

### Send Message to Device
**Endpoint:** `POST /send_message.php`

**Description:** Send message to STB device via Stalker Portal.

**Request Body:**
```json
{
  "mac": "00:1A:79:XX:XX:XX",
  "message": "Your subscription will expire soon",
  "ttl": 86400
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Message sent successfully"
}
```

---

### Send Event to Device
**Endpoint:** `POST /send_event.php`

**Description:** Send control event to STB device.

**Request Body:**
```json
{
  "mac": "00:1A:79:XX:XX:XX",
  "event": "reload_portal"
}
```

**Available Events:**
- `reload_portal` - Reload portal interface
- `reboot` - Reboot device
- `cut_off` - Disable service
- `play_channel` - Switch to specific channel

**Response:**
```json
{
  "error": 0,
  "err_msg": "Event sent successfully"
}
```

---

## STB Device Control

### Send Event to Device
**Endpoint:** `POST /send_stb_event.php`

**Description:** Send control event to Set-Top Box device via Stalker Portal API.

**Version:** Added in v1.7.2, Enhanced in v1.7.4

**Permissions:**
- Super admin: Always allowed
- Resellers: Must have "Can Send STB Events & Messages" permission (v1.7.4)

**Request Body:**
```json
{
  "mac": "00:1A:79:XX:XX:XX",
  "event": "reboot"
}
```

**Available Events:**

| Event | Description | Additional Parameters |
|-------|-------------|-----------------------|
| `reboot` | Restart the device | None |
| `reload_portal` | Refresh portal interface | None |
| `update_channels` | Sync latest channel list | None |
| `play_channel` | Switch to specific TV channel | `channel_id` (required) |
| `play_radio_channel` | Switch to specific radio channel | `channel_id` (required) |
| `update_image` | Update device firmware/image | None |
| `show_menu` | Display portal menu on device | None |
| `cut_off` | Disable service to device | None |

**Example - Play Channel:**
```json
{
  "mac": "00:1A:79:12:34:56",
  "event": "play_channel",
  "channel_id": "123"
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Event sent successfully to device 00:1A:79:12:34:56"
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. Only administrators can send STB events."
}
```

**Technical Details:**
- Uses Stalker Portal `/send_event/` endpoint
- Channel events use `$` separator (not `&`): `event=play_channel$channel_number=123`
- Verifies device ownership for resellers
- Logs all actions to error_log for audit trail

---

### Send Message to Device
**Endpoint:** `POST /send_stb_message.php`

**Description:** Send text message to Set-Top Box device via Stalker Portal API.

**Version:** Added in v1.7.2, Enhanced in v1.7.4

**Permissions:**
- Super admin: Always allowed
- Resellers: Must have "Can Send STB Events & Messages" permission (v1.7.4)

**Request Body:**
```json
{
  "mac": "00:1A:79:XX:XX:XX",
  "message": "Your subscription will expire soon. Please renew."
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Message sent successfully to device 00:1A:79:XX:XX:XX"
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. This device does not belong to you."
}
```

**Technical Details:**
- Uses Stalker Portal `/stb_msg/` endpoint
- Message content is URL-encoded
- Verifies device ownership for resellers
- Logs all messages to error_log
- Message displayed on device screen

---

### Assign Reseller to Account
**Endpoint:** `POST /assign_reseller.php`

**Description:** Assign or reassign account to a specific reseller.

**Version:** Added in v1.7.0

**Permissions:** Super admin or reseller admin only

**Request Body:**
```json
{
  "username": "user001",
  "reseller_id": 5
}
```

**To Unassign (set to "Not Assigned"):**
```json
{
  "username": "user001",
  "reseller_id": ""
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Reseller assigned successfully"
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Only administrators can assign accounts to resellers"
}
```

---

### Get Tariffs from Stalker Portal
**Endpoint:** `GET /get_tariffs.php`

**Description:** Fetch all tariff plans from Stalker Portal API.

**Version:** Added in v1.3.0

**Permissions:** Admin only

**Response:**
```json
{
  "error": 0,
  "tariffs": [
    {
      "id": 1,
      "name": "1 Month Premium",
      "days": 30,
      "description": "Monthly subscription plan"
    },
    {
      "id": 2,
      "name": "3 Month Premium",
      "days": 90,
      "description": "Quarterly subscription plan"
    }
  ],
  "count": 2
}
```

**Error Response:**
```json
{
  "error": 1,
  "message": "Failed to fetch tariff plans from Stalker Portal"
}
```

---

## Theme Management

### Get Available Themes
**Endpoint:** `GET /get_themes.php`

**Description:** Retrieve list of available Stalker Portal themes for reseller assignment.

**Version:** Added in v1.7.3

**Permissions:** Super admin only

**Request:** No parameters required

**Response:**
```json
{
  "error": 0,
  "themes": [
    {
      "id": "HenSoft-TV Realistic-Centered SHOWBOX",
      "name": "HenSoft-TV Realistic-Centered SHOWBOX (Default)",
      "is_default": true
    },
    {
      "id": "HenSoft-TV Realistic-Centered",
      "name": "HenSoft-TV Realistic-Centered"
    },
    {
      "id": "HenSoft-TV Realistic-Dark",
      "name": "HenSoft-TV Realistic-Dark"
    },
    {
      "id": "HenSoft-TV Realistic-Light",
      "name": "HenSoft-TV Realistic-Light"
    },
    {
      "id": "cappuccino",
      "name": "Cappuccino"
    },
    {
      "id": "digital",
      "name": "Digital"
    },
    {
      "id": "emerald",
      "name": "Emerald"
    },
    {
      "id": "graphite",
      "name": "Graphite"
    },
    {
      "id": "ocean_blue",
      "name": "Ocean Blue"
    }
  ]
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. Only super admins can access themes."
}
```

**Usage:**
- Called automatically when admin opens Add/Edit Reseller modal
- Populates theme dropdown in reseller forms
- Default theme is pre-selected in Add Reseller form

**Theme Application:**
- When creating a reseller, selected theme is stored in `_users.theme` column
- When creating an account, reseller's theme is automatically applied via server-side script
- When editing an account, theme is synced to ensure consistency with reseller's current theme
- **[v1.7.4]** When editing a reseller's theme, ALL existing accounts under that reseller are automatically updated
- Theme is sent to Stalker Portal using custom `/stalker_portal/update_account.php` endpoint

**Technical Details:**
- Uses server-side theme list (can be updated in `get_themes.php`)
- Default theme: "HenSoft-TV Realistic-Centered SHOWBOX"
- Theme IDs must match exactly with Stalker Portal theme names
- No API method available from Stalker Portal for dynamic theme fetching (as of v5.2)

---

### Update Reseller Accounts Theme (Bulk)
**Endpoint:** `POST /update_reseller_accounts_theme.php`

**Description:** Bulk update themes for all accounts under a specific reseller.

**Version:** Added in v1.7.4

**Permissions:** Super admin only

**Request Body:**
```json
{
  "reseller_id": "5",
  "theme": "HenSoft-TV Realistic-Dark"
}
```

**Success Response:**
```json
{
  "error": 0,
  "err_msg": "Theme updated successfully for all 10 accounts.",
  "details": {
    "total": 10,
    "updated": 10,
    "failed": 0
  }
}
```

**Partial Success Response:**
```json
{
  "error": 0,
  "warning": 1,
  "err_msg": "Theme updated for 8/10 accounts. 2 failed.",
  "details": {
    "total": 10,
    "updated": 8,
    "failed": 2,
    "errors": [
      "account1: Connection timeout",
      "account2: Invalid response"
    ]
  }
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Missing required fields"
}
```

**Usage:**
- Standalone endpoint for bulk theme updates (currently for future use)
- Main theme propagation happens automatically via `update_reseller.php` when theme changes
- Useful for manual bulk updates or re-sync operations
- Each account update includes 0.1 second delay to prevent server overload
- Comprehensive error logging for troubleshooting

**Automatic Propagation (v1.7.4):**
- Theme propagation is now built into `update_reseller.php`
- When admin changes reseller theme in Edit Reseller form, propagation happens automatically
- No need to call this endpoint separately for normal workflow
- This endpoint remains available for manual/bulk operations

---

## Push Notifications

### Get VAPID Public Key
**Endpoint:** `GET /api/get_vapid_key.php`

**Description:** Returns the VAPID public key needed for push notification subscription.

**Response:**
```json
{
  "error": 0,
  "publicKey": "BI8Gdm9PK3LeO2mvhV9yt5NzIBFhSrlKRbfHbaDFfvMqJGmI0T0R-huUK7yeo6aPoasqBnu7SLjNUjqb4J_j5L0"
}
```

---

### Check Subscription Status
**Endpoint:** `GET /api/push_subscribe.php`

**Description:** Check if the current user has push notification subscriptions.

**Headers:** Requires active session cookie.

**Response:**
```json
{
  "error": 0,
  "subscribed": true,
  "count": 1
}
```

---

### Subscribe to Push Notifications
**Endpoint:** `POST /api/push_subscribe.php`

**Description:** Subscribe to push notifications. Creates a new subscription or updates an existing one.

**Important (v1.11.65):** If the endpoint already exists in the database, the `user_id` is updated to the current session user. This ensures proper notification routing when multiple users share the same device.

**Headers:** Requires active session cookie.

**Request Body:**
```json
{
  "endpoint": "https://web.push.apple.com/...",
  "keys": {
    "p256dh": "BCUMMfFN9hJhT-OHBzsECLWdkHhhL9...",
    "auth": "Bq6Z4f648pjawdPm6QoQ"
  }
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Subscribed to notifications"
}
```

**Behavior:**
- New endpoint: Creates new subscription record
- Existing endpoint: Updates `user_id`, `p256dh`, `auth`, and `user_agent`

---

### Unsubscribe from Push Notifications
**Endpoint:** `DELETE /api/push_subscribe.php`

**Description:** Unsubscribe from push notifications.

**Headers:** Requires active session cookie.

**Request Body (optional):**
```json
{
  "endpoint": "https://web.push.apple.com/..."
}
```

If no endpoint is provided, all subscriptions for the user are removed.

**Response:**
```json
{
  "error": 0,
  "message": "Unsubscribed from notifications"
}
```

---

### Push Notification Triggers

Push notifications are automatically sent when accounts are created, renewed, or expired.

#### 1. New Account Created (v1.11.66)
- **Triggered in:** `api/add_account.php`
- **Notification:** "📱 {Actor} added: {FullName} ({Plan})"
- **Recipients:** Super Admin + Reseller Admins + Actor (the reseller who created it)

#### 2. Account Renewed (v1.11.66)
- **Triggered in:** `api/edit_account.php`
- **Notification:** "🔄 {Actor} renewed: {FullName} ({Plan}) until {Date}"
- **Recipients:** Super Admin + Reseller Admins + Actor (the reseller who renewed it)

#### 3. Account Expired (v1.11.48)
- **Triggered by:** `api/cron_check_expired.php` (cron job every 10 minutes)
- **Notification:** "⚠️ {FullName} has expired ({Date})"
- **Recipients:** Reseller Admins + Account Owner (NOT super admin)

**Notes:**
- v1.11.66: Resellers now receive notifications for their own actions
- v1.11.65: Subscription syncs with current user on login (fixes cross-user notification issue)
- Expiry notifications are only sent once per account per expiry date (tracked in `_push_expiry_tracking`)

---

## Error Codes

### Standard Error Response Format
```json
{
  "error": 1,
  "err_msg": "Error description here"
}
```

### Common Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 0 | Success | Operation completed successfully |
| 1 | General Error | Generic error occurred |
| 2 | Authentication Failed | Invalid credentials |
| 3 | Permission Denied | Insufficient permissions |
| 4 | Not Found | Resource not found |
| 5 | Already Exists | Duplicate entry |
| 6 | Invalid Input | Validation failed |
| 7 | Database Error | Database operation failed |
| 8 | API Error | External API call failed |

---

## Request/Response Examples

### Example 1: Create Account with Full Details

**Request:**
```bash
curl -X POST http://localhost:8000/add_account.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=xxx" \
  -d '{
    "username": "premium_user_001",
    "password": "secure_pass_123",
    "mac": "00:1A:79:12:34:56",
    "full_name": "Premium Customer",
    "email": "premium@customer.com",
    "tariff_plan": "1 Year Premium",
    "end_date": "2026-01-17",
    "status": 1
  }'
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Account created successfully on both servers",
  "account_id": 125,
  "stalker_response": {
    "server_1": "success",
    "server_2": "success"
  }
}
```

---

### Example 2: Sync Accounts from Stalker Portal

**Request:**
```bash
curl -X POST http://localhost:8000/sync_accounts.php \
  -H "Cookie: PHPSESSID=xxx"
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "",
  "synced": 3450,
  "skipped": 0,
  "total_accounts": 3450,
  "sync_time": "15.3 seconds"
}
```

---

### Example 3: Update Account Expiration

**Request:**
```bash
curl -X POST http://localhost:8000/update_account.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=xxx" \
  -d '{
    "username": "premium_user_001",
    "end_date": "2026-06-17",
    "tariff_plan": "Extended Premium"
  }'
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Account updated successfully",
  "previous_end_date": "2026-01-17",
  "new_end_date": "2026-06-17",
  "days_extended": 151
}
```

---

## Authentication Flow

1. **User Login** → `POST /login.php`
2. **Session Created** → Server sets `PHPSESSID` cookie
3. **Authenticated Requests** → Include session cookie in all subsequent requests
4. **Session Validation** → Server checks session on each request
5. **Logout** → `GET /logout.php` destroys session

---

## Rate Limiting

**Current Implementation:** No rate limiting

**Recommended for Production:**
- Login: 5 attempts per minute per IP
- API Calls: 100 requests per minute per user
- Sync: 1 request per 5 minutes

---

## Security Best Practices

1. **Always use HTTPS** in production
2. **Validate all inputs** server-side
3. **Use prepared statements** for SQL queries (implemented)
4. **Implement CSRF tokens** for state-changing operations
5. **Enable SSL verification** in cURL requests
6. **Log all critical operations** for audit trail
7. **Implement rate limiting** to prevent abuse
8. **Sanitize outputs** to prevent XSS (implemented)

---

## Expiry Reminder System (v1.7.8)

### Send Expiry Reminders
**Endpoint:** `POST /send_expiry_reminders.php`

**Description:** Send expiry reminder messages to all accounts expiring on a specific target date (today + configured days before expiry).

**Version:** Added in v1.7.8

**Permissions:** Super admin OR users with `can_control_stb` permission

**Behavior:**
- Calculates target expiry date based on user's configured "days before expiry" setting
- Finds all active accounts (status=1) with end_date matching target date
- Sends personalized messages to each account via Stalker Portal
- Tracks sent reminders in database to prevent duplicates
- Rate-limited sending (300ms delay between messages)
- Filters by ownership for non-admin users

**Request:**
```
POST /send_expiry_reminders.php
```

**Response:**
```json
{
  "error": 0,
  "sent": 15,
  "skipped": 3,
  "failed": 1,
  "total": 19,
  "days_before": 7,
  "target_date": "2025-11-29",
  "results": [
    {
      "account": "john_smith",
      "full_name": "John Smith",
      "mac": "00:1A:79:AB:CD:EF",
      "expiry_date": "2025-11-29",
      "status": "sent",
      "message": "Your subscription expires in 7 days..."
    },
    {
      "account": "jane_doe",
      "full_name": "Jane Doe",
      "status": "skipped",
      "reason": "Already sent reminder for this expiry date"
    },
    {
      "account": "bob_jones",
      "full_name": "Bob Jones",
      "status": "failed",
      "error": "MAC address not found on server"
    }
  ]
}
```

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. You need STB control permission to send reminders."
}
```

---

### Update Reminder Settings
**Endpoint:** `POST /update_reminder_settings.php`

**Description:** Configure expiry reminder preferences for the current user.

**Version:** Added in v1.7.8

**Permissions:** Super admin OR users with `can_control_stb` permission

**Request Body:**
```json
{
  "days_before_expiry": 7,
  "message_template": "Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.",
  "auto_send_enabled": 0
}
```

**Template Variables:**
- `{days}` - Number of days until expiration
- `{name}` - Customer's full name
- `{username}` - Customer's username
- `{date}` - Expiration date (YYYY-MM-DD)

**Response:**
```json
{
  "error": 0,
  "message": "Reminder settings updated successfully",
  "settings": {
    "days_before_expiry": 7,
    "message_template": "Your subscription expires in {days} days...",
    "auto_send_enabled": 0
  }
}
```

**Validation:**
- `days_before_expiry`: Must be between 1 and 90
- `message_template`: Required, cannot be empty

---

### Get Reminder Settings
**Endpoint:** `GET /get_reminder_settings.php`

**Description:** Retrieve current reminder configuration for logged-in user.

**Version:** Added in v1.7.8

**Permissions:** Authenticated users only

**Response:**
```json
{
  "error": 0,
  "settings": {
    "days_before_expiry": 7,
    "message_template": "Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.",
    "auto_send_enabled": 0,
    "last_sweep_at": "2025-11-22 14:30:00"
  }
}
```

**Default Settings (if none configured):**
```json
{
  "error": 0,
  "settings": {
    "days_before_expiry": 7,
    "message_template": "Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.",
    "auto_send_enabled": 0,
    "last_sweep_at": null
  }
}
```

### Get Reminder History
**Endpoint:** `GET /get_reminder_history.php`

**Description:** Retrieve sent reminder history for a specific date with statistics and filtering.

**Version:** Added in v1.7.8

**Permissions:** Super admin OR users with `can_control_stb` permission

**Query Parameters:**
- `date` (optional): Date to retrieve history for (YYYY-MM-DD format, defaults to today)

**Request:**
```
GET /get_reminder_history.php?date=2025-11-22
```

**Response:**
```json
{
  "error": 0,
  "date": "2025-11-22",
  "total": 25,
  "sent": 22,
  "failed": 3,
  "reminders": [
    {
      "id": 123,
      "account_id": 456,
      "mac": "00:1A:79:AB:CD:EF",
      "username": "john_smith",
      "full_name": "John Smith",
      "end_date": "2025-11-29",
      "days_before": 7,
      "reminder_date": "2025-11-22",
      "sent_at": "2025-11-22 09:15:30",
      "sent_by": 1,
      "message": "Dear John Smith, your subscription expires in 7 days...",
      "status": "sent",
      "error_message": null,
      "reseller": 1
    },
    {
      "id": 124,
      "mac": "00:1A:79:12:34:56",
      "username": "jane_doe",
      "full_name": "Jane Doe",
      "end_date": "2025-11-29",
      "days_before": 7,
      "sent_at": "2025-11-22 09:16:15",
      "status": "failed",
      "error_message": "MAC address not found on server"
    }
  ]
}
```

**Empty Response (no reminders for date):**
```json
{
  "error": 0,
  "date": "2025-11-20",
  "total": 0,
  "sent": 0,
  "failed": 0,
  "reminders": []
}
```

**Filtering Behavior:**
- **Super Admin**: Sees all reminders for the date
- **Reseller Admin**: Sees all reminders for the date
- **Regular Reseller**: Only sees reminders they sent (`sent_by = user_id`)

**Error Response:**
```json
{
  "error": 1,
  "err_msg": "Permission denied. You need STB control permission."
}
```

**Validation:**
- `date` must be in YYYY-MM-DD format
- Invalid date format returns error

---

## Changelog

### Version 1.11.22 (November 2025)
- **Auto-Logout Timeout Fix**: Fixed timeout comparison (`>=` instead of `>`)
- **Session Heartbeat Improvement**: Removed initial heartbeat on page load to prevent timer reset
- **Documentation**: Complete documentation update for all features

### Version 1.11.21 (November 2025)
- **Server-Side Session Timeout**: Added PHP session timeout check on page load
- **Session Expired Message**: Added "session expired" notification on login page
- **Session Heartbeat API**: New endpoint for session activity tracking

### Version 1.11.20 (November 2025)
- **Auto-Logout Feature**: Automatic session timeout after inactivity
- **Auto-Logout Settings API**: New endpoint for configuring timeout
- **App Settings Table**: New `_app_settings` table for global settings
- **Activity Tracking**: JavaScript activity detection with throttled heartbeat

### Version 1.11.19 (November 2025)
- **WebAuthn Biometric Login**: Face ID / Touch ID / Windows Hello support
- **PWA Auto-Login**: Biometric authentication auto-starts in PWA mode
- **WebAuthn Register API**: New endpoint for credential registration
- **WebAuthn Authenticate API**: New endpoint for biometric login
- **WebAuthn Manage API**: New endpoint for credential management
- **Biometric Credentials Table**: New `_webauthn_credentials` database table

### Version 1.7.8 (November 2025)
- **Added Expiry Reminder System** (Churn Prevention)
- **New Messaging Tab**: Dedicated tab for all messaging features
- New endpoint: `POST /send_expiry_reminders.php` - Send reminders to expiring accounts
- New endpoint: `POST /update_reminder_settings.php` - Configure reminder settings
- New endpoint: `GET /get_reminder_settings.php` - Retrieve reminder configuration
- New endpoint: `GET /get_reminder_history.php` - Browse reminder history by date
- New database tables: `_expiry_reminders`, `_reminder_settings`
- **Reminder History Log**: Date-based browsing with calendar navigation, statistics, and detailed audit trail
- **Auto-Send Toggle**: Enable/disable automated daily reminders via cron job
- Template variable support: {days}, {name}, {username}, {date}
- **MAC-based Deduplication**: Prevents duplicate reminders even after account sync
- Rate-limited batch processing (300ms delay)
- PWA notification integration via service worker
- Permission-based access (STB control required)
- **Bug Fixes**: Added missing `send_message()` function, fixed reminder persistence after account sync

### Version 1.7.2 (November 2025)
- Added STB Device Control section
- Documented `send_stb_event.php` endpoint with 8 event types
- Documented `send_stb_message.php` endpoint
- Added technical details for Stalker Portal integration
- Added event table with descriptions and parameters
- Documented phone number support in account endpoints
- Added `assign_reseller.php` endpoint documentation
- Added `get_tariffs.php` endpoint documentation

### Version 1.7.1 (November 2025)
- Added phone number field to account management endpoints
- Updated GET `/get_accounts.php` with new fields
- Updated POST `/add_account.php` with phone parameter
- Documented reseller_name and plan_name fields

### Version 1.7.0 (November 2025)
- Added account-to-reseller assignment endpoint
- Documented assign_reseller.php API
- Added query parameters for viewAllAccounts

### Version 1.0.0 (January 2025)
- Initial API documentation
- All core endpoints documented
- Added request/response examples
- Added error code reference

---

## Support

For API support:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin
- **Documentation**: README.md

---

**Document Version:** 1.11.22
**Last Updated:** November 25, 2025
**Maintained by:** ShowBox Development Team
