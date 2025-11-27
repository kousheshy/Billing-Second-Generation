# Deployment Summary - v1.17.1

**Date:** November 28, 2025
**Version:** 1.17.1
**Status:** Production Release
**Server:** 192.168.15.230

---

## Overview

This release adds automatic disabling of expired accounts and fixes MySQL timezone configuration to synchronize with Asia/Tehran.

---

## Changes Deployed

### 1. New Cron Job: Auto-Disable Expired Accounts

**File:** `cron/cron_disable_expired_accounts.php`

**Purpose:** Automatically disable accounts that have passed their expiration date in both:
- Billing database (status = 0)
- Stalker Portal server (via API)

**Features:**
- Timezone-aware (uses PHP's Asia/Tehran)
- Dual server support
- Audit logging
- Dry run mode for testing
- Detailed progress logging

### 2. MySQL Timezone Fix

**Configuration File:** `/etc/mysql/mysql.conf.d/mysqld.cnf`

**Change Added:**
```ini
# Timezone setting for Asia/Tehran
default-time-zone = "+03:30"
```

**Before:**
- MySQL NOW(): UTC time (e.g., 2025-11-27 21:47)
- System time: Tehran time (e.g., 2025-11-28 01:17)

**After:**
- MySQL NOW(): Tehran time (e.g., 2025-11-28 01:25)
- System time: Tehran time (e.g., 2025-11-28 01:25)

### 3. Version Updates

| File | Change |
|------|--------|
| `service-worker.js` | Cache version: `showbox-billing-v1.17.1` |
| `dashboard.php` | Header version: `v1.17.1` |
| `index.html` | Login page version: `v1.17.1` |
| `README.md` | Version badge: `1.17.1` |
| `docs/API_DOCUMENTATION.md` | Version: `1.17.1` |
| `docs/CHANGELOG.md` | Added v1.17.1 section |

---

## Files Modified

### New Files
| File | Description |
|------|-------------|
| `cron/cron_disable_expired_accounts.php` | Auto-disable expired accounts cron job |
| `docs/AUTO_DISABLE_EXPIRED_ACCOUNTS.md` | Feature documentation |
| `docs/DEPLOYMENT_v1.17.1_SUMMARY.md` | This deployment summary |

### Updated Files
| File | Changes |
|------|---------|
| `service-worker.js` | Cache version bump |
| `dashboard.php` | Version display |
| `index.html` | Version display |
| `README.md` | Version badge + version history |
| `docs/API_DOCUMENTATION.md` | Version + date |
| `docs/CHANGELOG.md` | v1.17.1 entry |
| `docs/CRON_SETUP_INSTRUCTIONS.md` | Added auto-disable section |

### Server Configuration
| File | Changes |
|------|---------|
| `/etc/mysql/mysql.conf.d/mysqld.cnf` | Added `default-time-zone = "+03:30"` |

---

## Deployment Commands

### 1. Deploy Files to Server

```bash
# Deploy cron file
scp cron/cron_disable_expired_accounts.php root@192.168.15.230:/var/www/showbox/cron/

# Deploy updated files
scp service-worker.js dashboard.php index.html root@192.168.15.230:/var/www/showbox/

# Deploy documentation
scp -r docs/ root@192.168.15.230:/var/www/showbox/
```

### 2. MySQL Timezone Configuration (Already Done)

```bash
# Add timezone to MySQL config
echo '' >> /etc/mysql/mysql.conf.d/mysqld.cnf
echo '# Timezone setting for Asia/Tehran' >> /etc/mysql/mysql.conf.d/mysqld.cnf
echo 'default-time-zone = "+03:30"' >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Restart MySQL
systemctl restart mysql
```

### 3. Set Up Cron Job

```bash
# Add to crontab
crontab -e

# Add this line:
0 * * * * php /var/www/showbox/cron/cron_disable_expired_accounts.php >> /var/log/showbox-disable-expired.log 2>&1
```

---

## Verification Steps

### 1. Verify MySQL Timezone

```bash
mysql -u root -e "SELECT @@global.time_zone, NOW();"
```

Expected output:
```
@@global.time_zone | NOW()
+03:30             | 2025-11-28 01:25:00
```

### 2. Test Auto-Disable Cron

```bash
php /var/www/showbox/cron/cron_disable_expired_accounts.php
```

Expected output:
```
[2025-11-28 01:17:49] === Starting Auto-Disable Expired Accounts ===
[2025-11-28 01:17:49] Server timezone: Asia/Tehran | Today's date: 2025-11-28 01:17:49
[2025-11-28 01:17:49] Found X expired accounts with status=enabled
...
```

### 3. Verify Version Numbers

- Login page shows: `v1.17.1`
- Dashboard header shows: `v1.17.1`
- Browser cache cleared (service worker updates)

---

## Rollback Procedure

If issues arise:

### 1. Disable Cron Job

```bash
crontab -e
# Comment out or remove the auto-disable cron line
```

### 2. Revert MySQL Timezone (if needed)

```bash
# Remove the timezone line from MySQL config
sed -i '/default-time-zone/d' /etc/mysql/mysql.conf.d/mysqld.cnf
systemctl restart mysql
```

### 3. Revert Version Numbers

Deploy previous version files from backup or git.

---

## Post-Deployment Checklist

- [x] Cron file deployed
- [x] MySQL timezone configured
- [x] MySQL service restarted
- [x] Auto-disable cron tested manually
- [x] Version numbers updated
- [x] Service worker cache version bumped
- [x] Documentation updated
- [ ] Cron job added to crontab (manual step)
- [ ] Monitor first automated run

---

## Known Issues

None identified.

---

## Related Documentation

- [AUTO_DISABLE_EXPIRED_ACCOUNTS.md](AUTO_DISABLE_EXPIRED_ACCOUNTS.md)
- [CRON_SETUP_INSTRUCTIONS.md](CRON_SETUP_INSTRUCTIONS.md)
- [CHANGELOG.md](CHANGELOG.md)

---

## Contact

For issues or questions:
- Developer: Kambiz Koosheshi
- System: ShowBox Billing Panel v1.17.1
