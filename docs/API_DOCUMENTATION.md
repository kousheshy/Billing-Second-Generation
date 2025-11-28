# ShowBox Billing Panel - API Documentation

Complete reference for all API endpoints in the ShowBox Billing Panel.

**Version:** 1.17.3
**Last Updated:** November 28, 2025
**Base URL:** `http://your-domain.com/`

---

## Table of Contents

1. [Authentication](#authentication)
2. [WebAuthn / Biometric Authentication](#webauthn--biometric-authentication)
3. [Session Management](#session-management)
4. [Account Management](#account-management)
5. [Reseller Management](#reseller-management)
6. [Plan Management](#plan-management)
7. [Reseller Payments & Balance](#reseller-payments--balance) **NEW in v1.17.0**
8. [Transaction Management](#transaction-management)
9. [Accounting & Monthly Invoices](#accounting--monthly-invoices)
10. [User Management](#user-management)
11. [Stalker Portal Integration](#stalker-portal-integration)
12. [STB Device Control](#stb-device-control)
13. [Theme Management](#theme-management)
14. [Push Notifications](#push-notifications)
15. [Audit Log](#audit-log)
16. [Login History](#login-history)
17. [Error Codes](#error-codes)

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
  "first_name": "John",
  "last_name": "Doe",
  "email": "user@example.com",
  "phone": "+447712345678",
  "tariff_plan": "1 Month Plan",
  "end_date": "2025-02-15",
  "status": 1,
  "plan": 1
}
```

**Field Changes (v1.17.3):**
- `first_name` (required) - Customer's first name
- `last_name` (required) - Customer's last name
- Backend combines: `full_name = first_name + " " + last_name`
- Combined `full_name` sent to Stalker Portal and stored in `_accounts.full_name`

**Legacy Fields:**
- `phone` (optional) - Customer phone number, sent to Stalker Portal and saved locally (v1.7.1)

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

## Reseller Payments & Balance

**Added in v1.17.0** - Track reseller payments and calculate running balance.

### Add Reseller Payment
**Endpoint:** `POST /api/add_reseller_payment.php`

**Description:** Record a new payment from a reseller. Only Admin and Reseller Admin can add payments.

**Request Body (multipart/form-data):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| reseller_id | int | Yes | ID of the reseller who made the payment |
| amount | decimal | Yes | Payment amount (positive number) |
| currency | string | Yes | Currency code: IRR, GBP, USD, EUR |
| payment_date | string | Yes | Date in YYYY-MM-DD format |
| bank_name | string | Yes | Bank name (from Iranian banks list) |
| reference_number | string | No | Bank reference/tracking number |
| description | string | No | Notes about the payment |
| receipt | file | No | Receipt image (JPG, PNG, GIF, PDF) |

**Response:**
```json
{
  "error": 0,
  "message": "Payment recorded successfully",
  "payment_id": 123,
  "payment": {
    "id": 123,
    "reseller_id": 5,
    "reseller_name": "John Reseller",
    "amount": 5000000,
    "currency": "IRR",
    "payment_date": "2025-11-27",
    "bank_name": "بانک ملت",
    "reference_number": "123456789",
    "receipt_path": "uploads/receipts/receipt_5_20251127.jpg",
    "recorded_by": "admin",
    "status": "active"
  }
}
```

---

### Get Reseller Payments
**Endpoint:** `GET /api/get_reseller_payments.php`

**Description:** Retrieve payment history. Admin sees all; resellers see only their own.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| reseller_id | int | No | Filter by specific reseller |
| start_date | string | No | Filter from date (YYYY-MM-DD) |
| end_date | string | No | Filter to date (YYYY-MM-DD) |
| status | string | No | Filter by status: active, cancelled, all |
| limit | int | No | Number of records (default: 100, max: 500) |

**Response:**
```json
{
  "error": 0,
  "payments": [
    {
      "id": 123,
      "reseller_id": 5,
      "reseller_name": "John Reseller",
      "amount": "5000000.00",
      "currency": "IRR",
      "payment_date": "2025-11-27",
      "payment_date_shamsi": "1404/09/07",
      "bank_name": "بانک ملت",
      "reference_number": "123456789",
      "status": "active",
      "recorded_by": "admin"
    }
  ],
  "summary": {
    "total_active": 5000000,
    "total_cancelled": 0,
    "count_active": 1,
    "count_cancelled": 0
  },
  "can_edit": true,
  "can_view_all": true
}
```

---

### Get Reseller Balance
**Endpoint:** `GET /api/get_reseller_balance.php`

**Description:** Calculate running balance for reseller(s).

**Formula:** `Balance = Total Sales - Total Payments`
- Positive = Reseller owes money (بدهکار)
- Negative = Reseller has credit (طلبکار)

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| reseller_id | int | No | Specific reseller (or all if admin) |
| year | int | No | Filter by year for yearly report |
| month | int | No | Filter by month for monthly report |

**Response:**
```json
{
  "error": 0,
  "balances": [
    {
      "reseller_id": 5,
      "reseller_name": "John Reseller",
      "reseller_username": "john",
      "currency": "IRR",
      "opening_balance": 0,
      "total_sales": 10000000,
      "total_payments": 5000000,
      "balance": 5000000,
      "closing_balance": 5000000,
      "transaction_count": 15,
      "payment_count": 1,
      "status": "debtor"
    }
  ],
  "grand_totals": {
    "total_sales": 10000000,
    "total_payments": 5000000,
    "balance": 5000000
  },
  "period": {
    "year": null,
    "month": null,
    "type": "all_time"
  }
}
```

---

### Cancel Reseller Payment
**Endpoint:** `POST /api/cancel_reseller_payment.php`

**Description:** Cancel (soft delete) a payment. Requires mandatory reason. Only Admin and Reseller Admin can cancel.

**Request Body:**
```json
{
  "payment_id": 123,
  "reason": "Duplicate entry - payment was recorded twice"
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Payment cancelled successfully",
  "payment_id": 123
}
```

**Error Response (reason missing):**
```json
{
  "error": 1,
  "message": "Cancellation reason is MANDATORY"
}
```

---

### Get Iranian Banks
**Endpoint:** `GET /api/get_iranian_banks.php`

**Description:** Get list of Iranian banks for the payment form dropdown.

**Response:**
```json
{
  "error": 0,
  "banks": [
    {"code": "BMELLI", "name_fa": "بانک ملی ایران", "name_en": "Bank Melli Iran"},
    {"code": "BSEPAH", "name_fa": "بانک سپه", "name_en": "Bank Sepah"},
    {"code": "BMELLAT", "name_fa": "بانک ملت", "name_en": "Bank Mellat"},
    {"code": "CASH", "name_fa": "نقدی", "name_en": "Cash"},
    {"code": "OTHER", "name_fa": "سایر", "name_en": "Other"}
  ],
  "source": "database"
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

### Edit Transaction (Correction)
**Endpoint:** `POST /api/edit_transaction.php`

**Version:** Added in v1.16.0

**Description:** Add corrections to existing transactions. Transactions are NEVER deleted - only corrected with mandatory comments. This implements immutable financial records for accounting compliance.

**Permissions:**
| Role | Access |
|------|--------|
| Super Admin | Full edit access |
| Reseller Admin | Full edit access |
| Reseller | READ-ONLY (cannot edit) |
| Observer | READ-ONLY (cannot edit) |

**Request Body:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `transaction_id` | int | Yes | ID of the transaction to correct |
| `correction_amount` | decimal | No | Amount to add/subtract (positive=increase, negative=decrease) |
| `correction_note` | string | **Yes** | Mandatory explanation for the correction |
| `status` | string | No | `active`, `corrected`, or `voided` (default: auto-determined) |

**Example Request - Add Correction:**
```json
{
  "transaction_id": 42,
  "correction_amount": -50000,
  "correction_note": "Refund for service issue on customer request"
}
```

**Example Request - Void Transaction:**
```json
{
  "transaction_id": 42,
  "correction_note": "Transaction voided - duplicate entry",
  "status": "voided"
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Transaction corrected successfully",
  "transaction": {
    "id": 42,
    "original_amount": -90000000,
    "correction_amount": -50000,
    "net_amount": -90050000,
    "status": "corrected",
    "correction_note": "Refund for service issue on customer request",
    "corrected_by": "admin",
    "corrected_at": "2025-11-27 14:30:00"
  }
}
```

**Net Amount Calculation:**
- `net_amount = original_amount + correction_amount`
- If `status = 'voided'`: `net_amount = 0`
- If no correction: `net_amount = original_amount`

**Error Responses:**

*Not logged in:*
```json
{
  "error": 1,
  "message": "Not logged in"
}
```

*Permission denied:*
```json
{
  "error": 1,
  "message": "Permission denied. Only Admin or Reseller Admin can edit transactions."
}
```

*Observer attempting edit:*
```json
{
  "error": 1,
  "message": "Observers cannot edit transactions. This is a read-only account."
}
```

*Missing correction note:*
```json
{
  "error": 1,
  "message": "Correction note is MANDATORY. Please explain why this correction is being made."
}
```

*Transaction not found:*
```json
{
  "error": 1,
  "message": "Transaction not found"
}
```

**Audit Trail:**
All corrections are automatically logged to `_audit_log` table with:
- Action type: `update`
- Target type: `transaction`
- Old values (before correction)
- New values (after correction)
- Details including correction note

---

## Accounting & Monthly Invoices

### Get Monthly Invoice
**Endpoint:** `GET /api/get_monthly_invoice.php`

**Description:** Generate monthly invoice data for a specific reseller. Returns sales transactions (new accounts and renewals) for the selected period. Supports both Gregorian and Persian (Shamsi/Jalali) calendars.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `reseller_id` | int | Yes | ID of the reseller |
| `calendar` | string | No | `gregorian` (default) or `shamsi` |
| `year` | int | Yes | Year number (e.g., 2024 or 1403) |
| `month` | int | Yes | Month number (1-12) |

**Example Request:**
```
GET /api/get_monthly_invoice.php?reseller_id=5&calendar=shamsi&year=1403&month=9
```

**Response:**
```json
{
  "error": 0,
  "invoice": {
    "period": {
      "calendar": "shamsi",
      "year": 1403,
      "month": 9,
      "display": "آذر 1403",
      "display_en": "آذر 1403 (Shamsi)",
      "start_date": "2024-11-21",
      "end_date": "2024-12-20"
    },
    "reseller": {
      "id": 5,
      "name": "Reseller Name",
      "username": "reseller1",
      "email": "reseller@example.com",
      "currency": "IRR",
      "currency_symbol": "IRR ",
      "current_balance": 500000
    },
    "summary": {
      "new_accounts": 3,
      "renewals": 12,
      "total_transactions": 15,
      "total_sales": 1500000,
      "total_sales_formatted": "1,500,000",
      "amount_owed": 1500000,
      "amount_owed_formatted": "1,500,000"
    },
    "transactions": [
      {
        "id": 1234,
        "date_gregorian": "2024-11-22",
        "date_shamsi": "1403/09/02",
        "time": "14:30",
        "mac_address": "00:1A:79:XX:XX:XX",
        "amount": 100000,
        "currency": "IRR",
        "description": "Account renewal: user123 - Plan: Monthly"
      }
    ]
  }
}
```

**Access Control:**
| User Type | Access |
|-----------|--------|
| Super Admin | All resellers |
| Reseller Admin | All resellers |
| Observer | All resellers (read-only) |
| Regular Reseller | Own data only |

**Notes:**
- Only includes debit transactions (sales)
- Excludes admin credit adjustments
- MAC address is looked up from `_accounts` table for renewal transactions
- Shamsi dates are calculated using standard Jalali conversion algorithms

**Error Response:**
```json
{
  "error": 1,
  "message": "Missing required parameters (reseller_id, year, month)"
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

## Audit Log

**Added in v1.13.0** | **Extended in v1.14.0**

The audit log provides a permanent, immutable record of all critical administrative actions.

### Get Audit Log
**Endpoint:** `GET /api/get_audit_log.php`

**Description:** Retrieve paginated audit log entries with optional filters.

**Access:** Super Admin only (super_user = 1)

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | int | No | Page number (default: 1) |
| per_page | int | No | Entries per page (default: 10, max: 100) |
| action | string | No | Filter by action type |
| target_type | string | No | Filter by target type |
| date_from | string | No | Start date (YYYY-MM-DD) |
| date_to | string | No | End date (YYYY-MM-DD) |

**Response:**
```json
{
  "error": 0,
  "logs": [
    {
      "id": 1,
      "user_id": 1,
      "username": "admin",
      "action": "create",
      "target_type": "account",
      "target_id": "AA:BB:CC:DD:EE:FF",
      "target_name": "John Doe",
      "old_value": null,
      "new_value": "{\"mac\":\"AA:BB:CC:DD:EE:FF\",\"plan\":\"Monthly\"}",
      "details": "New account created for John Doe",
      "ip_address": "192.168.1.100",
      "created_at": "2025-11-27 10:30:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 150,
    "total_pages": 15
  }
}
```

**Audited Actions (v1.14.0)**

| Action | Target Type | Description |
|--------|-------------|-------------|
| create | account | New account created |
| update | account | Account details modified |
| delete | account | Account removed |
| send | stb_message | Message sent to STB device(s) |
| create | user | New reseller created |
| delete | user | Reseller removed |
| update | credit | Reseller credit adjusted |
| update | password | User password changed |
| update | account_status | Account enabled/disabled |

---

## Login History

**Added in v1.11.22**

### Get Login History
**Endpoint:** `GET /api/get_login_history.php`

**Description:** Retrieve paginated login history for the current user or all users (admin).

**Access:** All authenticated users (own history), Super Admin for all users

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | int | No | Page number (default: 1) |
| per_page | int | No | Entries per page (default: 10) |
| user_id | int | No | Filter by user ID (admin only) |

**Response:**
```json
{
  "error": 0,
  "history": [
    {
      "id": 1,
      "user_id": 1,
      "username": "admin",
      "login_time": "2025-11-27 10:30:00",
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)",
      "device_info": "Chrome on Mac",
      "login_method": "password",
      "status": "success",
      "failure_reason": null
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 50,
    "total_pages": 5
  }
}
```

**Login Methods:**
- `password`: Standard username/password
- `biometric`: Face ID / Touch ID (WebAuthn)

**Status Values:**
- `success`: Successful login
- `failed`: Failed login attempt

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
