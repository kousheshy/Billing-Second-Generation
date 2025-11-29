# Deployment Summary - v1.17.6

**Date:** November 28, 2025
**Version:** 1.17.6
**Status:** Production Release
**Type:** Bug Fix Release

---

## Overview

This release fixes critical bugs in the Unlimited Plans feature introduced in v1.17.5.

---

## Bug Fixes

### 1. Unlimited Plan Renewal Date Error (Critical)

| Aspect | Details |
|--------|---------|
| **Error** | `SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect date value: '' for column 'end_date'` |
| **Root Cause** | When renewing with unlimited plan (days=0), end_date was set to empty string `""` |
| **Solution** | Set end_date to `'2099-12-31 23:59:59'` (far-future date) for unlimited plans |
| **Impact** | Admins and resellers could not renew accounts using unlimited plans |
| **File** | `api/edit_account.php` |

### 2. Discount Field for Unlimited Plans (UX Enhancement)

| Aspect | Details |
|--------|---------|
| **Issue** | Discount field remained enabled for unlimited plans (which have price=0) |
| **Solution** | Auto-disable discount field when unlimited plan is selected |
| **Behavior** | Field disabled, value reset to 0, grayed out (opacity: 0.5), tooltip added |
| **Coverage** | All 4 plan selection scenarios |
| **File** | `dashboard.js` |

---

## Files Modified

### PHP API Files

| File | Changes |
|------|---------|
| `api/edit_account.php` | Fixed unlimited plan end_date calculation |

### Frontend Files

| File | Changes |
|------|---------|
| `dashboard.js` | Added discount field disable logic for unlimited plans in 4 locations |
| `dashboard.php` | Version updated to 1.17.6 |
| `index.html` | Version updated to 1.17.6 |
| `service-worker.js` | Cache version updated to v1.17.6 |

### Documentation Files

| File | Changes |
|------|---------|
| `docs/CHANGELOG.md` | Added v1.17.6 section |
| `docs/API_DOCUMENTATION.md` | Version updated to 1.17.6 |
| `README.md` | Version badge and history updated to 1.17.6 |
| `docs/DEPLOYMENT_v1.17.6_SUMMARY.md` | This file |

---

## Technical Details

### End Date Fix (edit_account.php)

**Before (v1.17.5 - Broken):**
```php
if ($plan_days === 0) {
    // Unlimited plan - set expiration to empty
    $new_expiration_date = "";  // MySQL error!
}
```

**After (v1.17.6 - Fixed):**
```php
if ($plan_days === 0) {
    // Unlimited plan - set expiration to far future date (v1.17.6 fix)
    $new_expiration_date = '2099-12-31 23:59:59';
    error_log("edit_account.php: Unlimited plan (days=0), setting expiration to 2099-12-31");
}
```

### Discount Field Disable (dashboard.js)

**Added to 4 locations:**

1. **loadPlans()** - Admin dropdown in Add Account
2. **loadPlansForEdit()** - Admin dropdown in Edit/Renew
3. **loadNewDevicePlans()** - Reseller plan cards in Add Account
4. **loadRenewalPlans()** - Reseller plan cards in Edit/Renew

**Implementation Pattern:**
```javascript
// v1.17.6: Disable discount field for unlimited plans
const discountGroup = document.getElementById('add-discount-group');
const discountInput = document.getElementById('add-discount');
const discountDisplay = document.getElementById('add-discount-display');

if (discountGroup && discountInput) {
    const isUnlimitedPlan = this.dataset.isUnlimited === 'true';
    if (isUnlimitedPlan) {
        discountInput.value = 0;
        if (discountDisplay) discountDisplay.value = '';
        discountInput.disabled = true;
        if (discountDisplay) discountDisplay.disabled = true;
        discountGroup.style.opacity = '0.5';
        discountGroup.title = 'Discount not available for unlimited plans';
    } else {
        discountInput.disabled = false;
        if (discountDisplay) discountDisplay.disabled = false;
        discountGroup.style.opacity = '1';
        discountGroup.title = '';
    }
}
```

---

## Deployment Steps

### 1. Deploy PHP Files

```bash
sshpass -p 'PASSWORD' scp -o StrictHostKeyChecking=no \
    api/edit_account.php \
    root@192.168.15.230:/var/www/showbox/api/

# Fix permissions
sshpass -p 'PASSWORD' ssh -o StrictHostKeyChecking=no root@192.168.15.230 \
    "chmod 644 /var/www/showbox/api/edit_account.php && \
     chown www-data:www-data /var/www/showbox/api/edit_account.php"
```

### 2. Deploy Frontend Files

```bash
sshpass -p 'PASSWORD' scp -o StrictHostKeyChecking=no \
    dashboard.js \
    dashboard.php \
    index.html \
    service-worker.js \
    root@192.168.15.230:/var/www/showbox/

# Fix permissions
sshpass -p 'PASSWORD' ssh -o StrictHostKeyChecking=no root@192.168.15.230 \
    "chmod 644 /var/www/showbox/*.{js,php,html} && \
     chown www-data:www-data /var/www/showbox/*.{js,php,html}"
```

### 3. Deploy Documentation (Optional)

```bash
sshpass -p 'PASSWORD' scp -o StrictHostKeyChecking=no \
    docs/*.md \
    README.md \
    root@192.168.15.230:/var/www/showbox/docs/
```

### 4. Clear Browser Cache

After deployment, users should:
1. Clear browser cache, or
2. Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

The service worker will auto-update with the new cache version (v1.17.6).

---

## Testing Checklist

### Unlimited Plan Renewal

- [x] Renew account with unlimited plan (admin)
- [x] Verify end_date is set to 2099-12-31
- [x] No MySQL date format errors

### Discount Field Behavior

- [ ] Add Account (Admin dropdown): Select unlimited plan -> discount disabled
- [ ] Add Account (Reseller cards): Select unlimited plan -> discount disabled
- [ ] Edit/Renew (Admin dropdown): Select unlimited plan -> discount disabled
- [ ] Edit/Renew (Reseller cards): Select unlimited plan -> discount disabled
- [ ] Switching back to regular plan -> discount re-enabled

---

## Rollback Procedure

If issues occur:

```bash
# Restore from v1.17.5
ssh root@192.168.15.230 "cd /var/www/showbox && \
    git checkout v1.17.5 -- api/edit_account.php dashboard.js"
```

---

## Version References

| Location | Value |
|----------|-------|
| `dashboard.php` header | v1.17.6 |
| `dashboard.js` header | v1.17.6 |
| `index.html` footer | v1.17.6 |
| `service-worker.js` cache | showbox-billing-v1.17.6 |
| `README.md` badge | 1.17.6 |
| `docs/CHANGELOG.md` | [1.17.6] - 2025-11-28 |
| `docs/API_DOCUMENTATION.md` | Version: 1.17.6 |

---

## Known Issues

None at this time.

---

## Related Versions

| Version | Date | Description |
|---------|------|-------------|
| v1.17.6 | 2025-11-28 | Bug fixes for unlimited plans |
| v1.17.5 | 2025-11-28 | Unlimited Plans feature introduction |
| v1.17.4 | 2025-11-28 | STB Action Logs feature |

---

## Support

For issues related to this deployment:

```bash
# Check error logs
tail -f /var/log/apache2/showbox_error.log

# Check PHP logs for unlimited plan debug info
grep "edit_account.php" /var/log/apache2/showbox_error.log | tail -20
```
