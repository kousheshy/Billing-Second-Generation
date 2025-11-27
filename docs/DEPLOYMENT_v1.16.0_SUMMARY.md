# Deployment Summary - Version 1.16.0

## Immutable Transaction Correction System

**Release Date:** November 27, 2025
**Version:** 1.16.0
**Status:** Production Deployed

---

## Overview

Version 1.16.0 introduces an **Immutable Transaction Correction System** that fundamentally changes how financial transactions are handled in the ShowBox Billing Panel. Transactions can no longer be deleted - instead, they can be corrected or voided with mandatory explanatory notes.

---

## Key Changes

### 1. Philosophy Change

**Before (v1.15.3):**
- Transactions were deleted when accounts were removed
- Financial history could be lost

**After (v1.16.0):**
- Transactions are NEVER deleted
- Corrections applied with mandatory notes
- Complete audit trail preserved
- Voided transactions show as net 0

### 2. New Features

| Feature | Description |
|---------|-------------|
| Edit Transaction Modal | New UI for applying corrections |
| Live Amount Preview | Real-time calculation of new amount |
| Void Transaction | Checkbox to nullify transactions (net = 0) |
| Correction Badges | Visual indicators in tables and exports |
| Thousand Separators | Formatted number input |

### 3. Permission Matrix

| Role | Can View | Can Edit |
|------|----------|----------|
| Super Admin | Yes | Yes |
| Reseller Admin | Yes | Yes |
| Reseller | Yes | No |
| Observer | Yes | No |

---

## Database Migration

### New Columns in `_transactions` Table

```sql
ALTER TABLE _transactions ADD COLUMN correction_amount DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE _transactions ADD COLUMN correction_note TEXT DEFAULT NULL;
ALTER TABLE _transactions ADD COLUMN corrected_by INT DEFAULT NULL;
ALTER TABLE _transactions ADD COLUMN corrected_by_username VARCHAR(100) DEFAULT NULL;
ALTER TABLE _transactions ADD COLUMN corrected_at DATETIME DEFAULT NULL;
ALTER TABLE _transactions ADD COLUMN status ENUM('active','corrected','voided') DEFAULT 'active';
ALTER TABLE _transactions ADD INDEX idx_status (status);
ALTER TABLE _transactions ADD INDEX idx_corrected_at (corrected_at);
```

### Migration Script

Run on production server:
```bash
php scripts/add_transaction_corrections.php
```

---

## Files Modified

### New Files
| File | Description |
|------|-------------|
| `api/edit_transaction.php` | Transaction correction API endpoint |
| `scripts/add_transaction_corrections.php` | Database migration script |
| `docs/DEPLOYMENT_v1.16.0_SUMMARY.md` | This deployment summary |

### Updated Backend Files
| File | Changes |
|------|---------|
| `api/get_transactions.php` | Returns correction fields and net amount |
| `api/get_monthly_invoice.php` | Uses net amounts, includes correction info |
| `api/remove_account.php` | No longer deletes transactions (preserved) |
| `api/sync_accounts.php` | Orphans logged only, transactions preserved |

### Updated Frontend Files
| File | Changes |
|------|---------|
| `dashboard.php` | Added Edit Transaction modal with live preview |
| `dashboard.js` | Correction display, formatting, PDF/Excel updates |
| `dashboard.css` | Correction badge styles |
| `index.html` | Version number update |
| `service-worker.js` | Cache version update |

### Updated Documentation
| File | Changes |
|------|---------|
| `README.md` | Version badge, v1.16.0 history entry |
| `docs/API_DOCUMENTATION.md` | New edit_transaction endpoint |
| `docs/DATABASE_SCHEMA.md` | Updated _transactions schema |
| `docs/CHANGELOG.md` | Full v1.16.0 changelog entry |

---

## Version Numbers Updated

| Location | Old Version | New Version |
|----------|-------------|-------------|
| `README.md` badge | 1.15.3 | 1.16.0 |
| `README.md` footer | 1.15.2 | 1.16.0 |
| `dashboard.php` header | 1.15.3 | 1.16.0 |
| `dashboard.js` comment | 1.15.3 | 1.16.0 |
| `index.html` footer | 1.15.3 | 1.16.0 |
| `service-worker.js` cache | 1.15.3 | 1.16.0 |
| `docs/API_DOCUMENTATION.md` | 1.15.3 | 1.16.0 |
| `docs/DATABASE_SCHEMA.md` | 1.15.3 | 1.16.0 |

---

## Deployment Steps

### 1. Run Database Migration
```bash
# SSH to production server
ssh root@192.168.15.230

# Run migration script
cd /var/www/showbox
php scripts/add_transaction_corrections.php
```

### 2. Deploy Updated Files
```bash
# From local machine
rsync -avz --progress \
    api/edit_transaction.php \
    api/get_transactions.php \
    api/get_monthly_invoice.php \
    api/remove_account.php \
    api/sync_accounts.php \
    root@192.168.15.230:/var/www/showbox/api/

rsync -avz --progress \
    dashboard.php \
    dashboard.js \
    dashboard.css \
    index.html \
    service-worker.js \
    root@192.168.15.230:/var/www/showbox/

rsync -avz --progress \
    scripts/add_transaction_corrections.php \
    root@192.168.15.230:/var/www/showbox/scripts/
```

### 3. Deploy Documentation (Optional)
```bash
rsync -avz --progress \
    README.md \
    docs/ \
    root@192.168.15.230:/var/www/showbox/docs/
```

### 4. Verify Deployment
```bash
# Check file versions
ssh root@192.168.15.230 "grep -h 'v1.16.0' /var/www/showbox/dashboard.php /var/www/showbox/index.html /var/www/showbox/service-worker.js"

# Check database columns
ssh root@192.168.15.230 "mysql -u root showboxt_panel -e 'DESCRIBE _transactions' | grep -E 'correction|status'"
```

---

## API Changes

### New Endpoint

**POST /api/edit_transaction.php**

Apply corrections to existing transactions.

```json
// Request
{
    "transaction_id": 42,
    "correction_amount": -50000,
    "correction_note": "Refund for service issue"
}

// Response
{
    "error": 0,
    "message": "Transaction corrected successfully",
    "transaction": {
        "id": 42,
        "original_amount": -90000000,
        "correction_amount": -50000,
        "net_amount": -90050000,
        "status": "corrected",
        "correction_note": "Refund for service issue",
        "corrected_by": "admin",
        "corrected_at": "2025-11-27 14:30:00"
    }
}
```

### Updated Response Fields

**GET /api/get_transactions.php** now returns:
- `net_amount` - Calculated amount after correction
- `has_correction` - Boolean flag
- `correction_amount` - The correction value
- `correction_note` - Explanation text
- `status` - active/corrected/voided
- `corrected_by_username` - Who made the correction
- `corrected_at` - When correction was made

---

## Testing Checklist

- [ ] Edit Transaction modal opens correctly
- [ ] Permission check: Reseller cannot edit (button disabled/hidden)
- [ ] Permission check: Observer cannot edit
- [ ] Live preview updates when correction amount changes
- [ ] Void checkbox sets net amount to 0 in preview
- [ ] Mandatory correction note validation works
- [ ] Thousand separator formatting in input
- [ ] CORRECTED badge appears in Transactions tab
- [ ] VOIDED badge appears in Transactions tab
- [ ] Badges appear in Accounting tab Transaction Details
- [ ] PDF export shows Status column with colors
- [ ] Excel export includes correction columns
- [ ] Account deletion preserves transactions
- [ ] Sync preserves transactions for orphaned accounts
- [ ] Audit log records correction events

---

## Rollback Procedure

If rollback is needed:

1. **Restore Previous Files:**
   ```bash
   # Restore from backup (if available)
   # Or redeploy v1.15.3 files
   ```

2. **Database Columns:**
   - The new columns can remain in the database
   - They won't affect v1.15.3 functionality
   - To remove (not recommended):
   ```sql
   ALTER TABLE _transactions DROP COLUMN correction_amount;
   ALTER TABLE _transactions DROP COLUMN correction_note;
   ALTER TABLE _transactions DROP COLUMN corrected_by;
   ALTER TABLE _transactions DROP COLUMN corrected_by_username;
   ALTER TABLE _transactions DROP COLUMN corrected_at;
   ALTER TABLE _transactions DROP COLUMN status;
   ```

---

## Support

For issues related to this deployment:
- Check audit logs: `SELECT * FROM _audit_log WHERE target_type = 'transaction' ORDER BY created_at DESC LIMIT 20;`
- Contact: Kambiz Koosheshi
- GitHub: [@kousheshy](https://github.com/kousheshy)

---

**ShowBox Billing System v1.16.0**
**Deployed:** November 27, 2025
