# Server Setup Guide - ShowBox Billing Panel

**Version:** 1.17.3
**Last Updated:** November 28, 2025

This guide documents all server-specific configurations required when deploying to a new server.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [File Deployment](#file-deployment)
3. [File Permissions](#file-permissions)
4. [MySQL Configuration](#mysql-configuration)
5. [Cron Jobs](#cron-jobs)
6. [Web Server Configuration](#web-server-configuration)
7. [Post-Deployment Checklist](#post-deployment-checklist)

---

## Prerequisites

### Required Software
- PHP 7.4+ with extensions: curl, pdo_mysql, json, mbstring
- MySQL 5.7+ or MariaDB 10.3+
- Apache or Nginx web server
- Composer (for dependencies, if any)

### Required Access
- SSH root access to server
- MySQL root access
- Web server configuration access

---

## File Deployment

### Deploy all files
```bash
# From local machine
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='.DS_Store' \
  "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/" \
  root@SERVER_IP:/var/www/showbox/
```

### Or deploy specific directories
```bash
scp -r api/ root@SERVER_IP:/var/www/showbox/
scp -r cron/ root@SERVER_IP:/var/www/showbox/
scp -r docs/ root@SERVER_IP:/var/www/showbox/
scp dashboard.* root@SERVER_IP:/var/www/showbox/
scp index.html root@SERVER_IP:/var/www/showbox/
scp service-worker.js root@SERVER_IP:/var/www/showbox/
scp config.php root@SERVER_IP:/var/www/showbox/
```

---

## File Permissions

### CRITICAL: Set correct ownership and permissions

```bash
# Set ownership to web server user
chown -R www-data:www-data /var/www/showbox/

# Set directory permissions
find /var/www/showbox -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/showbox -type f -exec chmod 644 {} \;

# Make cron scripts executable
chmod +x /var/www/showbox/cron/*.php

# Protect config file (readable by web server only)
chmod 640 /var/www/showbox/config.php
```

### Verify permissions
```bash
ls -la /var/www/showbox/
ls -la /var/www/showbox/api/
ls -la /var/www/showbox/cron/
```

**Expected output:**
- Files: `-rw-r--r--` (644)
- Directories: `drwxr-xr-x` (755)
- Config: `-rw-r-----` (640)

---

## MySQL Configuration

### 1. Set Timezone to Asia/Tehran

**IMPORTANT:** MySQL must use the same timezone as PHP to ensure correct date comparisons.

```bash
# Edit MySQL configuration
nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Add under [mysqld] section:
default-time-zone = "+03:30"

# Restart MySQL
systemctl restart mysql
```

### 2. Verify Timezone
```bash
mysql -u root -e "SELECT @@global.time_zone, NOW();"
```

**Expected output:**
```
@@global.time_zone | NOW()
+03:30             | 2025-11-28 15:30:00  (Tehran time)
```

### 3. Create Database Tables

Run migration scripts if deploying fresh:
```bash
php /var/www/showbox/scripts/create_audit_log_table.php
php /var/www/showbox/scripts/create_login_history_table.php
php /var/www/showbox/scripts/add_transaction_corrections.php
```

---

## Cron Jobs

### Add all cron jobs

```bash
crontab -e
```

Add the following lines:

```cron
# ShowBox Billing Panel - Expiry Reminders (Daily at 9 AM)
0 9 * * * /usr/bin/php /var/www/showbox/cron_check_expiry_reminders.php >> /var/www/showbox/logs/reminder_cron.log 2>&1

# ShowBox Billing Panel - Push Notifications for Expired (Every 10 min)
*/10 * * * * /usr/bin/php /var/www/showbox/api/cron_check_expired.php >> /var/log/showbox_expiry.log 2>&1

# ShowBox Billing Panel - Auto-Disable Expired Accounts (Every hour)
0 * * * * /usr/bin/php /var/www/showbox/cron/cron_disable_expired_accounts.php >> /var/log/showbox-disable-expired.log 2>&1

# ShowBox Billing Panel - Cleanup Old Reminders (Monthly)
0 2 1 * * /usr/bin/php /var/www/showbox/cleanup_old_reminders.php >> /var/www/showbox/logs/cleanup_cron.log 2>&1
```

### Verify cron jobs
```bash
crontab -l
```

### Create logs directory
```bash
mkdir -p /var/www/showbox/logs
chown www-data:www-data /var/www/showbox/logs
chmod 755 /var/www/showbox/logs
```

---

## Web Server Configuration

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName billing.yourdomain.com
    DocumentRoot /var/www/showbox

    <Directory /var/www/showbox>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to sensitive files
    <FilesMatch "^(config\.php|\.htaccess|\.git)">
        Require all denied
    </FilesMatch>

    # Block access to cron directory from web
    <Directory /var/www/showbox/cron>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/showbox_error.log
    CustomLog ${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
```

### Enable required Apache modules
```bash
a2enmod rewrite
a2enmod headers
systemctl restart apache2
```

### Nginx Configuration (Alternative)

```nginx
server {
    listen 80;
    server_name billing.yourdomain.com;
    root /var/www/showbox;
    index index.php index.html;

    # Block sensitive files
    location ~ ^/(config\.php|\.git|cron/) {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Post-Deployment Checklist

### Server Configuration
- [ ] Files deployed to `/var/www/showbox/`
- [ ] File ownership set to `www-data:www-data`
- [ ] File permissions set correctly (644 for files, 755 for directories)
- [ ] MySQL timezone set to `+03:30` (Asia/Tehran)
- [ ] MySQL restarted after timezone change
- [ ] All cron jobs added to crontab
- [ ] Logs directory created with correct permissions

### Verify Services
- [ ] MySQL timezone matches server time: `mysql -e "SELECT NOW();"`
- [ ] PHP timezone is Asia/Tehran: `php -r "echo date_default_timezone_get();"`
- [ ] Cron jobs listed: `crontab -l`
- [ ] Web server running: `systemctl status apache2` (or nginx)

### Test Functionality
- [ ] Login page loads: `https://yourdomain.com/`
- [ ] Dashboard loads after login
- [ ] Accounts list displays
- [ ] Bulk selection works (admin only)
- [ ] Manual cron test: `php /var/www/showbox/cron/cron_disable_expired_accounts.php`

### Security
- [ ] Config.php not accessible from web
- [ ] Cron directory not accessible from web
- [ ] HTTPS enabled (recommended)
- [ ] Firewall configured

---

## Troubleshooting

### API returns 500 error
```bash
# Check PHP error log
tail -f /var/log/apache2/error.log

# Check file permissions
ls -la /var/www/showbox/api/
```

### Cron not running
```bash
# Check cron service
systemctl status cron

# Check cron logs
grep CRON /var/log/syslog
```

### MySQL timezone wrong
```bash
# Check current timezone
mysql -e "SELECT @@global.time_zone;"

# If not +03:30, edit config and restart
nano /etc/mysql/mysql.conf.d/mysqld.cnf
systemctl restart mysql
```

### Accounts not being disabled
```bash
# Run cron manually and check output
php /var/www/showbox/cron/cron_disable_expired_accounts.php

# Check the log
tail -50 /var/log/showbox-disable-expired.log
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.17.3 | 2025-11-28 | Split Full Name into First Name + Last Name in Add Account modal |
| 1.17.2 | 2025-11-28 | Added bulk account selection, file permissions documentation |
| 1.17.1 | 2025-11-28 | Added auto-disable cron, MySQL timezone fix |
| 1.17.0 | 2025-11-27 | Reseller payments system |

---

## Contact

For issues or questions:
- Developer: Kambiz Koosheshi
- System: ShowBox Billing Panel
