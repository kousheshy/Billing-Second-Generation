# Stalker Reseller Integration

## Overview
This document describes the integration between the billing system and Stalker Portal for managing reseller assignments.

## Changes Implemented

### 1. Modified add_account.php
**File:** `add_account.php` line 401

**Change:** When creating a new account in Stalker, the billing system now sends the `reseller` parameter with the reseller_id.

```php
$data = '...&reseller='.$reseller_info['id'];
```

**Purpose:** Ensures Stalker knows which reseller owns each account at creation time.

---

### 2. Modified update_account.php
**File:** `update_account.php` lines 352 and 355

**Change:** When updating an account in Stalker, the billing system includes the `reseller` parameter.

```php
$data = '...&reseller='.$reseller_info['id'];
```

**Purpose:** Maintains reseller ownership when accounts are edited.

---

### 3. Modified sync_accounts.php
**File:** `sync_accounts.php` lines 121-130

**Change:** When syncing accounts from Stalker, the system now:
1. **First** reads the reseller field from Stalker (source of truth)
2. **Fallback** to existing local mapping if Stalker doesn't have it
3. **Final fallback** to current user ID

```php
$stalker_reseller = $stalker_user->reseller ?? null;
if($stalker_reseller && is_numeric($stalker_reseller) && $stalker_reseller > 0) {
    $reseller_id = (int)$stalker_reseller;
} else {
    $reseller_id = $existing_resellers[$login] ?? $user_id;
}
```

**Purpose:** Makes Stalker the single source of truth for reseller assignments.

---

## Data Flow

### Creating New Account
```
User (kamiksh) → Billing System (add_account.php)
                      ↓
                 Stalker API (POST /accounts)
                 Data: login, password, ..., reseller=13
                      ↓
                 Stalker Database
                 (stores reseller=13)
                      ↓
                 Billing Database
                 (stores reseller=13)
```

### Syncing Accounts
```
Admin → Billing System (sync_accounts.php)
             ↓
        Stalker API (GET /accounts)
             ↓
        Response includes: login, mac, reseller=13
             ↓
        Billing Database
        (updates with reseller=13 from Stalker)
```

---

## Benefits

1. **Single Source of Truth**: Stalker Portal is the authoritative source for reseller assignments
2. **Data Persistence**: If billing database is lost, reseller info is preserved in Stalker
3. **Consistency**: Reseller assignments are maintained across sync operations
4. **Reliability**: No data loss when syncing from Stalker Portal

---

## Stalker Portal Requirements

The Stalker Portal must support the `reseller` field in the users/accounts table:

### Expected Field
- **Field Name**: `reseller`
- **Type**: INT
- **Description**: Billing system reseller ID (foreign key to billing._users.id)
- **Nullable**: Yes (NULL for accounts not assigned to any reseller)

### API Support
The Stalker API should:
- Accept `reseller` parameter in POST /accounts (create)
- Accept `reseller` parameter in PUT /accounts/{mac} (update)
- Return `reseller` field in GET /accounts response

---

## Testing

### Test 1: Create Account as Reseller
1. Login as reseller (e.g., kamiksh with ID=13)
2. Create a new account
3. Check Stalker database: `SELECT reseller FROM users WHERE login='...'`
4. Expected: `reseller = 13`

### Test 2: Sync Preserves Reseller
1. Create account as kamiksh (reseller_id=13)
2. Logout, login as admin
3. Click "Sync Accounts from Server"
4. Logout, login as kamiksh
5. Expected: Account still visible to kamiksh

### Test 3: Update Maintains Reseller
1. Edit an existing account
2. Check Stalker database after update
3. Expected: reseller field unchanged

---

## Migration Notes

### Existing Accounts
If you have existing accounts in Stalker without reseller assignments:
1. They will use the fallback mechanism (existing local mappings)
2. Next time they're edited, reseller will be written to Stalker
3. Or run a one-time migration script to push all reseller assignments to Stalker

### Migration Script (Optional)
A script can be created to update all Stalker accounts with their current reseller assignments from the billing database.

---

## Troubleshooting

### Issue: Accounts lose reseller assignment after sync
**Cause**: Stalker doesn't have reseller field or API doesn't return it
**Solution**: Verify Stalker database schema and API response includes reseller

### Issue: New accounts not assigned to reseller
**Cause**: Stalker API doesn't accept reseller parameter
**Solution**: Check Stalker API logs, verify parameter is being sent

---

## Version History

- **v1.0** (2025-11-22): Initial integration with Stalker reseller field
  - Added reseller parameter to add_account.php
  - Added reseller parameter to update_account.php
  - Modified sync_accounts.php to read reseller from Stalker
