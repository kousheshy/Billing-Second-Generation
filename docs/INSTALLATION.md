# ShowBox Billing Panel - Installation Guide

Complete step-by-step installation instructions for deploying the ShowBox Billing Panel on various environments.

**Version:** 1.0.0
**Last Updated:** January 2025

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Checklist](#pre-installation-checklist)
3. [Installation Methods](#installation-methods)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Post-Installation](#post-installation)
7. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.3+)
- **Web Server**: Apache 2.4+ or Nginx 1.18+ or PHP Built-in Server
- **RAM**: 512 MB minimum (1 GB recommended)
- **Disk Space**: 100 MB for application + database storage

### Required PHP Extensions
```bash
php -m | grep -E "pdo|pdo_mysql|curl|json|session"
```

Required extensions:
- pdo
- pdo_mysql
- curl
- json
- session
- mbstring (recommended)

---

## Pre-Installation Checklist

Before installation:
- [ ] Server access (SSH or local terminal)
- [ ] MySQL root password
- [ ] Stalker Portal API credentials
- [ ] Domain name (production) or localhost
- [ ] Web server installed
- [ ] PHP with required extensions

---

## Installation Methods

### Development Environment (Local)

#### Step 1: Install Dependencies

**macOS:**
```bash
brew install php@7.4 mysql
brew services start mysql
mysql_secure_installation
```

**Linux (Ubuntu):**
```bash
sudo apt update
sudo apt install php7.4 php7.4-mysql php7.4-curl php7.4-json
sudo apt install mysql-server
sudo mysql_secure_installation
```

#### Step 2: Create Database

```bash
mysql -u root -p

CREATE DATABASE showboxt_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'showbox_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON showboxt_panel.* TO 'showbox_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Step 3: Import Database Schema

```sql
mysql -u root -p showboxt_panel

CREATE TABLE _users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(255),
  email VARCHAR(255),
  max_users INT DEFAULT 0,
  currency VARCHAR(10) DEFAULT 'GBP',
  theme VARCHAR(50) DEFAULT 'light',
  super_user INT(1) DEFAULT 0,
  timestamp INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE _accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  email VARCHAR(255),
  mac VARCHAR(255),
  full_name VARCHAR(255),
  tariff_plan VARCHAR(255),
  end_date DATETIME,
  status INT(1) DEFAULT 1,
  reseller INT,
  timestamp INT,
  INDEX(reseller),
  INDEX(end_date),
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE _plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(255) NOT NULL,
  currency VARCHAR(10) DEFAULT 'GBP',
  price DECIMAL(10,2) DEFAULT 0.00,
  expiry_days INT DEFAULT 30,
  timestamp INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE _transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type VARCHAR(50) DEFAULT 'credit',
  description TEXT,
  timestamp INT,
  INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE _currencies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  symbol VARCHAR(10),
  rate DECIMAL(10,4) DEFAULT 1.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default data
INSERT INTO _currencies (code, symbol, rate) VALUES
('GBP', '£', 1.0000),
('USD', '$', 0.7500),
('EUR', '€', 0.8500);

INSERT INTO _users (username, password, full_name, email, currency, super_user, timestamp) VALUES
('admin', '21232f297a57a5a743894a0e4a801fc3', 'Administrator', 'admin@showbox.com', 'GBP', 1, UNIX_TIMESTAMP());

INSERT INTO _transactions (user_id, amount, type, description, timestamp) VALUES
(1, 1000.00, 'credit', 'Initial balance', UNIX_TIMESTAMP());
```

#### Step 4: Configure Application

Edit `config.php`:

```php
<?php
date_default_timezone_set('Asia/Tehran');
$PANEL_NAME = "ShowBox";

// Database
$ub_main_db = "showboxt_panel";
$ub_db_host = "localhost";
$ub_db_username = "showbox_user";
$ub_db_password = "your_password";

// Stalker Portal API
$SERVER_1_ADDRESS = "http://your-server.com";
$WEBSERVICE_USERNAME = "api_username";
$WEBSERVICE_PASSWORD = "api_password";
$WEBSERVICE_BASE_URL = "http://your-server.com/stalker_portal/api/";
?>
```

#### Step 5: Start Server

```bash
cd "/path/to/project"
php -S localhost:8000
```

Access at: `http://localhost:8000/index.html`

---

### Production Environment (Apache)

#### Step 1: Install Apache & PHP

```bash
sudo apt install apache2 php7.4 libapache2-mod-php7.4 php7.4-mysql php7.4-curl
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

#### Step 2: Configure Virtual Host

```bash
sudo nano /etc/apache2/sites-available/showbox.conf
```

```apache
<VirtualHost *:80>
    ServerName billing.showbox.com
    DocumentRoot /var/www/showbox

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
```

#### Step 3: Deploy Files

```bash
sudo mkdir -p /var/www/showbox
sudo cp -r /path/to/source/* /var/www/showbox/
sudo chown -R www-data:www-data /var/www/showbox
sudo chmod 600 /var/www/showbox/config.php

sudo a2ensite showbox.conf
sudo systemctl reload apache2
```

#### Step 4: Configure SSL

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d billing.showbox.com
```

---

## Configuration

### Security Hardening

#### 1. Protect config.php

```bash
chmod 400 config.php
chown www-data:www-data config.php
```

#### 2. Create .htaccess

```apache
<Files config.php>
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "\.(md|sql|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

#### 3. Enable SSL in API Calls

Edit `api.php`:

```php
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
```

---

## Post-Installation

### Step 1: First Login

Navigate to `http://your-domain.com/index.html`

**Default credentials:**
- Username: `admin`
- Password: `admin`

### Step 2: Change Admin Password

1. Go to Settings tab
2. Change password immediately
3. Use strong password

### Step 3: Test Sync

1. Click "Sync Accounts" button
2. Verify accounts are imported
3. Check for any errors

### Step 4: Create Plans

1. Go to Plans tab
2. Create subscription plans
3. Set pricing and duration

---

## Troubleshooting

### Database Connection Failed

```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u showbox_user -p showboxt_panel

# Verify config.php credentials
```

### Sync Not Working

1. Check API credentials in config.php
2. Test API endpoint:
   ```bash
   curl -u username:password http://server/stalker_portal/api/accounts/
   ```
3. Check server.log for errors
4. Verify firewall rules

### Permission Denied

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/showbox

# Fix permissions
sudo chmod 755 /var/www/showbox
sudo chmod 600 /var/www/showbox/config.php
```

### Session Errors

```bash
# Check session directory
ls -la /var/lib/php/sessions

# Fix permissions
sudo chmod 733 /var/lib/php/sessions
sudo systemctl restart apache2
```

---

## Database Backup

Create backup script:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/showbox"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="showboxt_panel"

mkdir -p $BACKUP_DIR
mysqldump -u root -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/showbox_$DATE.sql.gz
find $BACKUP_DIR -type f -mtime +7 -delete
```

Add to crontab:
```bash
0 2 * * * /path/to/backup_database.sh
```

---

## Performance Tuning

### MySQL Optimization

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_type = 1
query_cache_size = 32M
max_connections = 100
```

### PHP Optimization

Edit `/etc/php/7.4/apache2/php.ini`:

```ini
memory_limit = 256M
max_execution_time = 60
opcache.enable = 1
opcache.memory_consumption = 128
```

---

## Support

For installation assistance:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin
- **Documentation**: README.md

---

**Document Version:** 1.0.0
**Last Updated:** January 2025
**Maintained by:** ShowBox Development Team
