# Deployment Summary - Version 1.17.0

**Release Date:** November 27, 2025
**Version:** 1.17.0 - Reseller Payments & Balance Tracking System
**Status:** Production Release

---

## Overview

This release adds a comprehensive payment tracking system for resellers, enabling admin to record payments, track balances, and generate period reports.

---

## Changes Summary

### New Features
- **Payment Recording** - Record reseller deposits with bank, date, amount, reference
- **Balance Calculation** - Automatic balance = Total Sales - Total Payments
- **Iranian Banks** - 39 banks in dropdown (state-owned, private, credit institutions)
- **Shamsi Calendar** - Persian calendar support in date filters and display
- **Push Notifications** - Reseller receives notification when payment recorded
- **Cancel Payments** - Soft delete with mandatory reason

### New Database Tables
| Table | Purpose |
|-------|---------|
| `_reseller_payments` | Payment/deposit tracking |
| `_iranian_banks` | Iranian banks reference list |

### New API Endpoints
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/add_reseller_payment.php` | POST | Record new payment |
| `/api/get_reseller_payments.php` | GET | Get payment history |
| `/api/get_reseller_balance.php` | GET | Calculate balance |
| `/api/cancel_reseller_payment.php` | POST | Cancel payment |
| `/api/get_iranian_banks.php` | GET | Get banks list |

### UI Changes
- New "💳 Reseller Payments & Balance" section in Accounting tab
- Balance summary cards with status indicators
- Payments table with filtering
- Add Payment modal with bank dropdown
- Cancel Payment modal with reason field
- Shamsi/Gregorian calendar toggle

---

## Files Modified

### PHP Backend (api/)
```
api/add_reseller_payment.php       (NEW)
api/get_reseller_payments.php      (NEW)
api/get_reseller_balance.php       (NEW)
api/cancel_reseller_payment.php    (NEW)
api/get_iranian_banks.php          (NEW)
api/push_helper.php                (MODIFIED - new notification function)
```

### PHP Scripts (scripts/)
```
scripts/create_reseller_payments_table.php  (NEW)
```

### Frontend
```
dashboard.php          (MODIFIED - new UI section)
dashboard.js           (MODIFIED - new functions)
dashboard.css          (MODIFIED - new styles)
service-worker.js      (MODIFIED - cache version)
index.html             (MODIFIED - version)
```

### Documentation (docs/)
```
DATABASE_SCHEMA.md       (UPDATED)
API_DOCUMENTATION.md     (UPDATED)
CHANGELOG.md             (UPDATED)
DEPLOYMENT_v1.17.0_SUMMARY.md  (NEW)
```

---

## Deployment Steps

### 1. Run Database Migration
```bash
ssh root@192.168.15.230
cd /var/www/showbox
php scripts/create_reseller_payments_table.php
```

Expected output:
```
===========================================
ShowBox Billing - Reseller Payments Migration
Version: 1.17.0
===========================================

[1/3] Creating _reseller_payments table...
   ✓ Table _reseller_payments created successfully

[2/3] Creating _iranian_banks reference table...
   ✓ Table _iranian_banks created successfully

[3/3] Inserting Iranian banks list...
   ✓ Inserted 39 Iranian banks

===========================================
Migration completed successfully!
===========================================
```

### 2. Deploy Files
```bash
# From local machine
rsync -avz "/path/to/showbox/" root@192.168.15.230:/var/www/showbox/

# Set permissions
ssh root@192.168.15.230 "chmod 644 /var/www/showbox/dashboard.css /var/www/showbox/dashboard.js"
```

### 3. Verify Deployment
- Clear browser cache (Ctrl+Shift+R)
- Check version shows v1.17.0 in footer
- Verify "💳 Reseller Payments & Balance" section appears in Accounting tab
- Test Add Payment functionality

---

## Version Updates

### Files Updated with v1.17.0
| File | Location |
|------|----------|
| `service-worker.js` | Line 1: `CACHE_NAME = 'showbox-billing-v1.17.0'` |
| `dashboard.php` | Line 114: `v1.17.0` |
| `index.html` | Line 288: `v1.17.0` |
| `README.md` | Badge and version history |

---

## Audit Log Events

New audit events added:
| Action | Target Type | Description |
|--------|-------------|-------------|
| create | reseller_payment | Payment recorded |
| update | reseller_payment | Payment cancelled |

---

## Permission Matrix

| Role | View Payments | Add Payment | Cancel Payment |
|------|---------------|-------------|----------------|
| Super Admin | All | ✓ | ✓ |
| Reseller Admin | All | ✓ | ✓ |
| Reseller | Own only | ✗ | ✗ |
| Observer | All | ✗ | ✗ |

---

## Balance Calculation

**Formula:** `Balance = Total Sales - Total Payments`

- **Total Sales** = Sum of all transactions (debit entries in `_transactions`)
- **Total Payments** = Sum of all active payments in `_reseller_payments`

**Status:**
- **Positive Balance** = Reseller owes money (بدهکار/Debtor)
- **Negative Balance** = Reseller has credit (طلبکار/Creditor)
- **Zero Balance** = Settled (تسویه)

---

## Rollback Instructions

If rollback is needed:

```sql
-- Drop new tables
DROP TABLE IF EXISTS _reseller_payments;
DROP TABLE IF EXISTS _iranian_banks;
```

Restore previous version files from backup.

---

## Testing Checklist

- [ ] Database migration runs without errors
- [ ] Version shows v1.17.0 in UI
- [ ] Add Payment modal works correctly
- [ ] Payment appears in table after adding
- [ ] Balance calculates correctly
- [ ] Cancel Payment requires reason
- [ ] Reseller can only see own payments
- [ ] Push notification sent when payment recorded
- [ ] Shamsi calendar works in date filters
- [ ] Iranian banks dropdown populated

---

**Deployment by:** ShowBox Development Team
**Version:** 1.17.0
**Date:** November 27, 2025
