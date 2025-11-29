# Deployment Summary - v1.18.2

**Date:** November 29, 2025
**Release Type:** Feature Release
**Feature:** Telegram Notification System

---

## Overview

This deployment introduces a comprehensive Telegram notification system for the ShowBox Billing Panel. Users can link their Telegram accounts to receive automatic notifications about account events.

---

## Pre-Deployment Checklist

- [ ] Backup database
- [ ] Backup current files
- [ ] Verify Telegram Bot API access from server
- [ ] Ensure database migration script is ready

---

## Files Changed

### New Files

| File | Description |
|------|-------------|
| `api/telegram_helper.php` | Core Telegram functions |
| `api/telegram/get_settings.php` | Get Telegram settings API |
| `api/telegram/link_telegram.php` | Link/unlink Telegram account |
| `api/telegram/update_notification_settings.php` | Save notification preferences |
| `api/telegram/save_bot_settings.php` | Save bot token (admin only) |
| `api/telegram/send_message.php` | Send manual messages |
| `api/telegram/get_logs.php` | Get message history |
| `api/telegram/webhook.php` | Webhook handler for /start |
| `telegram-functions.js` | Frontend JavaScript |
| `scripts/create_telegram_tables.php` | Database migration |

### Modified Files

| File | Changes |
|------|---------|
| `dashboard.php` | Added Telegram tab UI, updated version to 1.18.2 |
| `dashboard.js` | Added Telegram initialization |
| `sms-functions.js` | Added Telegram tab handler |
| `api/add_account.php` | Added telegram_helper.php include |
| `api/edit_account.php` | Added telegram_helper.php include |
| `service-worker.js` | Updated cache version to v1.18.2 |
| `index.html` | Updated version to v1.18.2 |
| `README.md` | Updated version badge |
| `docs/API_DOCUMENTATION.md` | Added Telegram API section |
| `docs/CHANGELOG.md` | Added v1.18.2 changelog |
| `config.php` (server) | Updated Server 2 IP |

---

## Database Changes

### New Tables

```sql
-- Telegram Bot Settings
CREATE TABLE IF NOT EXISTS _telegram_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_token VARCHAR(100),
    bot_username VARCHAR(100),
    webhook_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notification Preferences per User
CREATE TABLE IF NOT EXISTS _telegram_notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notify_new_account TINYINT(1) DEFAULT 1,
    notify_renewal TINYINT(1) DEFAULT 1,
    notify_expiry TINYINT(1) DEFAULT 1,
    notify_expired TINYINT(1) DEFAULT 1,
    notify_low_balance TINYINT(1) DEFAULT 1,
    notify_new_payment TINYINT(1) DEFAULT 1,
    notify_login TINYINT(1) DEFAULT 0,
    notify_daily_report TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id)
);

-- Message Templates
CREATE TABLE IF NOT EXISTS _telegram_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    template_key VARCHAR(50) UNIQUE NOT NULL,
    template TEXT NOT NULL,
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Message Logs
CREATE TABLE IF NOT EXISTS _telegram_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    chat_id VARCHAR(50),
    message TEXT,
    message_type VARCHAR(50),
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    telegram_message_id VARCHAR(50),
    account_id INT,
    account_mac VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);
```

### User Table Modifications

```sql
ALTER TABLE _users ADD COLUMN telegram_chat_id VARCHAR(50) DEFAULT NULL;
ALTER TABLE _users ADD COLUMN telegram_linked_at TIMESTAMP NULL DEFAULT NULL;
```

---

## Configuration Changes

### Server Config (config.php)

```php
// Updated Server 2 IP
$SERVER_2_ADDRESS = "http://81.12.70.4";
$WEBSERVICE_2_BASE_URL = "http://81.12.70.4/stalker_portal/api/";
```

---

## Deployment Steps

### 1. Database Migration

```bash
# SSH to server
ssh root@192.168.15.230

# Run migration
php /var/www/showbox/scripts/create_telegram_tables.php
```

### 2. Deploy Files

```bash
# From local machine
sshpass -p 'PASSWORD' scp -r \
  dashboard.php \
  dashboard.js \
  sms-functions.js \
  telegram-functions.js \
  service-worker.js \
  index.html \
  root@192.168.15.230:/var/www/showbox/

# Deploy API files
sshpass -p 'PASSWORD' scp -r \
  api/telegram_helper.php \
  api/add_account.php \
  api/edit_account.php \
  root@192.168.15.230:/var/www/showbox/api/

# Deploy Telegram API directory
sshpass -p 'PASSWORD' scp -r \
  api/telegram/* \
  root@192.168.15.230:/var/www/showbox/api/telegram/

# Deploy docs
sshpass -p 'PASSWORD' scp -r \
  docs/* \
  root@192.168.15.230:/var/www/showbox/docs/
```

### 3. Set File Permissions

```bash
ssh root@192.168.15.230 "chmod 644 /var/www/showbox/api/telegram/*.php"
ssh root@192.168.15.230 "chmod 644 /var/www/showbox/api/telegram_helper.php"
```

### 4. Verify Telegram Connectivity

```bash
# Test from server
curl -s "https://api.telegram.org/bot8243087847:AAGJf5V27tmefuxQBzMbhW4WEOjKPG6vats/getMe"
```

### 5. Clear Cache

- Users: Press Ctrl+Shift+R to hard refresh
- PWA Users: Uninstall and reinstall the app

---

## Post-Deployment Verification

### Checklist

- [ ] Login page shows v1.18.2
- [ ] Dashboard header shows v1.18.2
- [ ] Telegram tab visible in Messaging section
- [ ] Admin can configure bot token
- [ ] Users can link Telegram account
- [ ] Notification preferences can be saved
- [ ] Test notification sent successfully
- [ ] Account creation triggers Telegram notification
- [ ] Account renewal triggers Telegram notification

### Test Commands

```bash
# Verify API endpoint
curl -s "https://billing.apamehnet.com/api/telegram/get_settings.php" \
  -H "Cookie: PHPSESSID=YOUR_SESSION"

# Test Telegram message
curl -s "https://api.telegram.org/bot8243087847:AAGJf5V27tmefuxQBzMbhW4WEOjKPG6vats/sendMessage" \
  -d "chat_id=1301477515" \
  -d "text=Test message from ShowBox"
```

---

## Rollback Plan

If issues occur:

1. Restore database backup
2. Restore file backup
3. Clear browser cache
4. Notify users of service interruption

---

## Known Issues

1. **Webhook not functional**: Server in Iran cannot receive inbound Telegram connections
   - Workaround: Users use @userinfobot to get Chat ID

2. **Telegram API requires proxy**: Outbound connections to Telegram may need VPN/proxy
   - Resolved: VPN configured on server

---

## Support Contacts

- Technical Issues: Check server logs at `/var/log/apache2/error.log`
- Telegram Bot: @ShowBox_TelegramBot
- Admin Chat ID: 1301477515

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.18.2 | 2025-11-29 | Telegram notification system |
| 1.18.1 | 2025-11-28 | File permission hotfix |
| 1.18.0 | 2025-11-28 | Email notification system |
