# Deployment Summary v1.18.1 - Hotfix Release

**Date:** November 28, 2025
**Version:** 1.18.1
**Status:** Production Release
**Type:** Hotfix

---

## Overview

Version 1.18.1 is a hotfix release that addresses file permission issues discovered after the v1.18.0 deployment. It also reverts the experimental Server 2 failure notification system that was causing issues.

---

## Bug Fixes

### 1. File Permission Fix (Critical)

**Issue:** `toggle_account_status.php` returned HTTP 500 Internal Server Error
- **Error:** `PHP Fatal error: Failed opening required '/var/www/showbox/api/toggle_account_status.php'`
- **Root Cause:** File permissions were set to `600` (owner read/write only)
- **Solution:** Changed permissions to `644` (owner read/write, others read)

**Affected Files:**
| File | Old Permissions | New Permissions |
|------|-----------------|-----------------|
| `api/toggle_account_status.php` | 600 | 644 |

**Fix Command:**
```bash
chmod 644 /var/www/showbox/api/toggle_account_status.php
```

### 2. Server 2 Notification Reversion

**Change:** Reverted experimental Server 2 failure notification system

**Reason:** User requested removal of Server 2 failure notifications

**Files Affected:**
| File | Change |
|------|--------|
| `api/toggle_account_status.php` | Removed Server 2 notification calls |
| `api/edit_account.php` | Removed handleServer2Failure calls |
| `api/add_account.php` | Removed notification includes |
| `api/remove_account.php` | Removed notification calls |
| `api/change_status.php` | Removed notification calls |
| `api/send_message.php` | Removed notification calls |
| `api/send_stb_message.php` | Removed notification calls |
| `api/send_stb_event.php` | Removed notification calls |

**Note:** `api/server2_notification_helper.php` still exists but is not included in any files.

---

## Version Numbers Updated

| Location | Old Version | New Version |
|----------|-------------|-------------|
| `service-worker.js` (CACHE_NAME) | v1.18.0 | v1.18.1 |
| `dashboard.php` (app-version) | v1.17.6 | v1.18.1 |
| `index.html` (login footer) | - | v1.18.1 |
| `docs/CHANGELOG.md` | v1.18.0 | v1.18.1 section added |
| `README.md` (badge) | 1.17.6 | 1.18.1 |
| `docs/DATABASE_SCHEMA.md` | v1.18.0 | v1.18.1 |
| `docs/API_DOCUMENTATION.md` | v1.18.0 | v1.18.1 |

---

## Deployment Steps

### 1. Apply File Permission Fix
```bash
ssh root@192.168.15.230 "chmod 644 /var/www/showbox/api/toggle_account_status.php"
```

### 2. Deploy Updated Files
```bash
rsync -avz --exclude='.git' \
  dashboard.php \
  service-worker.js \
  index.html \
  root@192.168.15.230:/var/www/showbox/
```

### 3. Verify Deployment
```bash
# Check file permissions
ssh root@192.168.15.230 "ls -la /var/www/showbox/api/toggle_account_status.php"

# Verify PHP syntax
ssh root@192.168.15.230 "php -l /var/www/showbox/api/toggle_account_status.php"
```

### 4. Clear Browser Cache
- Users should clear browser cache or use Ctrl+Shift+R
- PWA users may need to reinstall the app

---

## Testing Checklist

- [x] Toggle account status works (enable/disable)
- [x] No 500 Internal Server Error on toggle
- [x] File permissions verified (644)
- [x] PHP syntax verified (no errors)
- [ ] All API endpoints return valid JSON
- [ ] Account creation works
- [ ] Account editing/renewal works
- [ ] Account deletion works
- [ ] STB message sending works
- [ ] STB event sending works

---

## Error Log Reference

**Error Before Fix:**
```
[php:warn] PHP Warning: Unknown: Failed to open stream: Permission denied
[php:error] PHP Fatal error: Failed opening required '/var/www/showbox/api/toggle_account_status.php'
```

**Error Log Location:**
```
/var/log/apache2/showbox_error.log.1
```

---

## Rollback Plan

If issues persist:
```bash
# Restore previous toggle_account_status.php from git
git checkout HEAD~1 -- api/toggle_account_status.php
rsync -avz api/toggle_account_status.php root@192.168.15.230:/var/www/showbox/api/
ssh root@192.168.15.230 "chmod 644 /var/www/showbox/api/toggle_account_status.php"
```

---

## Related Documentation

- [DEPLOYMENT_v1.18.0_SUMMARY.md](DEPLOYMENT_v1.18.0_SUMMARY.md) - Email Notification System
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) - Full database documentation
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - API endpoint reference
- [CHANGELOG.md](CHANGELOG.md) - Full version history

---

## Technical Notes

### Server 2 Dual Mode Behavior

The system still supports dual server mode, but without failure notifications:

```php
// Dual server mode check (still active)
$dual_server_mode = isset($DUAL_SERVER_MODE_ENABLED) && $DUAL_SERVER_MODE_ENABLED
    && ($WEBSERVICE_BASE_URL !== $WEBSERVICE_2_BASE_URL);

// Server 2 operations still execute (silent failure)
if($dual_server_mode) {
    $res2 = api_send_request(...);
    $decoded2 = json_decode($res2);
    // No notification on failure - operation continues
}
```

### Permission Requirements

All PHP files in `/var/www/showbox/api/` should have:
- **Owner:** www-data:www-data
- **Permissions:** 644 (rw-r--r--)

```bash
# Fix all API file permissions
ssh root@192.168.15.230 "chown www-data:www-data /var/www/showbox/api/*.php && chmod 644 /var/www/showbox/api/*.php"
```

---

**Deployed By:** Claude Code
**Deployment Time:** November 28, 2025
