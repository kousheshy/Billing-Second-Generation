# Deployment Summary v1.18.0 - Email Notification System

**Date:** November 28, 2025
**Version:** 1.18.0
**Status:** Production Release

---

## Overview

Version 1.18.0 introduces a comprehensive email notification system using PHPMailer with cPanel SMTP integration. The system supports automatic emails on account events and manual email sending to customers.

---

## New Features

### Email System
- SMTP configuration with cPanel mail server (mail.showboxtv.tv)
- Support for TLS (port 587) and SSL (port 465) encryption
- Email credential management with password visibility toggle
- Automatic emails on:
  - New account creation
  - Account renewal
  - Account expiry (multi-stage reminders)
- Manual email sending (single recipient or multiple accounts)
- Multi-stage expiry reminders (7 days, 3 days, 1 day, expired)
- CC notifications to admin and reseller (configurable)
- Email template system with variables
- Email history with filtering

### Access Control
- Mail feature restricted to super admin only
- Both UI and API level permission checks
- Mail tab hidden by default, shown only for super_user=1

---

## Files Created

### API Endpoints
| File | Purpose |
|------|---------|
| `api/mail_helper.php` | Core mail functions |
| `api/get_mail_settings.php` | Get configuration & templates |
| `api/update_mail_settings.php` | Update SMTP settings |
| `api/test_mail_connection.php` | Test SMTP connection |
| `api/send_mail.php` | Send manual emails |
| `api/save_mail_template.php` | Create/update templates |
| `api/delete_mail_template.php` | Delete templates |
| `api/get_mail_template.php` | Get template details |
| `api/get_mail_logs.php` | Get email history |

### Frontend
| File | Purpose |
|------|---------|
| `mail-functions.js` | UI logic for mail tab |

### Scripts
| File | Purpose |
|------|---------|
| `scripts/create_mail_tables.php` | Database migration |
| `cron/cron_send_expiry_mail.php` | Automated expiry reminders |

---

## Files Modified

| File | Changes |
|------|---------|
| `dashboard.php` | Added Mail tab UI in Messaging section |
| `dashboard.js` | Added Mail tab visibility for super admin, version 1.18.0 |
| `service-worker.js` | Updated cache version to v1.18.0 |
| `index.html` | Updated version to v1.18.0 |
| `api/add_account.php` | Added automatic welcome email |
| `api/edit_account.php` | Added automatic renewal email |

---

## Database Changes

### New Tables (4)
1. `_mail_settings` - SMTP configuration per user
2. `_mail_templates` - Email templates with HTML body
3. `_mail_logs` - Email sending history
4. `_mail_reminder_tracking` - Multi-stage reminder tracking

### Migration
```bash
php scripts/create_mail_tables.php
```

---

## Deployment Steps

### 1. Pre-deployment
```bash
# Backup database
mysqldump -u root showboxt_panel > backup_$(date +%Y%m%d).sql
```

### 2. Deploy Files
```bash
# Deploy all files to server
rsync -avz --exclude='.git' ./ root@192.168.15.230:/var/www/showbox/

# Fix permissions
ssh root@192.168.15.230 "chown -R www-data:www-data /var/www/showbox/ && chmod 644 /var/www/showbox/*.php /var/www/showbox/*.js /var/www/showbox/api/*.php"
```

### 3. Run Database Migration
```bash
ssh root@192.168.15.230 "php /var/www/showbox/scripts/create_mail_tables.php"
```

### 4. Setup Cron Job (Optional)
```bash
# Add to crontab for automated expiry reminders
0 9 * * * /usr/bin/php /var/www/showbox/cron/cron_send_expiry_mail.php >> /var/log/showbox_mail_cron.log 2>&1
```

### 5. Verify Deployment
- Clear browser cache (Ctrl+Shift+R)
- Login as super admin
- Verify Mail tab appears in Messaging section
- Test SMTP connection
- Send test email

---

## Version Numbers Updated

| Location | Old | New |
|----------|-----|-----|
| `index.html` (login page) | v1.17.6 | v1.18.0 |
| `service-worker.js` (cache) | v1.17.6 | v1.18.0 |
| `dashboard.js` (header comment) | v1.17.6 | v1.18.0 |
| `docs/CHANGELOG.md` | - | v1.18.0 section added |
| `docs/DATABASE_SCHEMA.md` | v1.17.0 | v1.18.0 |
| `docs/API_DOCUMENTATION.md` | v1.17.6 | v1.18.0 |

---

## PHPMailer Requirement

PHPMailer library must be present in `/PHPMailer/` directory:
- `PHPMailer.php`
- `SMTP.php`
- `Exception.php`

---

## Permission Model

### UI Access
- Mail tab button: Hidden by default (`style="display:none;"`)
- JavaScript shows it only for `super_user == 1`

### API Access
All mail API endpoints check:
```php
$is_super_admin = isset($_SESSION['super_user']) && $_SESSION['super_user'] == 1;
if (!$is_super_admin) {
    echo json_encode(['error' => 1, 'message' => 'Permission denied.']);
    exit;
}
```

---

## Email Templates

4 default Persian templates are created:
1. **New Account Welcome** - Sent when account created
2. **Renewal Confirmation** - Sent when account renewed
3. **Expiry Reminder** - Sent before expiry
4. **Account Expired** - Sent when account expires

### Template Variables
- `{name}` - Customer full name
- `{mac}` - MAC address
- `{expiry_date}` - Expiration date
- `{plan_name}` - Plan name
- `{username}` - Account username
- `{password}` - Account password

---

## Rollback Plan

If issues occur:
```bash
# Restore database
mysql -u root showboxt_panel < backup_YYYYMMDD.sql

# Restore previous version files
# (keep backup of v1.17.6 files before deployment)
```

---

## Testing Checklist

- [ ] Super admin can see Mail tab
- [ ] Regular resellers cannot see Mail tab
- [ ] SMTP connection test works
- [ ] Settings save correctly
- [ ] Manual email sends successfully
- [ ] Welcome email sent on new account
- [ ] Renewal email sent on account renewal
- [ ] Email history shows correctly
- [ ] Template editing works
