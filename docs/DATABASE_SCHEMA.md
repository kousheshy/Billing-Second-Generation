# ShowBox Billing Panel - Database Schema

Complete database schema documentation for the ShowBox Billing Panel.

**Version:** 1.16.3
**Last Updated:** November 27, 2025
**Database:** MySQL 5.7+ / MariaDB 10.3+
**Character Set:** utf8mb4
**Collation:** utf8mb4_unicode_ci

---

## Quick Setup

### For New Installations

Run the complete setup script:

```bash
cd /path/to/showbox
php scripts/setup_complete_database.php
```

This will:
1. Create the database if it doesn't exist
2. Create all 18 required tables
3. Insert default data (currencies, admin user, settings)
4. Display confirmation and next steps

### Prerequisites

1. **MySQL 5.7+** or **MariaDB 10.3+**
2. **config.php** properly configured:
   ```php
   $ub_db_host = 'localhost';
   $ub_main_db = 'showboxt_panel';
   $ub_db_username = 'your_db_user';
   $ub_db_password = 'your_db_password';
   ```
3. Database user with CREATE/ALTER/INSERT privileges

---

## Table of Contents

1. [Overview](#overview)
2. [Quick Reference](#quick-reference)
3. [Core Tables](#core-tables)
4. [SMS Tables](#sms-tables)
5. [Security Tables](#security-tables)
6. [Settings Tables](#settings-tables)
7. [Entity Relationships](#entity-relationships)
8. [Migration Scripts](#migration-scripts)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The ShowBox Billing Panel uses **18 tables** organized into functional groups:

| Group | Tables | Purpose |
|-------|--------|---------|
| Core | 6 tables | Users, accounts, plans, transactions, currencies, reminders |
| SMS | 4 tables | SMS settings, logs, templates, tracking |
| Security | 3 tables | WebAuthn, login history, audit log |
| Push | 2 tables | Push subscriptions, expiry tracking |
| Settings | 3 tables | Reminder settings, Stalker settings, app settings |

**Database Name:** `showboxt_panel`
**Engine:** InnoDB (all tables)
**Transaction Support:** Yes

---

## Quick Reference

### All Tables at a Glance

| Table | Purpose | Key Fields | Added |
|-------|---------|------------|-------|
| `_users` | Admins & resellers | id, username, password, super_user | Core |
| `_accounts` | IPTV customer accounts | id, mac, end_date, reseller | Core |
| `_plans` | Subscription plans | id, name, duration_days, price_* | Core |
| `_transactions` | Financial transactions | id, for_user, type, amount | Core |
| `_currencies` | Currency definitions | id, code, symbol | Core |
| `_expiry_reminders` | STB reminder history | id, mac, end_date, sent_at | Core |
| `_reminder_settings` | Per-user reminder config | id, user_id, days_before_expiry | v1.7.8 |
| `_sms_settings` | SMS API configuration | id, user_id, api_token | v1.8.0 |
| `_sms_logs` | SMS sending history | id, recipient_number, status | v1.8.0 |
| `_sms_templates` | Message templates | id, user_id, name, template | v1.8.0 |
| `_sms_reminder_tracking` | Multi-stage SMS tracking | id, account_id, reminder_stage | v1.9.0 |
| `_stalker_settings` | Stalker Portal config | id, setting_key, setting_value | v1.6.0 |
| `_webauthn_credentials` | Biometric login | id, user_id, credential_id | v1.11.19 |
| `_login_history` | Login attempt tracking | id, user_id, login_time, status | v1.12.0 |
| `_audit_log` | Permanent action log | id, action, target_type | v1.13.0 |
| `_push_subscriptions` | Web push subscriptions | id, user_id, endpoint | v1.11.40 |
| `_push_expiry_tracking` | Expiry notification tracking | id, account_id, expiry_date | v1.11.48 |
| `_app_settings` | Global application settings | id, setting_key, setting_value | v1.11.20 |

---

## Core Tables

### 1. _users

**Purpose:** Store admin users and resellers

```sql
CREATE TABLE `_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(200) DEFAULT NULL,
    `full_name` VARCHAR(200) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `super_user` TINYINT(1) DEFAULT 0,
    `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0|0|0',
    `is_reseller_admin` TINYINT(1) DEFAULT 0,
    `is_observer` TINYINT(1) DEFAULT 0,
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `currency` VARCHAR(10) DEFAULT 'GBP',
    `max_users` INT(11) DEFAULT 0,
    `theme` VARCHAR(50) DEFAULT 'dark',
    `timestamp` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Fields:**
| Field | Description |
|-------|-------------|
| `super_user` | 1 = Super Admin, 0 = Reseller |
| `permissions` | Pipe-separated: can_edit\|can_add\|is_reseller_admin\|can_delete\|can_send_stb\|can_view_stb\|reserved |
| `is_observer` | 1 = Read-only mode |
| `balance` | Reseller credit balance |
| `max_users` | Max accounts reseller can create (0 = unlimited) |

---

### 2. _accounts

**Purpose:** Store IPTV customer accounts (synced from Stalker Portal)

```sql
CREATE TABLE `_accounts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `mac` VARCHAR(17) NOT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `full_name` VARCHAR(200) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` TINYINT(1) DEFAULT 1,
    `reseller` INT(11) DEFAULT NULL,
    `plan_id` INT(11) DEFAULT NULL,
    `tariff_plan` VARCHAR(200) DEFAULT NULL,
    `stb_type` VARCHAR(50) DEFAULT NULL,
    `server` TINYINT(1) DEFAULT 1,
    `timestamp` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mac` (`mac`),
    INDEX `idx_reseller` (`reseller`),
    INDEX `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Fields:**
| Field | Description |
|-------|-------------|
| `mac` | MAC address in format XX:XX:XX:XX:XX:XX |
| `end_date` | Subscription expiry date |
| `status` | 1 = Active, 0 = Inactive |
| `reseller` | FK to _users.id (owner) |
| `server` | 1 = Server 1, 2 = Server 2 |

---

### 3. _plans

**Purpose:** Subscription plan definitions

```sql
CREATE TABLE `_plans` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `tariff_id` INT(11) DEFAULT NULL,
    `duration_days` INT(11) NOT NULL DEFAULT 30,
    `price_gbp` DECIMAL(10,2) DEFAULT 0.00,
    `price_usd` DECIMAL(10,2) DEFAULT 0.00,
    `price_eur` DECIMAL(10,2) DEFAULT 0.00,
    `price_irr` DECIMAL(15,2) DEFAULT 0.00,
    `category` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 4. _transactions

**Purpose:** Financial transaction history with immutable correction support (v1.16.0)

```sql
CREATE TABLE `_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `for_user` INT(11) NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'GBP',
    `description` TEXT,
    `details` TEXT DEFAULT NULL,
    `related_account` INT(11) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `timestamp` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- v1.16.0: Correction columns for immutable financial records
    `correction_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Correction amount (positive=increase, negative=decrease)',
    `correction_note` TEXT DEFAULT NULL COMMENT 'Mandatory note explaining the correction',
    `corrected_by` INT(11) DEFAULT NULL COMMENT 'User ID who made the correction',
    `corrected_by_username` VARCHAR(100) DEFAULT NULL COMMENT 'Username who made the correction',
    `corrected_at` DATETIME DEFAULT NULL COMMENT 'When the correction was made',
    `status` ENUM('active','corrected','voided') DEFAULT 'active' COMMENT 'Transaction status',
    PRIMARY KEY (`id`),
    INDEX `idx_for_user` (`for_user`),
    INDEX `idx_status` (`status`),
    INDEX `idx_corrected_at` (`corrected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Fields:**
| Field | Description |
|-------|-------------|
| `amount` | Original transaction amount |
| `correction_amount` | Amount to add/subtract (positive=increase, negative=decrease) |
| `correction_note` | **MANDATORY** explanation for correction |
| `corrected_by` | User ID who made the correction |
| `corrected_by_username` | Username for display purposes |
| `corrected_at` | Timestamp when correction was made |
| `status` | `active` (normal), `corrected` (has correction), `voided` (nullified) |

**Net Amount Calculation:**
- `net_amount = amount + correction_amount`
- If `status = 'voided'`, net amount should be treated as 0
- Original `amount` is NEVER modified (immutable)

**Migration Script:** `scripts/add_transaction_corrections.php`

---

### 5. _currencies

**Purpose:** Currency definitions

```sql
CREATE TABLE `_currencies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(10) NOT NULL,
    `symbol` VARCHAR(10) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Default Data:**
| Code | Symbol | Name |
|------|--------|------|
| GBP | £ | British Pound |
| USD | $ | US Dollar |
| EUR | € | Euro |
| IRR | ﷼ | Iranian Rial |

---

### 6. _expiry_reminders

**Purpose:** STB expiry reminder history

```sql
CREATE TABLE `_expiry_reminders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) NOT NULL,
    `mac` VARCHAR(17) NOT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `full_name` VARCHAR(200) DEFAULT NULL,
    `end_date` DATE NOT NULL,
    `days_before` INT(11) NOT NULL,
    `reminder_date` DATE NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `sent_by` INT(11) NOT NULL,
    `message` TEXT,
    `status` ENUM('sent', 'failed') DEFAULT 'sent',
    `error_message` TEXT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_reminder` (`mac`, `end_date`, `days_before`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## SMS Tables

### 7. _sms_settings

**Purpose:** Per-user SMS API configuration

```sql
CREATE TABLE `_sms_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `api_token` VARCHAR(500) DEFAULT NULL,
    `sender_number` VARCHAR(20) DEFAULT NULL,
    `base_url` VARCHAR(200) DEFAULT 'https://edge.ippanel.com/v1',
    `auto_send_enabled` TINYINT(1) DEFAULT 0,
    `enable_multistage_reminders` TINYINT(1) DEFAULT 1,
    `days_before_expiry` INT(11) DEFAULT 7,
    `expiry_template` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8. _sms_logs

**Purpose:** SMS sending history

```sql
CREATE TABLE `_sms_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) DEFAULT NULL,
    `mac` VARCHAR(17) DEFAULT NULL,
    `recipient_name` VARCHAR(200) DEFAULT NULL,
    `recipient_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `message_type` ENUM('manual', 'expiry_reminder', 'renewal', 'new_account', 'welcome') DEFAULT 'manual',
    `sent_by` INT(11) NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    `api_response` TEXT,
    `bulk_id` VARCHAR(100) DEFAULT NULL,
    `error_message` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sent_at` (`sent_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 9. _sms_templates

**Purpose:** Reusable SMS message templates

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
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Template Variables:**
- `{name}` - Customer full name
- `{mac}` - MAC address
- `{expiry_date}` - Expiration date
- `{days}` - Days until expiry

---

### 10. _sms_reminder_tracking

**Purpose:** Track multi-stage SMS reminders (7/3/1 day)

```sql
CREATE TABLE `_sms_reminder_tracking` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `mac` VARCHAR(20) DEFAULT NULL,
    `reminder_stage` ENUM('7days', '3days', '1day', 'expired') NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `end_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_reminder` (`account_id`, `reminder_stage`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Security Tables

### 11. _webauthn_credentials

**Purpose:** Store biometric/WebAuthn credentials

```sql
CREATE TABLE `_webauthn_credentials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `credential_id` TEXT NOT NULL,
    `public_key` TEXT NOT NULL,
    `counter` INT DEFAULT 0,
    `device_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_used` TIMESTAMP NULL,
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 12. _login_history

**Purpose:** Track login attempts

```sql
CREATE TABLE `_login_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `login_time` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `login_method` VARCHAR(50) DEFAULT 'password',
    `status` ENUM('success', 'failed') DEFAULT 'success',
    `failure_reason` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Login Methods:**
- `password` - Standard username/password
- `biometric` - Face ID / Touch ID (WebAuthn)

---

### 13. _audit_log

**Purpose:** Permanent audit trail (CANNOT BE DELETED)

```sql
CREATE TABLE `_audit_log` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_id` INT(11) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `user_type` VARCHAR(50) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(100) NOT NULL,
    `target_id` VARCHAR(255) DEFAULT NULL,
    `target_name` VARCHAR(255) DEFAULT NULL,
    `old_value` JSON DEFAULT NULL,
    `new_value` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_action` (`action`),
    INDEX `idx_target_type` (`target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Audited Actions (v1.14.0):**
| Action | Target Type | Description |
|--------|-------------|-------------|
| create | account | New account created |
| update | account | Account modified |
| delete | account | Account removed |
| send | stb_message | Message sent to device |
| create | user | Reseller created |
| delete | user | Reseller deleted |
| update | credit | Credit adjusted |
| update | password | Password changed |
| update | account_status | Account enabled/disabled |

---

## Settings Tables

### 14. _reminder_settings

**Purpose:** Per-user STB reminder configuration

```sql
CREATE TABLE `_reminder_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `days_before_expiry` INT(11) NOT NULL DEFAULT 7,
    `message_template` TEXT,
    `auto_send_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_sweep_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 15. _stalker_settings

**Purpose:** Stalker Portal connection settings

```sql
CREATE TABLE `_stalker_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Required Settings:**
| Key | Description |
|-----|-------------|
| server_address | Primary Stalker server URL |
| server_2_address | Secondary server URL |
| api_username | Stalker API username |
| api_password | Stalker API password |
| api_base_url | Primary API base URL |
| api_2_base_url | Secondary API base URL |

---

### 16. _app_settings

**Purpose:** Global application settings

```sql
CREATE TABLE `_app_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Default Settings:**
| Key | Default | Description |
|-----|---------|-------------|
| auto_logout_timeout | 5 | Auto-logout after N minutes of inactivity |

---

## Push Notification Tables

### 17. _push_subscriptions

**Purpose:** Web push notification subscriptions

```sql
CREATE TABLE `_push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `endpoint` TEXT NOT NULL,
    `p256dh` TEXT NOT NULL,
    `auth` TEXT NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 18. _push_expiry_tracking

**Purpose:** Track which expiry notifications have been sent

```sql
CREATE TABLE `_push_expiry_tracking` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) NOT NULL,
    `expiry_date` DATE NOT NULL,
    `notified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_account_expiry` (`account_id`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Entity Relationships

```
┌─────────────────────────────────────────────────────────────┐
│                         _users                               │
│  id (PK) ◄────────────────────────────────────────────────┐  │
└──────────┬──────────────────────────────────────────────────┘
           │
           │ 1:N (reseller owns accounts)
           │
           ▼
┌─────────────────────────────────────────────────────────────┐
│                        _accounts                             │
│  id (PK), reseller (FK→_users.id), plan_id (FK→_plans.id)   │
└──────────┬──────────────────────────────────────────────────┘
           │
           │ N:1
           │
           ▼
┌─────────────────────────────────────────────────────────────┐
│                         _plans                               │
│  id (PK)                                                     │
└─────────────────────────────────────────────────────────────┘

Related tables (FK to _users.id):
  - _transactions.for_user
  - _sms_settings.user_id
  - _sms_templates.user_id
  - _reminder_settings.user_id
  - _webauthn_credentials.user_id
  - _login_history.user_id
  - _audit_log.user_id
  - _push_subscriptions.user_id
```

---

## Migration Scripts

Located in `/scripts/` directory:

| Script | Purpose | Run Order |
|--------|---------|-----------|
| `setup_complete_database.php` | **Full setup (new installations)** | 1 |
| `create_database_schema.php` | Legacy core tables | - |
| `create_sms_tables.php` | SMS tables | - |
| `create_login_history_table.php` | Login history | - |
| `create_audit_log_table.php` | Audit log | - |
| `create_stalker_settings_table.php` | Stalker settings | - |
| `upgrade_multistage_reminders.php` | Multi-stage SMS | - |
| `add_phone_column.php` | Add phone to accounts | - |
| `initialize_reseller_sms.php` | Init SMS for resellers | - |

**For new installations:** Just run `setup_complete_database.php`

**For upgrades:** Run individual scripts as needed based on your current version.

---

## Troubleshooting

### Common Issues

**1. "Table doesn't exist" errors**
```bash
# Run the complete setup script
php scripts/setup_complete_database.php
```

**2. "Access denied" errors**
- Check `config.php` credentials
- Verify database user has proper privileges:
```sql
GRANT ALL PRIVILEGES ON showboxt_panel.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

**3. "Unknown column" errors**
- Your database is outdated. Run migration scripts in order.
- Or backup data and run fresh setup.

**4. Character encoding issues**
```sql
ALTER DATABASE showboxt_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Verify Database Structure

```bash
# Connect to MySQL
mysql -u root -p showboxt_panel

# List all tables
SHOW TABLES;

# Check table structure
DESCRIBE _users;
DESCRIBE _accounts;
```

### Reset Database (DANGER!)

Only use if you want to start fresh:

```sql
DROP DATABASE showboxt_panel;
CREATE DATABASE showboxt_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run: `php scripts/setup_complete_database.php`

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.16.0 | 2025-11-27 | Added transaction correction columns (correction_amount, correction_note, corrected_by, corrected_by_username, corrected_at, status) with indexes |
| 1.15.3 | 2025-11-27 | Account deletion with balance refund (superseded by v1.16.0 immutable records) |
| 1.15.2 | 2025-11-27 | Documentation sync (no schema changes) |
| 1.15.1 | 2025-11-27 | Documentation sync (no schema changes) |
| 1.14.0 | 2025-11-27 | Added complete setup script, comprehensive documentation |
| 1.13.0 | 2025-11-27 | Added _audit_log table |
| 1.12.0 | 2025-11-27 | Added _login_history table |
| 1.11.40 | 2025-11-25 | Added _push_subscriptions table |
| 1.11.20 | 2025-11-25 | Added _app_settings table |
| 1.11.19 | 2025-11-25 | Added _webauthn_credentials table |
| 1.9.0 | 2025-11-23 | Added _sms_reminder_tracking table |
| 1.8.0 | 2025-11-22 | Added SMS tables |
| 1.7.8 | 2025-11-21 | Added reminder tables |
| 1.6.0 | 2025-11-20 | Added _stalker_settings table |
