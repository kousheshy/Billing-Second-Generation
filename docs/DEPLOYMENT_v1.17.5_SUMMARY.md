# Deployment Summary - v1.17.5

**Date:** November 28, 2025
**Version:** 1.17.5
**Status:** Production Release

---

## Overview

This release introduces **Unlimited Plans** - a new plan type that is currency-agnostic and category-independent, allowing it to work across all resellers and in both Add Account and Edit/Renew flows.

---

## Features Added

### Unlimited Plans

| Feature | Description |
|---------|-------------|
| Automatic Detection | Plans with `days = 0` are treated as unlimited |
| Currency Wildcard | Currency is set to `*` (available to all currencies) |
| Price Zero | Price is automatically set to `0` |
| Category Independent | Appears in both new_device and renew_device sections |
| Universal Access | Available to all resellers regardless of their currency |

### UI Improvements

| Change | Description |
|--------|-------------|
| Add Account Modal | "Assign to Reseller" moved above "Plan" dropdown |
| Plan Display | Unlimited plans show "-" for currency, "Unlimited" for duration |
| Plan Cards | Display "Unlimited" instead of "0 days" |

---

## Files Modified

### PHP API Files

| File | Changes |
|------|---------|
| `api/add_plan.php` | Set currency='*' and price=0 when days=0 |
| `api/edit_plan.php` | Update currency to '*' when editing to days=0, added debug logging |
| `api/get_plans.php` | Include unlimited plans (currency='*') for all resellers |
| `api/add_account.php` | Skip currency validation for unlimited plans |
| `api/edit_account.php` | Skip currency validation for unlimited plans |
| `api/assign_plans.php` | Allow assigning unlimited plans to any reseller |

### Frontend Files

| File | Changes |
|------|---------|
| `dashboard.js` | Category-independent filtering, unlimited plan display formatting |
| `dashboard.php` | Moved Assign to Reseller above Plan, version updated to 1.17.5 |
| `index.html` | Version updated to 1.17.5 |
| `service-worker.js` | Cache version updated to v1.17.5 |

### Documentation Files

| File | Changes |
|------|---------|
| `docs/CHANGELOG.md` | Added v1.17.5 section |
| `docs/API_DOCUMENTATION.md` | Added Unlimited Plans section, version 1.17.5 |
| `README.md` | Version badge updated to 1.17.5 |
| `docs/DEPLOYMENT_v1.17.5_SUMMARY.md` | This file |

---

## Database Changes

**No new tables required.**

The existing `_plans` table now supports:
- `currency_id = '*'` for unlimited plans
- `days = 0` for unlimited duration
- `price = 0.00` for unlimited plans

---

## Deployment Steps

### 1. Deploy PHP Files

```bash
# Files to deploy
scp api/add_plan.php root@SERVER:/var/www/showbox/api/
scp api/edit_plan.php root@SERVER:/var/www/showbox/api/
scp api/get_plans.php root@SERVER:/var/www/showbox/api/
scp api/add_account.php root@SERVER:/var/www/showbox/api/
scp api/edit_account.php root@SERVER:/var/www/showbox/api/
scp api/assign_plans.php root@SERVER:/var/www/showbox/api/

# Fix permissions
ssh root@SERVER "chmod 644 /var/www/showbox/api/*.php && chown www-data:www-data /var/www/showbox/api/*.php"
```

### 2. Deploy Frontend Files

```bash
scp dashboard.js root@SERVER:/var/www/showbox/
scp dashboard.php root@SERVER:/var/www/showbox/
scp index.html root@SERVER:/var/www/showbox/
scp service-worker.js root@SERVER:/var/www/showbox/

# Fix permissions
ssh root@SERVER "chmod 644 /var/www/showbox/*.{js,php,html} && chown www-data:www-data /var/www/showbox/*.{js,php,html}"
```

### 3. Deploy Documentation

```bash
scp docs/*.md root@SERVER:/var/www/showbox/docs/
scp README.md root@SERVER:/var/www/showbox/

# Fix permissions
ssh root@SERVER "chmod 644 /var/www/showbox/docs/*.md /var/www/showbox/README.md"
```

### 4. Clear Browser Cache

After deployment, users should:
1. Clear browser cache, or
2. Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

The service worker will auto-update with the new cache version (v1.17.5).

---

## Testing Checklist

### Unlimited Plans

- [ ] Create new plan with days=0
  - [ ] Currency automatically set to '*'
  - [ ] Price automatically set to 0
- [ ] Edit existing plan to days=0
  - [ ] Currency changes to '*'
- [ ] Verify unlimited plan appears in:
  - [ ] Plans tab (shows "-" for currency, "Unlimited" for days)
  - [ ] Add Account modal (for all resellers)
  - [ ] Edit/Renew modal
  - [ ] Assign Plans modal

### Currency Validation

- [ ] Add account with unlimited plan for IRR reseller
- [ ] Add account with unlimited plan for GBP reseller
- [ ] Edit account to use unlimited plan (any currency)
- [ ] Assign unlimited plan to reseller (any currency)

### UI

- [ ] "Assign to Reseller" appears above "Plan" in Add Account modal
- [ ] Plan cards show "Unlimited" instead of "0 days"
- [ ] Price shows "-" for unlimited plans

---

## Rollback Procedure

If issues occur:

```bash
# Restore from backup
ssh root@SERVER "cd /var/www/showbox && git checkout v1.17.4 -- api/add_plan.php api/edit_plan.php api/get_plans.php api/add_account.php api/edit_account.php api/assign_plans.php dashboard.js dashboard.php"
```

---

## Version References

| Location | Value |
|----------|-------|
| `dashboard.php` header | v1.17.5 |
| `index.html` footer | v1.17.5 |
| `service-worker.js` cache | showbox-billing-v1.17.5 |
| `README.md` badge | 1.17.5 |
| `docs/CHANGELOG.md` | [1.17.5] - 2025-11-28 |
| `docs/API_DOCUMENTATION.md` | Version: 1.17.5 |

---

## Known Issues

None at this time.

---

## Support

For issues related to this deployment, contact the development team or check the error logs:

```bash
tail -f /var/log/apache2/showbox_error.log
```
