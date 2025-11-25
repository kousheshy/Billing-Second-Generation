# ShowBox Billing Panel - Database Schema

Complete database schema documentation for the ShowBox Billing Panel.

**Version:** 1.11.22
**Last Updated:** November 25, 2025
**Database:** MySQL 5.7+
**Character Set:** UTF8MB4
**Collation:** utf8mb4_unicode_ci

---

## Table of Contents

1. [Overview](#overview)
2. [Entity-Relationship Diagram](#entity-relationship-diagram)
3. [Tables](#tables)
4. [Relationships](#relationships)
5. [Indexes](#indexes)
6. [Migration History](#migration-history)

---

## Overview

The ShowBox Billing Panel uses a MySQL relational database with 9 core tables:

| Table | Purpose | Records (Typical) |
|-------|---------|-------------------|
| `_users` | Resellers and admins | 10-100 |
| `_accounts` | Customer IPTV accounts | 1,000-50,000 |
| `_plans` | Subscription plans | 10-50 |
| `_transactions` | Financial transactions | 1,000-100,000 |
| `_currencies` | Currency definitions | 4-10 |
| `_webauthn_credentials` | Biometric login credentials (v1.11.19) | 10-500 |
| `_app_settings` | Global application settings (v1.11.20) | 1-10 |
| `_expiry_reminders` | Sent reminder tracking (v1.7.8) | 1,000-50,000 |
| `_reminder_settings` | User reminder preferences (v1.7.8) | 10-100 |

**Database Name:** `showboxt_panel`
**Engine:** InnoDB
**Transaction Support:** Yes

---

## Entity-Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                          _users                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ id (PK, INT, AUTO_INCREMENT)                              │  │
│  │ username (VARCHAR 255, UNIQUE)                            │  │
│  │ password (VARCHAR 255) [MD5 hash]                         │  │
│  │ name (VARCHAR 255)                                        │  │
│  │ email (VARCHAR 255)                                       │  │
│  │ balance (DECIMAL 10,2) [default: 0.00]                    │  │
│  │ currency (VARCHAR 10) [GBP, USD, EUR, IRR]                │  │
│  │ super_user (TINYINT 1) [0=reseller, 1=admin]              │  │
│  │ max_users (INT)                                           │  │
│  │ theme (VARCHAR 50) [light, dark]                          │  │
│  │ permissions (VARCHAR 255) [can_edit|can_add|is_admin|...]│  │
│  │ is_observer (TINYINT 1) [0=no, 1=yes]                     │  │
│  │ timestamp (INT)                                           │  │
│  └───────────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       │ 1:N
                       │
        ┌──────────────┴────────────────┐
        │                               │
        ▼                               ▼
┌──────────────────┐           ┌──────────────────┐
│  _transactions   │           │    _accounts     │
├──────────────────┤           ├──────────────────┤
│ id (PK)          │           │ id (PK)          │
│ reseller_id (FK) ├───────────┤ reseller (FK)    │
│ amount           │   N:1     │ username (UNIQUE)│
│ type             │           │ mac (UNIQUE)     │
│ description      │           │ email            │
│ timestamp        │           │ phone_number ★   │
└──────────────────┘           │ full_name        │
                               │ tariff_plan      │
                               │ end_date         │
                               │ status           │
                               │ plan (FK)        │
                               │ timestamp        │
                               └────────┬─────────┘
                                        │
                                        │ N:1
                                        │
                                        ▼
                               ┌──────────────────┐
                               │     _plans       │
                               ├──────────────────┤
                               │ id (PK)          │
                               │ name             │
                               │ days             │
                               │ price            │
                               │ currency         │
                               │ tariff_id        │
                               │ timestamp        │
                               └──────────────────┘

┌──────────────────┐
│   _currencies    │
├──────────────────┤
│ id (PK)          │
│ code (UNIQUE)    │
│ symbol           │
│ name             │
└──────────────────┘

★ = New in v1.7.1
```

---

## Tables

### 1. _users

**Purpose:** Store resellers and admin users

**Schema:**
```sql
CREATE TABLE `_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'MD5 hash',
  `name` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `balance` DECIMAL(10,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'GBP' COMMENT 'GBP, USD, EUR, IRR',
  `super_user` TINYINT(1) DEFAULT 0 COMMENT '0=reseller, 1=admin',
  `max_users` INT(11) DEFAULT 0,
  `theme` VARCHAR(50) DEFAULT 'dark' COMMENT 'light or dark',
  `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0' COMMENT 'can_edit|can_add|is_reseller_admin|can_delete|reserved',
  `is_observer` TINYINT(1) DEFAULT 0 COMMENT '0=no, 1=yes (read-only mode)',
  `timestamp` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `username` | VARCHAR(255) | NO | - | Unique username for login |
| `password` | VARCHAR(255) | NO | - | MD5 hash (upgrade to bcrypt recommended) |
| `name` | VARCHAR(255) | YES | NULL | Full name or company name |
| `email` | VARCHAR(255) | YES | NULL | Email address |
| `balance` | DECIMAL(10,2) | YES | 0.00 | Account balance/credit |
| `currency` | VARCHAR(10) | YES | GBP | Preferred currency (GBP/USD/EUR/IRR) |
| `super_user` | TINYINT(1) | YES | 0 | 0=Reseller, 1=Super Admin |
| `max_users` | INT(11) | YES | 0 | Maximum accounts allowed (0=unlimited) |
| `theme` | VARCHAR(50) | YES | dark | UI theme preference |
| `permissions` | VARCHAR(255) | YES | 0\|0\|0\|0\|0 | Pipe-separated permission flags |
| `is_observer` | TINYINT(1) | YES | 0 | Read-only observer mode |
| `timestamp` | INT(11) | NO | - | Unix timestamp of creation |

**Permission Format:**
```
can_edit|can_add|is_reseller_admin|can_delete|reserved
   0        0           0              0         0
```

**User Types:**
1. **Super Admin** (`super_user=1`): Full system access
2. **Reseller Admin** (`permissions[2]=1`): Admin features within scope
3. **Regular Reseller** (`super_user=0, permissions[2]=0`): Limited access
4. **Observer** (`is_observer=1`): Read-only access

**Default Record:**
```sql
INSERT INTO `_users` VALUES (
  1,
  'admin',
  '21232f297a57a5a743894a0e4a801fc3', -- MD5('admin')
  'Administrator',
  'admin@showbox.com',
  1000.00,
  'GBP',
  1, -- super_user
  0, -- max_users (unlimited)
  'dark',
  '1|1|1|1|0',
  0, -- not observer
  UNIX_TIMESTAMP()
);
```

---

### 2. _accounts

**Purpose:** Store IPTV customer accounts

**Schema:**
```sql
CREATE TABLE `_accounts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL,
  `mac` VARCHAR(17) NOT NULL COMMENT 'Format: 00:1A:79:XX:XX:XX',
  `email` VARCHAR(255) DEFAULT NULL,
  `phone_number` VARCHAR(50) DEFAULT NULL COMMENT 'Added in v1.7.1',
  `full_name` VARCHAR(255) DEFAULT NULL,
  `tariff_plan` VARCHAR(255) DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL COMMENT 'Expiration date',
  `status` TINYINT(1) DEFAULT 1 COMMENT '0=OFF, 1=ON',
  `reseller` INT(11) DEFAULT NULL COMMENT 'Foreign key to _users.id',
  `plan` INT(11) DEFAULT NULL COMMENT 'Foreign key to _plans.id',
  `timestamp` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `mac` (`mac`),
  KEY `reseller` (`reseller`),
  KEY `plan` (`plan`),
  KEY `end_date` (`end_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `username` | VARCHAR(255) | NO | - | Unique username for account |
| `mac` | VARCHAR(17) | NO | - | MAC address (00:1A:79:XX:XX:XX) |
| `email` | VARCHAR(255) | YES | NULL | Customer email |
| `phone_number` | VARCHAR(50) | YES | NULL | Customer phone (v1.7.1) |
| `full_name` | VARCHAR(255) | YES | NULL | Customer full name |
| `tariff_plan` | VARCHAR(255) | YES | NULL | Plan name from Stalker Portal |
| `end_date` | DATETIME | YES | NULL | Subscription expiration date |
| `status` | TINYINT(1) | YES | 1 | 0=Disabled, 1=Enabled |
| `reseller` | INT(11) | YES | NULL | FK to _users.id (owner) |
| `plan` | INT(11) | YES | NULL | FK to _plans.id |
| `timestamp` | INT(11) | NO | - | Unix timestamp of creation |

**Expiration Logic:**
- An account is **expired** if `end_date < NOW()`
- Status field (ON/OFF) is for admin control only, not expiration
- An account is "not renewed" if `end_date` remains in the past

**Example Record:**
```sql
INSERT INTO `_accounts` VALUES (
  1,
  'premium_user_001',
  '00:1A:79:12:34:56',
  'customer@example.com',
  '+447712345678', -- phone_number
  'John Smith',
  '1 Month Premium',
  '2025-12-31 23:59:59',
  1, -- status ON
  2, -- owned by reseller ID 2
  1, -- using plan ID 1
  UNIX_TIMESTAMP()
);
```

---

### 3. _plans

**Purpose:** Store subscription plans with pricing

**Schema:**
```sql
CREATE TABLE `_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `days` INT(11) NOT NULL COMMENT 'Duration in days',
  `price` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL COMMENT 'GBP, USD, EUR, IRR',
  `tariff_id` INT(11) DEFAULT NULL COMMENT 'Reference to Stalker Portal tariff',
  `timestamp` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `currency` (`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `name` | VARCHAR(255) | NO | - | Plan name (e.g., "1 Month Premium") |
| `days` | INT(11) | NO | - | Duration in days (30, 90, 365, etc.) |
| `price` | DECIMAL(10,2) | NO | - | Price in specified currency |
| `currency` | VARCHAR(10) | NO | - | Currency code (GBP/USD/EUR/IRR) |
| `tariff_id` | INT(11) | YES | NULL | Stalker Portal tariff ID |
| `timestamp` | INT(11) | NO | - | Unix timestamp of creation |

**Example Records:**
```sql
INSERT INTO `_plans` VALUES
(1, '1 Month Premium', 30, 10.00, 'GBP', 1, UNIX_TIMESTAMP()),
(2, '3 Month Premium', 90, 25.00, 'GBP', 2, UNIX_TIMESTAMP()),
(3, '1 Year Premium', 365, 80.00, 'GBP', 3, UNIX_TIMESTAMP()),
(4, '1 Month Standard', 30, 6500000.00, 'IRR', 4, UNIX_TIMESTAMP());
```

---

### 4. _transactions

**Purpose:** Store financial transaction history

**Schema:**
```sql
CREATE TABLE `_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reseller_id` INT(11) NOT NULL COMMENT 'FK to _users.id',
  `amount` DECIMAL(10,2) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'credit, debit, renewal, etc.',
  `description` TEXT DEFAULT NULL,
  `timestamp` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `reseller_id` (`reseller_id`),
  KEY `type` (`type`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `reseller_id` | INT(11) | NO | - | FK to _users.id |
| `amount` | DECIMAL(10,2) | NO | - | Transaction amount (+ or -) |
| `type` | VARCHAR(50) | NO | - | Transaction type |
| `description` | TEXT | YES | NULL | Human-readable description |
| `timestamp` | INT(11) | NO | - | Unix timestamp of transaction |

**Transaction Types:**
- `credit` - Balance added
- `debit` - Balance deducted
- `renewal` - Account renewed (balance deducted)
- `adjustment` - Manual adjustment by admin

**Example Record:**
```sql
INSERT INTO `_transactions` VALUES (
  1,
  2, -- reseller_id
  -10.00, -- deducted
  'renewal',
  'Account renewal: premium_user_001 - Plan: 1 Month Premium',
  UNIX_TIMESTAMP()
);
```

---

### 5. _currencies

**Purpose:** Store currency definitions

**Schema:**
```sql
CREATE TABLE `_currencies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(10) NOT NULL COMMENT 'ISO 4217 code',
  `symbol` VARCHAR(10) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `code` | VARCHAR(10) | NO | - | ISO 4217 currency code |
| `symbol` | VARCHAR(10) | YES | NULL | Currency symbol |
| `name` | VARCHAR(100) | NO | - | Full currency name |

**Default Records:**
```sql
INSERT INTO `_currencies` VALUES
(1, 'GBP', '£', 'British Pound Sterling'),
(2, 'USD', '$', 'United States Dollar'),
(3, 'EUR', '€', 'Euro'),
(4, 'IRR', 'IRR ', 'Iranian Rial');
```

---

### 6. _webauthn_credentials

**Purpose:** Store WebAuthn biometric credentials for passwordless login

**Version:** Added in v1.11.19

**Schema:**
```sql
CREATE TABLE IF NOT EXISTS `_webauthn_credentials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'FK to _users.id',
  `credential_id` TEXT NOT NULL COMMENT 'Base64 encoded credential ID',
  `public_key` TEXT NOT NULL COMMENT 'Base64 encoded public key',
  `counter` INT(11) DEFAULT 0 COMMENT 'Signature counter for replay protection',
  `device_name` VARCHAR(255) DEFAULT NULL COMMENT 'User-friendly device name',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_used` TIMESTAMP NULL COMMENT 'Last successful authentication',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `user_id` | INT(11) | NO | - | FK to _users.id |
| `credential_id` | TEXT | NO | - | Base64 WebAuthn credential ID |
| `public_key` | TEXT | NO | - | Base64 public key for verification |
| `counter` | INT(11) | YES | 0 | Signature counter (anti-replay) |
| `device_name` | VARCHAR(255) | YES | NULL | Device name (e.g., "iPhone 15 Pro") |
| `created_at` | TIMESTAMP | YES | CURRENT | When credential was registered |
| `last_used` | TIMESTAMP | YES | NULL | Last successful login |

**Usage:**
- One user can have multiple credentials (multi-device support)
- Credential ID is unique per device
- Counter increments with each authentication to prevent replay attacks
- Device name helps users identify which credentials to manage

**Example Record:**
```sql
INSERT INTO `_webauthn_credentials` VALUES (
  1,
  1, -- user_id (admin)
  'base64-encoded-credential-id...',
  'base64-encoded-public-key...',
  5, -- counter
  'iPhone 15 Pro',
  NOW(),
  NOW()
);
```

---

### 7. _app_settings

**Purpose:** Store global application settings (key-value pairs)

**Version:** Added in v1.11.20

**Schema:**
```sql
CREATE TABLE IF NOT EXISTS `_app_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL COMMENT 'Unique setting identifier',
  `setting_value` TEXT COMMENT 'Setting value (can be JSON)',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `setting_key` | VARCHAR(100) | NO | - | Unique setting name |
| `setting_value` | TEXT | YES | NULL | Setting value |
| `updated_at` | TIMESTAMP | YES | CURRENT | Last update time |

**Current Settings:**

| Key | Default | Description |
|-----|---------|-------------|
| `auto_logout_timeout` | 5 | Minutes of inactivity before auto-logout (0 = disabled) |

**Default Record:**
```sql
INSERT INTO `_app_settings` (setting_key, setting_value) VALUES
('auto_logout_timeout', '5');
```

**Usage:**
- Used for application-wide settings
- Super admin can modify via Settings tab
- Supports any key-value configuration

---

### 8. _expiry_reminders

**Purpose:** Track sent expiry reminders to prevent duplicates

**Version:** Added in v1.7.8

**Schema:**
```sql
CREATE TABLE IF NOT EXISTS `_expiry_reminders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) DEFAULT NULL COMMENT 'FK to _accounts.id (nullable for MAC-based tracking)',
  `mac` VARCHAR(17) NOT NULL COMMENT 'MAC address for deduplication',
  `expiry_date` DATE NOT NULL COMMENT 'Account expiry date',
  `reminder_date` DATE NOT NULL COMMENT 'When reminder was sent',
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_by` INT(11) NOT NULL COMMENT 'FK to _users.id',
  `message` TEXT COMMENT 'Message that was sent',
  `status` ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
  `error_message` TEXT COMMENT 'Error details if failed',
  PRIMARY KEY (`id`),
  INDEX `idx_mac_expiry` (`mac`, `expiry_date`),
  INDEX `idx_reminder_date` (`reminder_date`),
  INDEX `idx_sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `account_id` | INT(11) | YES | NULL | FK to _accounts.id |
| `mac` | VARCHAR(17) | NO | - | MAC address (dedup key) |
| `expiry_date` | DATE | NO | - | When account expires |
| `reminder_date` | DATE | NO | - | When reminder was sent |
| `sent_at` | TIMESTAMP | YES | CURRENT | Exact timestamp |
| `sent_by` | INT(11) | NO | - | Who sent it |
| `message` | TEXT | YES | NULL | Message content |
| `status` | ENUM | YES | sent | sent/failed/skipped |
| `error_message` | TEXT | YES | NULL | Error details |

---

### 9. _reminder_settings

**Purpose:** Store user-specific reminder preferences

**Version:** Added in v1.7.8

**Schema:**
```sql
CREATE TABLE IF NOT EXISTS `_reminder_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'FK to _users.id',
  `days_before_expiry` INT(11) DEFAULT 7,
  `message_template` TEXT,
  `auto_send_enabled` TINYINT(1) DEFAULT 0,
  `last_sweep_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**

| Field | Type | Null | Default | Description |
|-------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO | Primary key |
| `user_id` | INT(11) | NO | - | FK to _users.id |
| `days_before_expiry` | INT(11) | YES | 7 | Days before to send reminder |
| `message_template` | TEXT | YES | NULL | Custom message template |
| `auto_send_enabled` | TINYINT(1) | YES | 0 | Enable auto-send via cron |
| `last_sweep_at` | TIMESTAMP | YES | NULL | Last auto-send run |
| `updated_at` | TIMESTAMP | YES | CURRENT | Last settings update |

---

## Relationships

### Foreign Keys

```sql
-- _accounts.reseller → _users.id
ALTER TABLE `_accounts`
ADD CONSTRAINT `fk_accounts_reseller`
FOREIGN KEY (`reseller`)
REFERENCES `_users`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- _accounts.plan → _plans.id
ALTER TABLE `_accounts`
ADD CONSTRAINT `fk_accounts_plan`
FOREIGN KEY (`plan`)
REFERENCES `_plans`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- _transactions.reseller_id → _users.id
ALTER TABLE `_transactions`
ADD CONSTRAINT `fk_transactions_reseller`
FOREIGN KEY (`reseller_id`)
REFERENCES `_users`(`id`)
ON DELETE CASCADE
ON UPDATE CASCADE;
```

**Relationship Rules:**
1. **_users → _accounts (1:N)**
   - One reseller can own many accounts
   - If reseller is deleted, accounts set to NULL (orphaned)

2. **_users → _transactions (1:N)**
   - One reseller can have many transactions
   - If reseller is deleted, transactions are deleted (CASCADE)

3. **_plans → _accounts (1:N)**
   - One plan can be used by many accounts
   - If plan is deleted, accounts.plan set to NULL

---

## Indexes

### Primary Keys
- `_users.id`
- `_accounts.id`
- `_plans.id`
- `_transactions.id`
- `_currencies.id`

### Unique Indexes
- `_users.username`
- `_accounts.username`
- `_accounts.mac`
- `_currencies.code`

### Performance Indexes
```sql
-- Frequently queried fields
CREATE INDEX idx_accounts_reseller ON _accounts(reseller);
CREATE INDEX idx_accounts_plan ON _accounts(plan);
CREATE INDEX idx_accounts_end_date ON _accounts(end_date);
CREATE INDEX idx_accounts_status ON _accounts(status);

CREATE INDEX idx_transactions_reseller ON _transactions(reseller_id);
CREATE INDEX idx_transactions_type ON _transactions(type);
CREATE INDEX idx_transactions_timestamp ON _transactions(timestamp);

CREATE INDEX idx_plans_currency ON _plans(currency);
```

---

## Migration History

### v1.11.22 - Auto-Logout Fix (November 2025)

**Changes:** No schema changes (bug fixes only)

**Enhancement:** Fixed timeout comparison operator (`>=` instead of `>`)

---

### v1.11.20 - Auto-Logout Feature (November 2025)

**Changes:**
```sql
CREATE TABLE IF NOT EXISTS `_app_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO _app_settings (setting_key, setting_value) VALUES ('auto_logout_timeout', '5');
```

**Rollback:**
```sql
DROP TABLE IF EXISTS _app_settings;
```

---

### v1.11.19 - WebAuthn Biometric Login (November 2025)

**Changes:**
```sql
CREATE TABLE IF NOT EXISTS `_webauthn_credentials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `credential_id` TEXT NOT NULL,
  `public_key` TEXT NOT NULL,
  `counter` INT DEFAULT 0,
  `device_name` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_used` TIMESTAMP NULL,
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Rollback:**
```sql
DROP TABLE IF EXISTS _webauthn_credentials;
```

---

### v1.7.8 - Expiry Reminders (November 2025)

**Changes:**
```sql
CREATE TABLE IF NOT EXISTS `_expiry_reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT DEFAULT NULL,
  `mac` VARCHAR(17) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `reminder_date` DATE NOT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_by` INT NOT NULL,
  `message` TEXT,
  `status` ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
  `error_message` TEXT,
  INDEX (`mac`, `expiry_date`),
  INDEX (`reminder_date`),
  INDEX (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `_reminder_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `days_before_expiry` INT DEFAULT 7,
  `message_template` TEXT,
  `auto_send_enabled` TINYINT(1) DEFAULT 0,
  `last_sweep_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Rollback:**
```sql
DROP TABLE IF EXISTS _expiry_reminders;
DROP TABLE IF EXISTS _reminder_settings;
```

---

### v1.7.1 - Phone Number Support (November 2025)

**Migration Script:** `add_phone_column.php`

**Changes:**
```sql
ALTER TABLE `_accounts`
ADD COLUMN `phone_number` VARCHAR(50) DEFAULT NULL
AFTER `email`;
```

**Verification:**
```sql
SHOW COLUMNS FROM _accounts LIKE 'phone_number';
```

**Rollback:**
```sql
ALTER TABLE `_accounts` DROP COLUMN `phone_number`;
```

---

### v1.7.0 - Reseller Assignment (November 2025)

**Changes:** No schema changes (used existing `reseller` column)

**Enhancement:** Added UI and API for account-to-reseller assignment

---

### v1.6.5 - Observer Mode (November 2025)

**Changes:**
```sql
ALTER TABLE `_users`
ADD COLUMN `is_observer` TINYINT(1) DEFAULT 0
COMMENT 'Read-only observer mode';
```

---

### v1.6.0 - Permission System (November 2025)

**Changes:**
```sql
ALTER TABLE `_users`
ADD COLUMN `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0'
COMMENT 'can_edit|can_add|is_reseller_admin|can_delete|reserved';
```

---

### v1.0.0 - Initial Schema (January 2025)

**Created All Tables:**
- `_users`
- `_accounts`
- `_plans`
- `_transactions`
- `_currencies`

---

## Backup & Restore

### Backup Command
```bash
mysqldump -u root -p showboxt_panel > backup_$(date +%Y%m%d).sql
```

### Backup with Compression
```bash
mysqldump -u root -p showboxt_panel | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Restore Command
```bash
mysql -u root -p showboxt_panel < backup_20251122.sql
```

---

## Database Size Estimates

**Typical Installation:**
- 100 resellers × 5 KB = 500 KB
- 10,000 accounts × 2 KB = 20 MB
- 50 plans × 1 KB = 50 KB
- 100,000 transactions × 1 KB = 100 MB
- **Total:** ~120 MB

**Large Installation:**
- 1,000 resellers × 5 KB = 5 MB
- 100,000 accounts × 2 KB = 200 MB
- 200 plans × 1 KB = 200 KB
- 1,000,000 transactions × 1 KB = 1 GB
- **Total:** ~1.2 GB

---

## Maintenance

### Optimize Tables
```sql
OPTIMIZE TABLE _users, _accounts, _plans, _transactions, _currencies;
```

### Analyze Tables
```sql
ANALYZE TABLE _users, _accounts, _plans, _transactions, _currencies;
```

### Check Table Integrity
```sql
CHECK TABLE _users, _accounts, _plans, _transactions, _currencies;
```

### Repair Tables (if needed)
```sql
REPAIR TABLE _users, _accounts, _plans, _transactions, _currencies;
```

---

## Support

For database support:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin
- **Documentation**: README.md

---

**Document Version:** 1.11.22
**Last Updated:** November 25, 2025
**Maintained by:** ShowBox Development Team
**Developer:** Kambiz Koosheshi
