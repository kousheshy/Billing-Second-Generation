# ShowBox Billing Panel - Complete Deployment Guide

**Version:** 1.7.9
**Date:** November 2025
**Author:** Deployment Documentation

---

## Table of Contents

1. [Overview](#overview)
2. [Server Requirements](#server-requirements)
3. [Database Structure](#database-structure)
4. [Pre-Deployment Checklist](#pre-deployment-checklist)
5. [Step-by-Step Deployment](#step-by-step-deployment)
6. [SSL Certificate Setup](#ssl-certificate-setup)
7. [Post-Deployment Verification](#post-deployment-verification)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Configuration Reference](#configuration-reference)

---

## Overview

This guide provides complete instructions for deploying the ShowBox Billing Panel to an Ubuntu 22.04 server. It includes all database schemas, configuration files, and troubleshooting steps based on a successful production deployment.

**Application Stack:**
- Frontend: HTML5, CSS3, JavaScript (PWA)
- Backend: PHP 8.1.2
- Database: MySQL 8.0
- Web Server: Apache 2.4.52
- OS: Ubuntu 22.04 LTS

**Default Credentials (CHANGE IMMEDIATELY):**
- Admin Username: `admin`
- Admin Password: `admin`

---

## Server Requirements

### Minimum Hardware
- CPU: 2 cores
- RAM: 2GB
- Disk: 20GB
- Network: Public IP with ports 80, 443, 22 open

### Software Requirements
- Ubuntu 22.04 LTS (recommended)
- Apache 2.4+
- MySQL 8.0+
- PHP 8.1+ with extensions:
  - php-mysql
  - php-cli
  - php-curl
  - php-json
  - php-mbstring
  - php-xml
  - libapache2-mod-php

### Network Requirements
- SSH access (port 22)
- HTTP access (port 80)
- HTTPS access (port 443)
- Domain name (for Let's Encrypt SSL) - optional

---

## Database Structure

### Database Configuration

```sql
Database Name: showboxt_panel
Database User: showbox_user
Database Password: ShowBox_2025_Secure (change this!)
Character Set: utf8
Collation: utf8_general_ci
```

### Table 1: _users

```sql
CREATE TABLE IF NOT EXISTS `_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(200) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `super_user` TINYINT(1) DEFAULT 0,
    `permissions` VARCHAR(255) DEFAULT '0|0|0|0|0|0|0',
    `is_reseller_admin` TINYINT(1) DEFAULT 0,
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `currency_id` INT(11) DEFAULT NULL,
    `is_observer` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Permissions Format:** `can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging`

### Table 2: _accounts

```sql
CREATE TABLE IF NOT EXISTS `_accounts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `mac` VARCHAR(17) NOT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `full_name` VARCHAR(200) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `phone_number` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(200) DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `reseller` INT(11) DEFAULT NULL,
    `plan_id` INT(11) DEFAULT NULL,
    `tariff_plan` VARCHAR(200) DEFAULT NULL,
    `timestamp` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mac` (`mac`),
    KEY `reseller` (`reseller`),
    KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### Table 3: _plans

```sql
CREATE TABLE IF NOT EXISTS `_plans` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `tariff_id` INT(11) DEFAULT NULL,
    `duration_days` INT(11) NOT NULL,
    `price_gbp` DECIMAL(10,2) DEFAULT 0.00,
    `price_usd` DECIMAL(10,2) DEFAULT 0.00,
    `price_eur` DECIMAL(10,2) DEFAULT 0.00,
    `price_irr` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### Table 4: _transactions

```sql
CREATE TABLE IF NOT EXISTS `_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'GBP',
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### Table 5: _currencies

```sql
CREATE TABLE IF NOT EXISTS `_currencies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(10) NOT NULL,
    `symbol` VARCHAR(10) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Default Currency Data:**

```sql
INSERT INTO _currencies (code, symbol, name, enabled) VALUES
    ('GBP', '£', 'British Pound', 1),
    ('USD', '$', 'US Dollar', 1),
    ('EUR', '€', 'Euro', 1),
    ('IRR', '﷼', 'Iranian Rial', 1);
```

### Table 6: _expiry_reminders

```sql
CREATE TABLE IF NOT EXISTS `_expiry_reminders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) NOT NULL,
    `mac` VARCHAR(17) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
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
    KEY `account_id` (`account_id`),
    KEY `mac` (`mac`),
    KEY `reminder_date` (`reminder_date`),
    KEY `sent_at` (`sent_at`),
    UNIQUE KEY `unique_reminder_mac` (`mac`, `end_date`, `days_before`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### Table 7: _reminder_settings

```sql
CREATE TABLE IF NOT EXISTS `_reminder_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `days_before_expiry` INT(11) NOT NULL DEFAULT 7,
    `message_template` TEXT,
    `auto_send_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_sweep_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**Default Reminder Settings:**

```sql
INSERT INTO _reminder_settings (user_id, days_before_expiry, message_template, auto_send_enabled, created_at, updated_at)
VALUES (1, 7, 'Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.', 0, NOW(), NOW());
```

### Default Admin User

```sql
INSERT INTO _users (id, username, password, full_name, email, super_user, permissions, balance, created_at)
VALUES (1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'System Administrator', 'admin@showbox.local', 1, '1|1|1|1|1|1|1', 0.00, NOW());
```

**Note:** Password is MD5 hash of 'admin' - CHANGE IMMEDIATELY after first login!

---

## Pre-Deployment Checklist

Before starting deployment, ensure you have:

- [ ] Server IP address or domain name
- [ ] Root or sudo access credentials
- [ ] SSH access confirmed (port 22)
- [ ] Application source files ready
- [ ] Database backup (if migrating existing data)
- [ ] Domain DNS configured (if using Let's Encrypt)
- [ ] Stalker Portal API credentials (if integrating IPTV service)

**Required Information:**

```
Server IP: ___________________
SSH User: ___________________
SSH Password: ___________________
Domain (optional): ___________________
Database Password: ___________________ (change from default!)
Stalker Portal API URL: ___________________
Stalker Portal Username: ___________________
Stalker Portal Password: ___________________
```

---

## Step-by-Step Deployment

### Step 1: Prepare Local Files

On your local machine, create a tarball of all application files:

```bash
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"

tar -czf showbox-app.tar.gz \
  *.php \
  *.html \
  *.js \
  *.css \
  *.json \
  *.png \
  icons/ \
  --exclude=.git \
  --exclude=node_modules
```

This creates `showbox-app.tar.gz` (approximately 300KB).

### Step 2: Connect to Server

```bash
ssh root@YOUR_SERVER_IP
```

Replace `YOUR_SERVER_IP` with your actual server IP address.

### Step 3: Update System Packages

```bash
apt update -y
apt upgrade -y
```

### Step 4: Install Apache Web Server

```bash
apt install -y apache2
systemctl start apache2
systemctl enable apache2
systemctl status apache2
```

**Verify:** Open browser to `http://YOUR_SERVER_IP` - should see Apache default page.

### Step 5: Install MySQL Database Server

```bash
apt install -y mysql-server mysql-client
systemctl start mysql
systemctl enable mysql
systemctl status mysql
```

**Verify MySQL is running:**

```bash
mysql --version
# Should output: mysql  Ver 8.0.44-0ubuntu0.22.04.1 or similar
```

### Step 6: Install PHP and Extensions

```bash
apt install -y \
  php \
  php-mysql \
  php-cli \
  php-curl \
  php-json \
  php-mbstring \
  php-xml \
  libapache2-mod-php

systemctl restart apache2
```

**Verify PHP installation:**

```bash
php --version
# Should output: PHP 8.1.2 or higher

php -m | grep -E 'pdo|mysql|curl|json|mbstring'
# Should show all required extensions
```

### Step 7: Create MySQL Database and User

```bash
mysql -e "CREATE DATABASE IF NOT EXISTS showboxt_panel CHARACTER SET utf8 COLLATE utf8_general_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'showbox_user'@'localhost' IDENTIFIED BY 'ShowBox_2025_Secure';"
mysql -e "GRANT ALL PRIVILEGES ON showboxt_panel.* TO 'showbox_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
```

**IMPORTANT:** Change `ShowBox_2025_Secure` to a strong password of your choice!

**Verify database created:**

```bash
mysql -e "SHOW DATABASES LIKE 'showboxt_panel';"
```

### Step 8: Create Web Directory

```bash
mkdir -p /var/www/showbox
mkdir -p /var/www/showbox/icons
mkdir -p /var/www/showbox/logs
```

### Step 9: Upload Application Files

**From your local machine**, use SCP to upload the tarball:

```bash
scp showbox-app.tar.gz root@YOUR_SERVER_IP:/tmp/
```

**On the server**, extract the files:

```bash
cd /var/www/showbox
tar -xzf /tmp/showbox-app.tar.gz
rm /tmp/showbox-app.tar.gz
```

### Step 10: Configure Database Connection

Edit `config.php` to update database credentials:

```bash
cd /var/www/showbox

# Update database username
sed -i 's/\$ub_db_username="[^"]*"/\$ub_db_username="showbox_user"/' config.php

# Update database password
sed -i 's/\$ub_db_password="[^"]*"/\$ub_db_password="ShowBox_2025_Secure"/' config.php
```

**Manually verify the changes:**

```bash
grep -E 'ub_db_username|ub_db_password' config.php
```

Should show:
```
$ub_db_username="showbox_user";
$ub_db_password="ShowBox_2025_Secure";
```

### Step 11: Set File Permissions

```bash
chown -R www-data:www-data /var/www/showbox
chmod -R 755 /var/www/showbox
chmod -R 775 /var/www/showbox/logs
```

### Step 12: Configure Apache Virtual Host

Create Apache configuration file:

```bash
cat > /etc/apache2/sites-available/showbox.conf << 'EOF'
<VirtualHost *:80>
    ServerAdmin admin@showbox.local
    ServerName YOUR_SERVER_IP
    DocumentRoot /var/www/showbox

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
EOF
```

Replace `YOUR_SERVER_IP` with actual IP or domain.

Enable the site:

```bash
a2dissite 000-default.conf
a2ensite showbox.conf
a2enmod rewrite
systemctl reload apache2
```

### Step 13: Create Database Schema

Run the database schema creation script:

```bash
cd /var/www/showbox
php create_database_schema.php
```

**Expected output:**
```
════════════════════════════════════════════════════════════════
  Creating ShowBox Billing Panel Database Schema
  Version: 1.7.9
════════════════════════════════════════════════════════════════

[1/7] Creating _users table...
    ✓ _users table created
[2/7] Creating _accounts table...
    ✓ _accounts table created
...
✅ DATABASE SCHEMA CREATED SUCCESSFULLY!
```

**Verify database tables:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SHOW TABLES;"
```

Should show all 7 tables:
```
+---------------------------+
| Tables_in_showboxt_panel |
+---------------------------+
| _accounts                 |
| _currencies               |
| _expiry_reminders         |
| _plans                    |
| _reminder_settings        |
| _transactions             |
| _users                    |
+---------------------------+
```

### Step 14: Configure PHP Settings (Optional)

For better performance with large file uploads:

```bash
PHP_INI=$(php -i | grep "Loaded Configuration File" | awk '{print $5}')

sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' "$PHP_INI"
sed -i 's/post_max_size = .*/post_max_size = 50M/' "$PHP_INI"
sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI"

systemctl restart apache2
```

### Step 15: Configure Firewall

```bash
# Install UFW if not already installed
apt install -y ufw

# Allow SSH (IMPORTANT: Do this first!)
ufw allow 22/tcp

# Allow HTTP and HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Enable firewall
ufw --force enable

# Check status
ufw status
```

### Step 16: Initial Access Test

Open browser and navigate to:

```
http://YOUR_SERVER_IP
```

**Login with default credentials:**
- Username: `admin`
- Password: `admin`

**CRITICAL:** Change the admin password immediately after first login!

---

## SSL Certificate Setup

### Option A: Self-Signed Certificate (For Testing/Internal Use)

**Step 1:** Create SSL directory

```bash
mkdir -p /etc/ssl/showbox
```

**Step 2:** Generate self-signed certificate (valid 365 days)

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/showbox/showbox.key \
  -out /etc/ssl/showbox/showbox.crt \
  -subj "/C=GB/ST=London/L=London/O=ShowBox/OU=IT/CN=YOUR_SERVER_IP"
```

Replace `YOUR_SERVER_IP` with your server IP or domain.

**Step 3:** Create SSL virtual host

```bash
cat > /etc/apache2/sites-available/showbox-ssl.conf << 'EOF'
<VirtualHost *:443>
    ServerAdmin admin@showbox.local
    ServerName YOUR_SERVER_IP
    DocumentRoot /var/www/showbox

    SSLEngine on
    SSLCertificateFile /etc/ssl/showbox/showbox.crt
    SSLCertificateKeyFile /etc/ssl/showbox/showbox.key

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_ssl_access.log combined
</VirtualHost>
EOF
```

**Step 4:** Enable SSL module and site

```bash
a2enmod ssl
a2ensite showbox-ssl.conf
systemctl restart apache2
```

**Step 5:** Access via HTTPS

```
https://YOUR_SERVER_IP
```

**Note:** Browser will show "Not Secure" warning - this is expected with self-signed certificates. Click "Advanced" → "Proceed to site".

### Option B: Let's Encrypt Certificate (For Production/Public Domain)

**Prerequisites:**
- Valid domain name pointing to server's public IP
- Ports 80 and 443 accessible from internet
- DNS propagation complete

**Step 1:** Install Certbot

```bash
apt install -y certbot python3-certbot-apache
```

**Step 2:** Create domain-based virtual host

```bash
cat > /etc/apache2/sites-available/showbox-domain.conf << 'EOF'
<VirtualHost *:80>
    ServerName YOUR_DOMAIN
    ServerAdmin admin@YOUR_DOMAIN
    DocumentRoot /var/www/showbox

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
EOF
```

Replace `YOUR_DOMAIN` with your actual domain (e.g., billing.apamehnet.com).

**Step 3:** Enable domain site

```bash
a2ensite showbox-domain.conf
systemctl reload apache2
```

**Step 4:** Obtain Let's Encrypt certificate

```bash
certbot --apache -d YOUR_DOMAIN --non-interactive --agree-tos --email YOUR_EMAIL
```

Replace:
- `YOUR_DOMAIN` with your domain (e.g., billing.apamehnet.com)
- `YOUR_EMAIL` with your email address

**Expected output:**
```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/YOUR_DOMAIN/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/YOUR_DOMAIN/privkey.pem
```

**Step 5:** Verify auto-renewal

```bash
certbot renew --dry-run
```

**Step 6:** Access via HTTPS

```
https://YOUR_DOMAIN
```

Browser should show green padlock with valid certificate.

**Certificate Details:**
- Issuer: Let's Encrypt
- Validity: 90 days (auto-renews)
- Auto-renewal: Runs twice daily via systemd timer

**Verify auto-renewal timer:**

```bash
systemctl status certbot.timer
```

---

## Post-Deployment Verification

### 1. Web Server Check

```bash
systemctl status apache2
# Should show: active (running)

curl -I http://localhost
# Should return: HTTP/1.1 200 OK
```

### 2. Database Connection Check

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SELECT COUNT(*) as users FROM _users;"
# Should return: 1 (admin user)
```

### 3. PHP Configuration Check

Create test file:

```bash
echo "<?php phpinfo(); ?>" > /var/www/showbox/test.php
```

Open browser: `http://YOUR_SERVER_IP/test.php`

Verify:
- PHP Version: 8.1.2 or higher
- PDO drivers: mysql enabled
- Extensions: curl, json, mbstring loaded

**IMPORTANT:** Delete test file after verification:

```bash
rm /var/www/showbox/test.php
```

### 4. Login Functionality Test

1. Open: `http://YOUR_SERVER_IP` or `https://YOUR_DOMAIN`
2. Enter username: `admin`
3. Enter password: `admin`
4. Click "Login"
5. Should redirect to dashboard with welcome message

**Expected result:** Dashboard loads showing:
- Welcome message with admin name
- Account statistics
- Navigation menu

### 5. API Endpoints Test

Test user info endpoint:

```bash
curl -X GET "http://YOUR_SERVER_IP/get_user_info.php" \
  -H "Cookie: PHPSESSID=test" \
  -w "\n"
```

Should return JSON response (may show "Not logged in" error if no session, which is expected).

### 6. Stalker Portal Integration Test (If Configured)

1. Login to dashboard
2. Click "Sync Accounts" button
3. Should fetch accounts from Stalker Portal API
4. Verify accounts appear in accounts table

### 7. SSL Certificate Verification (If Configured)

**For Let's Encrypt:**

```bash
openssl s_client -connect YOUR_DOMAIN:443 -servername YOUR_DOMAIN < /dev/null 2>/dev/null | grep "Verify return code"
# Should return: Verify return code: 0 (ok)
```

**For Self-Signed:**

```bash
openssl s_client -connect YOUR_SERVER_IP:443 < /dev/null 2>/dev/null | grep "subject"
# Should show your certificate subject details
```

### 8. File Permissions Check

```bash
ls -la /var/www/showbox/ | grep -E "config.php|index.html"
```

Should show:
```
-rwxr-xr-x  1 www-data www-data config.php
-rwxr-xr-x  1 www-data www-data index.html
```

### 9. Error Log Check

```bash
tail -20 /var/log/apache2/showbox_error.log
```

Should be empty or show only informational messages (no PHP errors).

---

## Troubleshooting Guide

### Issue 1: "Database connection failed" Error

**Symptoms:**
- Login shows database connection error
- get_user_info.php returns error

**Solutions:**

1. **Verify database credentials in config.php:**

```bash
grep -E 'ub_db_username|ub_db_password|ub_main_db' /var/www/showbox/config.php
```

Should match your database credentials.

2. **Test MySQL connection manually:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SELECT 1;"
```

3. **Check if database tables exist:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SHOW TABLES;"
```

Should list all 7 tables. If missing, run `create_database_schema.php` again.

4. **Check MySQL service status:**

```bash
systemctl status mysql
```

### Issue 2: "Table not found" Error

**Symptoms:**
- Error: `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'showboxt_panel._users' doesn't exist`

**Solution:**

Run database schema creation:

```bash
cd /var/www/showbox
php create_database_schema.php
```

Verify tables created:

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SHOW TABLES;"
```

### Issue 3: Login Works But Dashboard Doesn't Load

**Symptoms:**
- Login succeeds
- Stuck on login page
- No redirect to dashboard

**Solutions:**

1. **Check browser console for JavaScript errors:**
   - Press F12 in browser
   - Check Console tab for errors

2. **Verify get_user_info.php returns correct data:**

```bash
# Create a test session first by logging in via browser
# Then check the endpoint:
curl -X GET "http://YOUR_SERVER_IP/get_user_info.php" \
  -H "Cookie: PHPSESSID=YOUR_SESSION_ID" \
  -w "\n"
```

Should return JSON with user information.

3. **Check Apache error logs:**

```bash
tail -50 /var/log/apache2/showbox_error.log
```

4. **Verify file integrity - compare with GitHub repository:**

```bash
# On local machine
git clone https://github.com/kousheshy/Billing-Second-Generation /tmp/billing-check

# Compare critical files
diff /var/www/showbox/get_user_info.php /tmp/billing-check/get_user_info.php
diff /var/www/showbox/dashboard.js /tmp/billing-check/dashboard.js
```

If differences found, restore from GitHub.

### Issue 4: 403 Forbidden Error

**Symptoms:**
- Browser shows "403 Forbidden"
- Cannot access any pages

**Solutions:**

1. **Check file permissions:**

```bash
ls -la /var/www/showbox/
```

Should show `www-data:www-data` ownership.

Fix permissions:

```bash
chown -R www-data:www-data /var/www/showbox
chmod -R 755 /var/www/showbox
```

2. **Check Apache virtual host configuration:**

```bash
grep -A 5 "Directory /var/www/showbox" /etc/apache2/sites-available/showbox.conf
```

Should show `Require all granted`.

3. **Check Apache error log:**

```bash
tail -50 /var/log/apache2/error.log
```

### Issue 5: 500 Internal Server Error

**Symptoms:**
- Any page shows "500 Internal Server Error"

**Solutions:**

1. **Check Apache error log:**

```bash
tail -100 /var/log/apache2/showbox_error.log
```

Look for PHP errors.

2. **Check PHP syntax errors:**

```bash
cd /var/www/showbox
php -l config.php
php -l login.php
php -l get_user_info.php
```

Should show "No syntax errors detected" for each file.

3. **Verify PHP extensions:**

```bash
php -m | grep -E 'pdo|mysql'
```

Should show both `PDO` and `pdo_mysql`.

4. **Check .htaccess file (if exists):**

```bash
cat /var/www/showbox/.htaccess
```

If it contains invalid directives, rename or delete it:

```bash
mv /var/www/showbox/.htaccess /var/www/showbox/.htaccess.bak
```

### Issue 6: SSL Certificate Warning

**Symptoms:**
- Browser shows "Your connection is not private"
- Certificate errors

**For Self-Signed Certificates:**

This is expected. Click "Advanced" → "Proceed to site (unsafe)". For production, use Let's Encrypt instead.

**For Let's Encrypt Certificates:**

1. **Verify certificate is installed:**

```bash
certbot certificates
```

2. **Check certificate expiry:**

```bash
openssl s_client -connect YOUR_DOMAIN:443 -servername YOUR_DOMAIN < /dev/null 2>/dev/null | openssl x509 -noout -dates
```

3. **Test auto-renewal:**

```bash
certbot renew --dry-run
```

4. **Check Apache SSL configuration:**

```bash
grep -E 'SSLCertificate' /etc/apache2/sites-available/showbox-domain-le-ssl.conf
```

### Issue 7: Sync Accounts Not Working

**Symptoms:**
- "Sync Accounts" button does nothing
- API errors in console

**Solutions:**

1. **Verify Stalker Portal API credentials in config.php:**

```bash
grep -E 'WEBSERVICE_USERNAME|WEBSERVICE_PASSWORD|WEBSERVICE_BASE_URL' /var/www/showbox/config.php
```

2. **Test API connectivity:**

```bash
curl -I http://81.12.70.4/stalker_portal/api/
```

Should return HTTP 200.

3. **Check sync_accounts.php for errors:**

```bash
php /var/www/showbox/sync_accounts.php
```

Will show PHP errors if any.

4. **Check Apache error log during sync:**

```bash
tail -f /var/log/apache2/showbox_error.log
```

Then trigger sync from browser.

### Issue 8: Favicon/Icons Not Showing

**Symptoms:**
- Browser tab shows no icon
- PWA icons missing

**Solutions:**

1. **Verify icon files exist:**

```bash
ls -la /var/www/showbox/favicon*.png
ls -la /var/www/showbox/icons/
```

2. **Check file permissions:**

```bash
chmod 644 /var/www/showbox/favicon*.png
chmod 644 /var/www/showbox/icons/*.png
```

3. **Clear browser cache:**
   - Press Ctrl+Shift+Delete
   - Clear cached images and files

4. **Verify manifest.json references correct paths:**

```bash
grep -E 'icon|favicon' /var/www/showbox/manifest.json
```

### Issue 9: Session Not Persisting (Login Loop)

**Symptoms:**
- Login succeeds but immediately logs out
- Constant redirect to login page

**Solutions:**

1. **Check PHP session configuration:**

```bash
php -i | grep -E 'session.save_path|session.cookie'
```

2. **Verify session directory writable:**

```bash
php -r 'echo session_save_path();'
ls -la $(php -r 'echo session_save_path();')
```

Should be writable by www-data.

3. **Check session_start() in PHP files:**

```bash
grep -n "session_start" /var/www/showbox/*.php
```

Should appear at top of login.php, get_user_info.php, etc.

4. **Check browser cookies enabled:**
   - Open browser DevTools → Application → Cookies
   - Should see PHPSESSID cookie

### Issue 10: Permission Denied Errors

**Symptoms:**
- Cannot write logs
- Upload failures
- Session errors

**Solutions:**

```bash
# Fix web directory permissions
chown -R www-data:www-data /var/www/showbox
chmod -R 755 /var/www/showbox

# Fix log directory permissions
mkdir -p /var/www/showbox/logs
chmod 775 /var/www/showbox/logs
chown www-data:www-data /var/www/showbox/logs

# Fix session directory permissions
SESSION_DIR=$(php -r 'echo session_save_path();')
chmod 1733 $SESSION_DIR
```

---

## Configuration Reference

### config.php Full Reference

```php
<?php

//////////////////System
date_default_timezone_set('Asia/Tehran');  // Change to your timezone
$admins_only = false;
$PANEL_NAME = "ShowBox";
$WELCOME_MSG = "Welcome to ShowBox - 24/7 Support";

//////////////////Database
$ub_main_db="showboxt_panel";
$ub_db_host="localhost";
$ub_db_username="showbox_user";  // CHANGE THIS
$ub_db_password="ShowBox_2025_Secure";  // CHANGE THIS

//////////////////Stalker Portal API
$SERVER_1_ADDRESS = "http://YOUR_STALKER_IP";  // Change this
$SERVER_2_ADDRESS = "http://YOUR_STALKER_IP";  // Change this

$WEBSERVICE_USERNAME = "admin";  // Stalker admin username
$WEBSERVICE_PASSWORD = "your_password";  // Stalker admin password
$WEBSERVICE_BASE_URL = "http://YOUR_STALKER_IP/stalker_portal/api/";

$WEBSERVICE_URLs['stb'] = $WEBSERVICE_BASE_URL."stb/";
$WEBSERVICE_URLs['accounts'] = $WEBSERVICE_BASE_URL."accounts/";
$WEBSERVICE_URLs['users'] = $WEBSERVICE_BASE_URL."users/";
$WEBSERVICE_URLs['stb_msg'] = $WEBSERVICE_BASE_URL."stb_msg/";
$WEBSERVICE_URLs['send_event'] = $WEBSERVICE_BASE_URL."send_event/";
$WEBSERVICE_URLs['stb_modules'] = $WEBSERVICE_BASE_URL."stb_modules/";
$WEBSERVICE_URLs['itv'] = $WEBSERVICE_BASE_URL."itv/";
$WEBSERVICE_URLs['itv_subscription'] = $WEBSERVICE_BASE_URL."itv_subscription/";
$WEBSERVICE_URLs['tariffs'] = $WEBSERVICE_BASE_URL."tariffs/";
$WEBSERVICE_URLs['services_plan'] = $WEBSERVICE_BASE_URL."services_plan/";
$WEBSERVICE_URLs['account_subscription'] = $WEBSERVICE_BASE_URL."account_subscription/";
$WEBSERVICE_URLs['reseller'] = $WEBSERVICE_BASE_URL."reseller/";

?>
```

### Apache Virtual Host Templates

**Standard HTTP (Port 80):**

```apache
<VirtualHost *:80>
    ServerAdmin admin@showbox.local
    ServerName YOUR_DOMAIN_OR_IP
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

**HTTPS with Self-Signed Certificate (Port 443):**

```apache
<VirtualHost *:443>
    ServerAdmin admin@showbox.local
    ServerName YOUR_DOMAIN_OR_IP
    DocumentRoot /var/www/showbox

    SSLEngine on
    SSLCertificateFile /etc/ssl/showbox/showbox.crt
    SSLCertificateKeyFile /etc/ssl/showbox/showbox.key

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_ssl_access.log combined
</VirtualHost>
```

**HTTPS with Let's Encrypt (Auto-generated by Certbot):**

```apache
<VirtualHost *:443>
    ServerName YOUR_DOMAIN
    ServerAdmin admin@YOUR_DOMAIN
    DocumentRoot /var/www/showbox

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/YOUR_DOMAIN/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/YOUR_DOMAIN/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
```

### MySQL Backup and Restore

**Create Backup:**

```bash
mysqldump -u showbox_user -p'ShowBox_2025_Secure' \
  --set-gtid-purged=OFF \
  --skip-comments \
  --no-tablespaces \
  showboxt_panel > showbox_backup_$(date +%Y%m%d).sql
```

**Restore from Backup:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel < showbox_backup_20251122.sql
```

**Transfer Backup to Remote Server:**

```bash
scp showbox_backup_20251122.sql root@REMOTE_SERVER_IP:/tmp/
```

### Useful Commands Reference

**Restart All Services:**

```bash
systemctl restart apache2
systemctl restart mysql
```

**View Real-Time Logs:**

```bash
# Apache error log
tail -f /var/log/apache2/showbox_error.log

# Apache access log
tail -f /var/log/apache2/showbox_access.log

# MySQL error log
tail -f /var/log/mysql/error.log
```

**Check Service Status:**

```bash
systemctl status apache2
systemctl status mysql
systemctl status certbot.timer  # If using Let's Encrypt
```

**Check Disk Space:**

```bash
df -h /var/www/showbox
```

**Check Database Size:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' -e "
SELECT
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'showboxt_panel'
GROUP BY table_schema;
"
```

**List All Accounts:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "
SELECT id, mac, username, full_name, end_date, status
FROM _accounts
ORDER BY created_at DESC
LIMIT 10;
"
```

**Count Active Accounts:**

```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "
SELECT
    status,
    COUNT(*) as count
FROM _accounts
GROUP BY status;
"
```

---

## Security Best Practices

### 1. Change Default Passwords

**Immediately after deployment:**

1. **Change admin panel password:**
   - Login to dashboard
   - Go to Settings → Change Password
   - Use strong password (minimum 12 characters, mixed case, numbers, symbols)

2. **Change database password:**

```bash
# On server
mysql -e "ALTER USER 'showbox_user'@'localhost' IDENTIFIED BY 'NEW_STRONG_PASSWORD';"

# Update config.php
sed -i 's/ShowBox_2025_Secure/NEW_STRONG_PASSWORD/' /var/www/showbox/config.php
```

3. **Change SSH password:**

```bash
passwd root  # Or your SSH user
```

### 2. Secure MySQL

```bash
# Run MySQL secure installation
mysql_secure_installation

# Answer prompts:
# - Set root password: YES
# - Remove anonymous users: YES
# - Disallow root login remotely: YES
# - Remove test database: YES
# - Reload privilege tables: YES
```

### 3. Configure Firewall

```bash
# Allow only necessary ports
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp   # SSH
ufw allow 80/tcp   # HTTP
ufw allow 443/tcp  # HTTPS
ufw enable
```

### 4. Disable Directory Listing

Already configured in Apache virtual host:

```apache
Options -Indexes +FollowSymLinks
```

### 5. Hide PHP Version

Edit PHP configuration:

```bash
PHP_INI=$(php -i | grep "Loaded Configuration File" | awk '{print $5}')
sed -i 's/expose_php = On/expose_php = Off/' "$PHP_INI"
systemctl restart apache2
```

### 6. Set Secure File Permissions

```bash
# Files: 644 (owner read/write, group/others read only)
find /var/www/showbox -type f -exec chmod 644 {} \;

# Directories: 755 (owner full, group/others read/execute)
find /var/www/showbox -type d -exec chmod 755 {} \;

# config.php: 640 (owner read/write, group read only, others none)
chmod 640 /var/www/showbox/config.php
```

### 7. Regular Backups

Create automated backup script:

```bash
cat > /root/backup-showbox.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/root/backups"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u showbox_user -p'ShowBox_2025_Secure' \
  --set-gtid-purged=OFF \
  showboxt_panel > $BACKUP_DIR/db_$DATE.sql

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/showbox

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /root/backup-showbox.sh
```

Add to crontab (daily at 2 AM):

```bash
crontab -e

# Add this line:
0 2 * * * /root/backup-showbox.sh >> /var/log/showbox-backup.log 2>&1
```

### 8. Enable HTTPS Only (Redirect HTTP to HTTPS)

After SSL is configured:

```bash
# Edit HTTP virtual host to redirect to HTTPS
cat > /etc/apache2/sites-available/showbox.conf << 'EOF'
<VirtualHost *:80>
    ServerName YOUR_DOMAIN
    Redirect permanent / https://YOUR_DOMAIN/
</VirtualHost>
EOF

systemctl reload apache2
```

### 9. Update System Regularly

```bash
# Create update script
cat > /root/update-system.sh << 'EOF'
#!/bin/bash
apt update
apt upgrade -y
apt autoremove -y
apt autoclean
echo "System updated: $(date)"
EOF

chmod +x /root/update-system.sh

# Add to crontab (weekly on Sunday at 3 AM)
crontab -e

# Add this line:
0 3 * * 0 /root/update-system.sh >> /var/log/system-update.log 2>&1
```

### 10. Monitor Logs

Set up log monitoring:

```bash
# Install logwatch
apt install -y logwatch

# Configure daily log reports via email
# Edit: /etc/logwatch/conf/logwatch.conf
# Set: MailTo = your-email@example.com
```

---

## Production Deployment Checklist

Use this checklist before going live:

**Pre-Deployment:**
- [ ] Server meets minimum requirements
- [ ] Domain DNS configured (if using domain)
- [ ] Backup of existing data (if migrating)
- [ ] All credentials documented securely

**Installation:**
- [ ] LAMP stack installed and verified
- [ ] Database created with correct charset
- [ ] Application files uploaded
- [ ] config.php updated with correct credentials
- [ ] File permissions set correctly
- [ ] Apache virtual host configured
- [ ] Database schema created successfully

**Security:**
- [ ] Default admin password changed
- [ ] Database password changed from default
- [ ] SSH password changed (if using password auth)
- [ ] MySQL secured (mysql_secure_installation run)
- [ ] Firewall configured and enabled
- [ ] Directory listing disabled
- [ ] PHP version hidden
- [ ] SSL certificate installed (Let's Encrypt for production)
- [ ] HTTP to HTTPS redirect enabled
- [ ] File permissions secured (config.php = 640)

**Testing:**
- [ ] Login works correctly
- [ ] Dashboard loads without errors
- [ ] Account sync from Stalker Portal works (if applicable)
- [ ] Add/Edit/Delete operations work
- [ ] User permissions work correctly
- [ ] SSL certificate shows green padlock (if using Let's Encrypt)
- [ ] All icons/favicons display correctly
- [ ] PWA manifest loads
- [ ] No errors in Apache error log
- [ ] No PHP errors in browser console

**Monitoring:**
- [ ] Backup script created and tested
- [ ] Backup cron job scheduled
- [ ] System update cron job scheduled
- [ ] Log monitoring configured
- [ ] Disk space monitoring set up
- [ ] SSL certificate auto-renewal verified (certbot renew --dry-run)

**Documentation:**
- [ ] Server access credentials documented
- [ ] Database credentials documented
- [ ] Stalker Portal API credentials documented
- [ ] SSL certificate details documented
- [ ] Backup location documented
- [ ] Support contact information documented

---

## Quick Reference Commands

### Essential One-Liners

**Check if web server is running:**
```bash
curl -I http://localhost
```

**Test database connection:**
```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' -e "SELECT VERSION();"
```

**View recent error logs:**
```bash
tail -50 /var/log/apache2/showbox_error.log
```

**Restart all services:**
```bash
systemctl restart apache2 mysql
```

**Check SSL certificate expiry:**
```bash
certbot certificates
```

**View active sessions:**
```bash
mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "SELECT username, created_at FROM _users WHERE id IN (SELECT DISTINCT user_id FROM _reminder_settings);"
```

**Get server info:**
```bash
echo "OS: $(lsb_release -d | cut -f2)"
echo "Apache: $(apache2 -v | head -1)"
echo "MySQL: $(mysql --version)"
echo "PHP: $(php -v | head -1)"
```

**Quick health check:**
```bash
#!/bin/bash
echo "=== ShowBox Health Check ==="
echo "Apache: $(systemctl is-active apache2)"
echo "MySQL: $(systemctl is-active mysql)"
echo "Disk Usage: $(df -h /var/www/showbox | tail -1 | awk '{print $5}')"
echo "Database Size: $(mysql -u showbox_user -p'ShowBox_2025_Secure' -Nse "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = 'showboxt_panel';") MB"
echo "Total Accounts: $(mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -Nse "SELECT COUNT(*) FROM _accounts;")"
echo "SSL Expiry: $(if [ -f /etc/letsencrypt/live/*/cert.pem ]; then openssl x509 -enddate -noout -in /etc/letsencrypt/live/*/cert.pem; else echo "No Let's Encrypt cert"; fi)"
```

---

## Support and Resources

**GitHub Repository:**
https://github.com/kousheshy/Billing-Second-Generation

**Documentation Location:**
This file should be kept in the application root directory alongside the source code.

**Version History:**
- v1.7.9 - Current version (November 2025)
- Complete database schema with 7 tables
- PWA support with service workers
- Stalker Portal integration
- Multi-currency support
- Expiry reminder system

**Common Support Scenarios:**

1. **Forgot admin password:**
   - Connect to server via SSH
   - Run: `mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel -e "UPDATE _users SET password = MD5('newpassword') WHERE username = 'admin';"`
   - Replace 'newpassword' with desired password

2. **Need to reset database:**
   ```bash
   mysql -u showbox_user -p'ShowBox_2025_Secure' -e "DROP DATABASE showboxt_panel;"
   mysql -u showbox_user -p'ShowBox_2025_Secure' -e "CREATE DATABASE showboxt_panel CHARACTER SET utf8 COLLATE utf8_general_ci;"
   cd /var/www/showbox
   php create_database_schema.php
   ```

3. **Restore from backup:**
   ```bash
   mysql -u showbox_user -p'ShowBox_2025_Secure' showboxt_panel < /path/to/backup.sql
   ```

---

## Appendix A: Complete File List

Files that must be present in `/var/www/showbox/`:

**Core Application Files:**
- index.html
- login.html (or integrated in index.html)
- dashboard.html
- config.php
- login.php
- logout.php
- get_user_info.php

**API Endpoints:**
- sync_accounts.php
- add_account.php
- edit_account.php
- delete_account.php
- get_accounts.php
- send_message.php
- send_event.php
- get_plans.php
- get_tariffs.php

**Reminder System:**
- get_reminder_settings.php
- update_reminder_settings.php
- send_expiry_reminders.php
- cron_check_expiry_reminders.php
- add_reminder_tracking.php

**Static Assets:**
- dashboard.css
- dashboard.js
- service-worker.js
- manifest.json

**Icons:**
- favicon.png
- favicon-16x16.png
- favicon-32x32.png
- icons/icon-72x72.png
- icons/icon-96x96.png
- icons/icon-128x128.png
- icons/icon-144x144.png
- icons/icon-152x152.png
- icons/icon-192x192.png
- icons/icon-384x384.png
- icons/icon-512x512.png

**Utility Scripts:**
- create_database_schema.php
- server_setup.sh (optional, for reference)

---

## Appendix B: Database Schema Diagram

```
┌──────────────────┐
│     _users       │
├──────────────────┤
│ id (PK)          │
│ username (UQ)    │
│ password         │
│ full_name        │
│ email            │
│ phone            │
│ super_user       │
│ permissions      │
│ is_reseller_admin│
│ balance          │
│ currency_id (FK) │──┐
│ is_observer      │  │
│ created_at       │  │
│ updated_at       │  │
└──────────────────┘  │
         │            │
         │            │
         │            ▼
         │    ┌──────────────────┐
         │    │   _currencies    │
         │    ├──────────────────┤
         │    │ id (PK)          │
         │    │ code (UQ)        │
         │    │ symbol           │
         │    │ name             │
         │    │ enabled          │
         │    └──────────────────┘
         │
         │ (reseller FK)
         │
         ▼
┌──────────────────┐
│    _accounts     │
├──────────────────┤
│ id (PK)          │
│ mac (UQ)         │
│ username         │
│ full_name        │
│ phone            │
│ phone_number     │
│ email            │
│ end_date         │
│ status           │
│ reseller (FK)    │──────┐
│ plan_id (FK)     │──┐   │
│ tariff_plan      │  │   │
│ timestamp        │  │   │
│ created_at       │  │   │
│ updated_at       │  │   │
└──────────────────┘  │   │
         │            │   │
         │            │   │
         │            ▼   │
         │    ┌──────────────────┐
         │    │     _plans       │
         │    ├──────────────────┤
         │    │ id (PK)          │
         │    │ name             │
         │    │ tariff_id        │
         │    │ duration_days    │
         │    │ price_gbp        │
         │    │ price_usd        │
         │    │ price_eur        │
         │    │ price_irr        │
         │    │ created_at       │
         │    └──────────────────┘
         │
         │ (account_id FK)
         │
         ▼
┌──────────────────┐
│_expiry_reminders │
├──────────────────┤
│ id (PK)          │
│ account_id (FK)  │
│ mac              │
│ username         │
│ full_name        │
│ end_date         │
│ days_before      │
│ reminder_date    │
│ sent_at          │
│ sent_by (FK)     │──────┐
│ message          │      │
│ status           │      │
│ error_message    │      │
└──────────────────┘      │
                          │
         ┌────────────────┘
         │
         │ (user_id FK)
         │
         ▼
┌──────────────────┐
│_reminder_settings│
├──────────────────┤
│ id (PK)          │
│ user_id (UQ,FK)  │
│ days_before_expiry│
│ message_template │
│ auto_send_enabled│
│ last_sweep_at    │
│ created_at       │
│ updated_at       │
└──────────────────┘

┌──────────────────┐
│  _transactions   │
├──────────────────┤
│ id (PK)          │
│ user_id (FK)     │
│ type             │
│ amount           │
│ currency         │
│ description      │
│ created_at       │
└──────────────────┘
```

**Relationships:**
- _users.currency_id → _currencies.id (Many-to-One)
- _accounts.reseller → _users.id (Many-to-One)
- _accounts.plan_id → _plans.id (Many-to-One)
- _expiry_reminders.account_id → _accounts.id (Many-to-One)
- _expiry_reminders.sent_by → _users.id (Many-to-One)
- _reminder_settings.user_id → _users.id (One-to-One)
- _transactions.user_id → _users.id (Many-to-One)

---

## Document Version

**Version:** 1.0
**Last Updated:** November 22, 2025
**Deployment Version:** ShowBox Billing Panel v1.7.9
**Tested On:** Ubuntu 22.04.3 LTS

---

**END OF DEPLOYMENT GUIDE**
