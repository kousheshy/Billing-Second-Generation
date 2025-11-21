# ShowBox Billing Panel - API Documentation

Complete reference for all API endpoints in the ShowBox Billing Panel.

**Version:** 1.0.0
**Last Updated:** January 2025
**Base URL:** `http://your-domain.com/`

---

## Table of Contents

1. [Authentication](#authentication)
2. [Account Management](#account-management)
3. [Reseller Management](#reseller-management)
4. [Plan Management](#plan-management)
5. [Transaction Management](#transaction-management)
6. [User Management](#user-management)
7. [Stalker Portal Integration](#stalker-portal-integration)
8. [Error Codes](#error-codes)

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

## Account Management

### Get All Accounts
**Endpoint:** `GET /get_accounts.php`

**Description:** Retrieve all accounts (filtered by reseller for non-admin users).

**Headers:** Requires active session.

**Response:**
```json
{
  "error": 0,
  "err_msg": "",
  "accounts": [
    {
      "id": 1,
      "username": "user001",
      "email": "user@example.com",
      "mac": "00:1A:79:XX:XX:XX",
      "full_name": "John Doe",
      "tariff_plan": "1 Month Plan",
      "end_date": "2025-02-15 23:59:59",
      "status": 1,
      "reseller": 1,
      "timestamp": 1705449600
    }
  ]
}
```

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
  "tariff_plan": "1 Month Plan",
  "end_date": "2025-02-15",
  "status": 1
}
```

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

**Description:** Update reseller details.

**Request Body:**
```json
{
  "reseller_id": 2,
  "full_name": "Reseller One Updated",
  "email": "new@example.com",
  "max_users": 200,
  "currency": "USD"
}
```

**Response:**
```json
{
  "error": 0,
  "err_msg": "Reseller updated successfully"
}
```

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

## Changelog

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

**Document Version:** 1.0.0
**Last Updated:** January 2025
**Maintained by:** ShowBox Development Team
