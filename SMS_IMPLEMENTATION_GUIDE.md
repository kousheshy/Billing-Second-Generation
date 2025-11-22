# SMS Messaging System - Implementation Guide

**Version:** 1.8.0
**Date:** November 2025
**Feature:** Complete SMS notification system using Faraz SMS (IPPanel Edge) API

---

## Overview

This guide documents the complete SMS messaging system implementation for ShowBox Billing Panel. The system integrates with Faraz SMS API to send SMS notifications to customers automatically (expiry reminders) and manually.

---

## Features Implemented

### 1. SMS Configuration
- API token and sender number management
- Automatic expiry SMS toggle
- Configurable days before expiry (1-30 days)
- Custom message templates with variable support

### 2. Manual SMS Sending
- **Send to Number**: Send SMS to individual phone numbers
- **Send to Accounts**: Send SMS to multiple selected accounts
- Template selection and message personalization
- Character counter (500 max)

### 3. Automatic Expiry Reminders
- Daily cron job to send automatic reminders
- Sends SMS N days before account expiry
- Supports message variables: `{name}`, `{mac}`, `{expiry_date}`, `{days}`
- Prevents duplicate messages

### 4. SMS History & Tracking
- Complete SMS sending history
- Filter by status (sent/failed/pending)
- Filter by type (manual/expiry_reminder/renewal/new_account)
- Search by name, phone number, or MAC address
- Pagination support

### 5. SMS Statistics
- Total SMS sent
- Successful deliveries
- Failed deliveries
- Pending messages

---

## Database Schema

### Table 1: _sms_settings

Stores SMS API configuration for each user.

```sql
CREATE TABLE `_sms_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `api_token` VARCHAR(500) DEFAULT NULL,
    `sender_number` VARCHAR(20) DEFAULT NULL,
    `base_url` VARCHAR(200) DEFAULT 'https://edge.ippanel.com/v1',
    `auto_send_enabled` TINYINT(1) DEFAULT 0,
    `days_before_expiry` INT(11) DEFAULT 7,
    `expiry_template` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Key Fields:**
- `api_token`: Faraz SMS API authentication token
- `sender_number`: Phone number in E.164 format (e.g., +983000505)
- `auto_send_enabled`: 1 = automatic expiry reminders enabled
- `days_before_expiry`: Number of days before expiry to send SMS
- `expiry_template`: Message template with variables

### Table 2: _sms_logs

Tracks all SMS messages sent through the system.

```sql
CREATE TABLE `_sms_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) DEFAULT NULL,
    `mac` VARCHAR(17) DEFAULT NULL,
    `recipient_name` VARCHAR(200) DEFAULT NULL,
    `recipient_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `message_type` ENUM('manual', 'expiry_reminder', 'renewal', 'new_account') DEFAULT 'manual',
    `sent_by` INT(11) NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    `api_response` TEXT,
    `bulk_id` VARCHAR(100) DEFAULT NULL,
    `error_message` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `account_id` (`account_id`),
    KEY `mac` (`mac`),
    KEY `recipient_number` (`recipient_number`),
    KEY `sent_by` (`sent_by`),
    KEY `sent_at` (`sent_at`),
    KEY `message_type` (`message_type`),
    KEY `status` (`status`),
    FOREIGN KEY (`sent_by`) REFERENCES `_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Key Fields:**
- `message_type`: Type of SMS (manual, expiry_reminder, renewal, new_account)
- `status`: Delivery status (sent, failed, pending)
- `bulk_id`: Faraz SMS API bulk ID for tracking
- `api_response`: Full API response for debugging

### Table 3: _sms_templates

Stores reusable SMS message templates.

```sql
CREATE TABLE `_sms_templates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `template` TEXT NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Default Templates:**
1. **Expiry Reminder**: Sent before account expiry
2. **New Account Welcome**: Sent when account is created
3. **Renewal Confirmation**: Sent when account is renewed
4. **Payment Reminder**: General payment reminder

---

## Files Created

### Backend PHP Files

1. **create_sms_tables.php**
   - Creates all 3 SMS database tables
   - Inserts default settings and templates
   - Run once during initial setup

2. **get_sms_settings.php**
   - Returns SMS configuration for logged-in user
   - Returns SMS templates list
   - Returns SMS statistics

3. **update_sms_settings.php**
   - Updates SMS API configuration
   - Validates input (days between 1-30)
   - Upserts settings (insert or update)

4. **send_sms.php**
   - Main SMS sending endpoint
   - Supports two modes:
     - `manual`: Send to single phone number
     - `accounts`: Send to multiple accounts
   - Integrates with Faraz SMS API
   - Logs all SMS to database
   - Supports message personalization

5. **get_sms_logs.php**
   - Returns SMS sending history
   - Supports pagination
   - Supports filtering by status and type
   - Shows sender information

6. **cron_send_expiry_sms.php**
   - Cron job for automatic expiry reminders
   - Finds accounts expiring in N days
   - Sends personalized SMS to each
   - Prevents duplicate messages
   - Logs all activities

### Frontend Files

7. **sms-functions.js**
   - Complete SMS JavaScript functionality
   - Tab switching (STB vs SMS messages)
   - SMS mode switching (manual vs accounts)
   - Settings management
   - SMS sending functions
   - History loading and filtering
   - Pagination logic
   - Character counting
   - Statistics display

8. **dashboard.html** (updated)
   - Added SMS tab navigation
   - SMS configuration section
   - Send SMS section (manual & accounts modes)
   - SMS history table
   - SMS statistics cards

9. **dashboard.css** (updated)
   - Messaging tab styles
   - SMS tab styles
   - Statistics card styles
   - Account selection styles

---

## Setup Instructions

### Step 1: Create Database Tables

Run the table creation script:

```bash
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"
php create_sms_tables.php
```

**Expected Output:**
```
════════════════════════════════════════════════════════════════
  Creating SMS Tables for ShowBox Billing Panel
  Version: 1.8.0
════════════════════════════════════════════════════════════════

[1/3] Creating _sms_settings table...
    ✓ _sms_settings table created
[2/3] Creating _sms_logs table...
    ✓ _sms_logs table created
[3/3] Creating _sms_templates table...
    ✓ _sms_templates table created

✅ SMS TABLES CREATED SUCCESSFULLY!
```

### Step 2: Get Faraz SMS API Credentials

1. Login to [Faraz SMS Dashboard](https://sms.farazsms.com/dashboard)
2. Navigate to **Developer** section
3. Generate an API token
4. Note your sender phone number

### Step 3: Configure SMS in Dashboard

1. Login to ShowBox Billing Panel
2. Go to **Messaging** tab
3. Click **SMS Messages** sub-tab
4. Fill in SMS Configuration:
   - **API Token**: Your Faraz SMS API token
   - **Sender Number**: Your sender number (e.g., +983000505)
   - **Enable Automatic Expiry SMS**: Toggle ON if you want automatic reminders
   - **Days Before Expiry**: Set to 7 (or your preference)
   - **Expiry Message Template**: Customize the message
5. Click **Save SMS Configuration**

### Step 4: Set Up Automatic Reminders (Optional)

If you enabled automatic expiry SMS, configure a cron job:

**On Linux/Ubuntu Server:**

```bash
crontab -e
```

Add this line (runs daily at 9:00 AM):

```cron
0 9 * * * /usr/bin/php /var/www/showbox/cron_send_expiry_sms.php >> /var/log/sms-reminders.log 2>&1
```

**On Local Development (macOS):**

Create a launchd plist or run manually for testing:

```bash
php cron_send_expiry_sms.php
```

---

## Usage Guide

### Send Manual SMS to a Number

1. Go to **Messaging** → **SMS Messages**
2. Click **Send to Number** tab
3. Enter recipient phone number (E.164 format, e.g., +989120000000)
4. Type your message (max 500 characters)
5. Click **📱 Send SMS**

### Send SMS to Multiple Accounts

1. Go to **Messaging** → **SMS Messages**
2. Click **Send to Accounts** tab
3. Select accounts from the list (only accounts with phone numbers are shown)
4. Optionally select a template or type custom message
5. Use variables for personalization:
   - `{name}` → Customer full name
   - `{mac}` → MAC address
   - `{expiry_date}` → Account expiry date
6. Click **📱 Send SMS to Selected Accounts**

### View SMS History

1. Go to **Messaging** → **SMS Messages**
2. Scroll to **SMS History** section
3. Select date or click **Today**
4. Use filters:
   - **Search**: By name, phone number, or MAC
   - **Status**: All / Sent / Failed / Pending
   - **Type**: All / Manual / Expiry Reminder / Renewal / New Account
5. Click **Refresh** to reload

### Monitor SMS Statistics

Statistics are shown at the bottom of SMS Messages tab:
- **Total Sent**: All SMS sent by this user
- **Successful**: Successfully delivered
- **Failed**: Failed deliveries
- **Pending**: Waiting for delivery confirmation

---

## Faraz SMS API Integration

### API Endpoint

```
POST https://edge.ippanel.com/v1/api/send
```

### Authentication

```
Authorization: YOUR_API_TOKEN
Content-Type: application/json
```

### Request Format

```json
{
  "sending_type": "webservice",
  "from_number": "+983000505",
  "message": "Your message here",
  "params": {
    "recipients": ["+989120000000", "+989350000000"]
  }
}
```

### Response Format (Success)

```json
{
  "status": "success",
  "code": "200-1",
  "data": {
    "bulk_id": "12345678",
    "message_ids": [...]
  }
}
```

### Response Format (Error)

```json
{
  "status": "error",
  "code": "401",
  "message": "Invalid authentication credentials"
}
```

### Error Codes

- **401**: Invalid or expired token
- **422**: Missing required fields
- **429**: Rate limit exceeded
- **500**: Server error

---

## Message Variables

You can use these variables in SMS templates and they will be automatically replaced:

| Variable | Description | Example |
|----------|-------------|---------|
| `{name}` | Customer full name | John Smith |
| `{mac}` | Device MAC address | 00:1A:79:12:34:56 |
| `{expiry_date}` | Account expiry date | 2025-12-31 |
| `{days}` | Days until expiry | 7 |

**Example Template:**
```
Dear {name}, your ShowBox subscription expires in {days} days on {expiry_date}. Please renew to continue enjoying our service. Contact: 00447736932888
```

**Personalized Output:**
```
Dear John Smith, your ShowBox subscription expires in 7 days on 2025-12-31. Please renew to continue enjoying our service. Contact: 00447736932888
```

---

## Automatic Expiry Reminders

### How It Works

1. Cron job runs daily (configured time)
2. Finds all users with `auto_send_enabled = 1`
3. For each user:
   - Calculates target date: `today + days_before_expiry`
   - Finds all accounts expiring on that date
   - Filters accounts with phone numbers
   - Checks for duplicates (prevents re-sending)
   - Sends personalized SMS to each account
   - Logs success/failure to database

### Cron Script Output

```
════════════════════════════════════════════════════════════════
  Automatic Expiry SMS Reminder - 2025-11-22 09:00:00
════════════════════════════════════════════════════════════════

Found 1 user(s) with automatic SMS enabled.

Processing user ID: 1 (Send 7 days before expiry)
  Found 5 account(s) expiring on 2025-11-29
  ✓ SMS sent successfully to 5 recipient(s)

════════════════════════════════════════════════════════════════
  Summary:
  ✓ Sent: 5
  ✗ Failed: 0
════════════════════════════════════════════════════════════════
```

---

## Troubleshooting

### Issue 1: SMS Configuration Not Saving

**Symptoms:**
- Error message when clicking "Save SMS Configuration"

**Solutions:**
1. Check database connection in `config.php`
2. Verify `_sms_settings` table exists
3. Check browser console for JavaScript errors

### Issue 2: SMS Sending Fails

**Symptoms:**
- "SMS failed" error when sending

**Possible Causes:**
1. **Invalid API Token**
   - Verify token in Faraz SMS Dashboard
   - Re-generate token if expired

2. **Invalid Sender Number**
   - Must be in E.164 format (+983000505)
   - Must be registered in your Faraz SMS account

3. **Invalid Recipient Number**
   - Must be in E.164 format (+989120000000)
   - Check for correct country code

4. **API Connection Issue**
   - Test API connectivity: `curl -I https://edge.ippanel.com`
   - Check server firewall settings

**Debug Steps:**
```bash
# Check SMS logs for error details
mysql -u root -p
USE showboxt_panel;
SELECT * FROM _sms_logs WHERE status = 'failed' ORDER BY sent_at DESC LIMIT 10;
```

### Issue 3: Automatic Reminders Not Sending

**Symptoms:**
- Cron job runs but no SMS sent

**Solutions:**
1. **Check cron job is running:**
   ```bash
   crontab -l  # List cron jobs
   tail -f /var/log/sms-reminders.log  # View log
   ```

2. **Manually test cron script:**
   ```bash
   php cron_send_expiry_sms.php
   ```

3. **Verify auto-send is enabled:**
   ```sql
   SELECT * FROM _sms_settings WHERE auto_send_enabled = 1;
   ```

4. **Check for accounts expiring:**
   ```sql
   SELECT * FROM _accounts
   WHERE end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
   AND phone IS NOT NULL;
   ```

### Issue 4: Duplicate SMS Sent

**Symptoms:**
- Customers receiving multiple SMS for same expiry

**Solutions:**
The system prevents duplicates by checking:
- Same MAC address
- Same end date
- Same days_before value
- Sent today with status 'sent'

If duplicates occur:
1. Check cron job isn't running multiple times
2. Verify unique key in `_sms_logs` table:
   ```sql
   SHOW KEYS FROM _sms_logs WHERE Key_name = 'unique_reminder_mac';
   ```

### Issue 5: Character Limit Exceeded

**Symptoms:**
- Message cut off or send fails

**Solution:**
- SMS messages are limited to 500 characters
- Character counter shows remaining space
- Reduce message length or split into multiple SMS

---

## Security Considerations

### 1. API Token Storage

API tokens are stored in the database. Consider:
- Encrypting tokens before storage
- Restricting database access
- Using environment variables for additional security

### 2. Phone Number Validation

Always validate phone numbers:
- Must match E.164 format
- Include country code
- No spaces or special characters

### 3. Rate Limiting

Faraz SMS API has rate limits:
- Monitor bulk sending
- Implement delays between batches if needed
- Check API response for rate limit errors

### 4. Permission Control

Only users with messaging permission can access SMS:
- Check `can_access_messaging` permission flag
- Verify in both frontend and backend

---

## Cost Estimation

SMS costs vary by provider and destination. Example:

- **Local SMS (Iran)**: ~0.002-0.005 USD per SMS
- **International SMS**: ~0.05-0.15 USD per SMS

**Monthly estimate for 1000 customers:**
- 1 reminder per month per customer
- 1000 SMS × 0.003 USD = **$3/month**

Check current pricing with Faraz SMS.

---

## Future Enhancements

Potential improvements:
1. **Delivery Reports**: Track individual message delivery status
2. **Scheduled SMS**: Schedule SMS for specific date/time
3. **SMS Templates Editor**: Visual template editor in dashboard
4. **Bulk Import**: Import phone numbers from CSV
5. **SMS Credits Display**: Show remaining SMS balance
6. **Multi-language Support**: Send SMS in different languages
7. **MMS Support**: Send images with messages
8. **Two-way SMS**: Receive replies from customers

---

## API Reference

### get_sms_settings.php

**Method:** GET
**Authentication:** Session required
**Response:**
```json
{
  "error": 0,
  "settings": {
    "api_token": "...",
    "sender_number": "+983000505",
    "auto_send_enabled": 1,
    "days_before_expiry": 7,
    "expiry_template": "..."
  },
  "templates": [...],
  "stats": {
    "total_sent": 150,
    "successful": 145,
    "failed": 3,
    "pending": 2
  }
}
```

### update_sms_settings.php

**Method:** POST
**Authentication:** Session required
**Parameters:**
- `api_token` (string)
- `sender_number` (string)
- `base_url` (string)
- `auto_send_enabled` (0|1)
- `days_before_expiry` (1-30)
- `expiry_template` (text)

**Response:**
```json
{
  "error": 0,
  "message": "SMS settings updated successfully"
}
```

### send_sms.php

**Method:** POST
**Authentication:** Session required
**Parameters:**
- `send_type` ("manual"|"accounts")
- `message` (string, max 500)
- `recipient_number` (string, for manual mode)
- `account_ids` (array, for accounts mode)

**Response:**
```json
{
  "error": 0,
  "message": "SMS sent successfully to 5 recipient(s)",
  "sent_count": 5,
  "failed_count": 0,
  "bulk_id": "12345678"
}
```

### get_sms_logs.php

**Method:** GET
**Authentication:** Session required
**Parameters:**
- `page` (int, default 1)
- `limit` (int, default 50)
- `status` (string, optional)
- `type` (string, optional)

**Response:**
```json
{
  "error": 0,
  "logs": [...],
  "pagination": {
    "page": 1,
    "limit": 50,
    "total": 150,
    "pages": 3
  }
}
```

---

## Support

For issues or questions:
1. Check this documentation
2. Review API logs in `_sms_logs` table
3. Test API connection manually
4. Contact Faraz SMS support for API issues

---

## Version History

**v1.8.0** (November 2025)
- Initial SMS messaging system implementation
- Faraz SMS API integration
- Automatic expiry reminders
- Manual SMS sending
- SMS history and statistics

---

**END OF SMS IMPLEMENTATION GUIDE**
