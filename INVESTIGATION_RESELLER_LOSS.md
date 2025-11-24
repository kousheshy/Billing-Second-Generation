# Investigation: Reseller Assignment Loss

**Date:** November 24, 2025
**Issue:** All 4903 accounts lost their reseller assignments (became NULL)
**Status:** 🔍 INVESTIGATING

---

## Timeline of Events

1. **Commit 8efd61e** (Nov 24): Added dual-key lookup fix to sync_accounts.php (LOCAL ONLY)
2. **Later commits**: Only modified dashboard.js (frontend JavaScript)
3. **Nov 23 19:10**: Database backup created (already had NULL assignments)
4. **Today**: User noticed all assignments are lost

---

## Critical Findings

### 1. The Fix Was NOT Deployed

**Local File** (`sync_accounts.php`):
- ✅ Has dual-key lookup (lines 46-56, 127-157)
- ✅ Has MAC address preservation
- ✅ Has error logging

**What's Actually Running**:
- ❓ Unknown - could be old version without fix
- ❓ Could be production server (192.168.15.230)
- ❓ Could be local MAMP/development server

### 2. No Error Logs Available

```bash
$ php -i | grep "error_log"
error_log => no value => no value
```

The `error_log()` statements in sync_accounts.php lines 138-156 are NOT being saved anywhere, so we have NO TRACE of what happened during the sync.

### 3. Backup Was Taken AFTER Loss

The backup at `/tmp/showboxt_panel_local_export.sql` (Nov 23 19:10) shows ALL accounts already had `NULL` reseller assignments. This means:
- Assignments were lost BEFORE Nov 23 19:10
- The sync that caused the loss happened sometime before that backup

---

## Root Cause Analysis

### Theory #1: Old sync_accounts.php Still Running ⭐ MOST LIKELY

**Evidence:**
- Local file HAS the fix
- Assignments were lost anyway
- This indicates the web server is running an OLD version

**Why This Happens:**
1. Developer modifies file locally
2. Commits to git
3. BUT doesn't deploy to web server
4. Web server still runs old code
5. User clicks "Sync Accounts" button
6. Old buggy code executes
7. All assignments lost

**Verification Needed:**
- Check what sync_accounts.php file the web server is actually using
- Check if it has the dual-key lookup code (lines 46-56)

### Theory #2: Stalker Portal Has No Reseller Field

**Evidence:**
- The fix assumes Stalker Portal has a `reseller` field
- Line 135: `$stalker_reseller = $stalker_user->reseller ?? null;`

**If Stalker Portal Doesn't Have This Field:**
1. `$stalker_reseller` is always NULL
2. Code falls back to local mapping
3. Looks for existing resellers in `_accounts` table (line 50)
4. If table was already empty (no reseller assignments), then `$existing_resellers` array is EMPTY
5. All new accounts get `reseller_id = null`

**Test This:**
```php
// In sync_accounts.php line 75
error_log("RAW STALKER RESPONSE: " . substr($res, 0, 1000));
```

This would show if Stalker Portal even returns a `reseller` field.

### Theory #3: Database Was Cleared Before Sync

**Evidence:**
- Line 61: `DELETE FROM _accounts` (for admins)
- If someone accidentally cleared assignments BEFORE running sync
- Then line 50 would find NO assignments to preserve
- Result: All accounts get NULL

**Timeline If This Happened:**
1. Someone manually sets all `reseller = NULL` in database
2. User clicks "Sync Accounts"
3. Line 50 finds zero accounts with reseller assignments
4. `$existing_resellers` array is empty
5. All synced accounts get `reseller_id = null`

---

## What We Know FOR SURE

1. ✅ Local sync_accounts.php HAS the dual-key lookup fix
2. ✅ All 4903 current accounts have `reseller = NULL`
3. ✅ Backup from Nov 23 19:10 also had all NULLs
4. ✅ Loss happened BEFORE Nov 23 19:10
5. ❌ NO error logs available to trace execution
6. ❌ DON'T KNOW which sync_accounts.php version actually ran
7. ❌ DON'T KNOW if Stalker Portal has reseller field
8. ❌ DON'T KNOW when sync button was clicked

---

## Tests To Run

### Test 1: Check Web Server File

**If using local MAMP/development:**
```bash
# Find where the web root is
cat config.php | grep "localhost"

# Check if it's same file
ls -la sync_accounts.php
```

**If using production server:**
```bash
ssh root@192.168.15.230
cat /var/www/showbox/sync_accounts.php | head -60
# Check if it has lines 46-56 (dual-key lookup)
```

### Test 2: Check Stalker Portal Response

**Add temporary logging:**
```php
// In sync_accounts.php after line 72
file_put_contents('/tmp/stalker_response.json', $res);
error_log("Stalker response saved to /tmp/stalker_response.json");
```

Then click Sync and check if file has `reseller` field.

### Test 3: Enable Error Logging

**In sync_accounts.php, add at top (after line 7):**
```php
ini_set('error_log', '/tmp/sync_debug.log');
ini_set('log_errors', 1);
```

This will force all error_log() statements to go to `/tmp/sync_debug.log`.

### Test 4: Check for Previous Assignments

```sql
-- Check if there's ANY history of reseller assignments
SELECT * FROM _accounts WHERE reseller IS NOT NULL LIMIT 5;

-- Check resellers table
SELECT id, username FROM _users WHERE super_user = 0;
```

---

## Preventive Measures

### 1. Deploy Protection ⭐ CRITICAL

**Problem:** Local code fixes don't automatically go to production.

**Solution:**
```bash
# Before allowing sync, check file version
# Add to sync_accounts.php line 8:
define('SYNC_VERSION', '1.11.3');

// In JavaScript, check version before sync:
fetch('sync_accounts.php?check_version=1')
  .then(r => r.json())
  .then(data => {
    if(data.version !== '1.11.3') {
      alert('WARNING: Sync code is outdated! Contact developer.');
      return;
    }
    // Proceed with sync
  });
```

### 2. Pre-Sync Backup ⭐ CRITICAL

**Add to sync_accounts.php before line 61:**
```php
// BACKUP reseller assignments before deleting
$backup_file = "/tmp/reseller_backup_" . date('Y-m-d_H-i-s') . ".json";
$stmt = $pdo->prepare('SELECT mac, reseller FROM _accounts WHERE reseller IS NOT NULL');
$stmt->execute();
$backup_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents($backup_file, json_encode($backup_data));
error_log("Reseller assignments backed up to: " . $backup_file);
```

This creates a JSON backup of ALL assignments before deleting anything.

### 3. Stalker Portal Integration Check

**Add validation:**
```php
// After line 78 (after decoding Stalker response)
if(!isset($decoded->results) || empty($decoded->results)) {
    $response['error'] = 1;
    $response['err_msg'] = 'Stalker Portal returned no accounts';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Check if first account has expected fields
$first_account = is_array($decoded->results) ? $decoded->results[0] : $decoded->results;
error_log("First account fields: " . implode(', ', array_keys((array)$first_account)));
```

### 4. Confirmation Dialog

**In dashboard.js, before sync:**
```javascript
function syncAccounts() {
    if(!confirm('⚠️ WARNING: This will delete and re-import all accounts.\n\nReseller assignments will be preserved using MAC addresses.\n\nAre you sure?')) {
        return;
    }

    if(!confirm('⚠️ SECOND CONFIRMATION: This is a destructive operation.\n\nA backup will be created, but please ensure you have a recent database backup.\n\nProceed?')) {
        return;
    }

    // Proceed with sync...
}
```

---

## Recommended Next Steps

1. **FIRST**: Determine which sync_accounts.php file is actually being executed
   - Local MAMP server? Check web root
   - Production server? SSH and check /var/www/showbox/

2. **SECOND**: Check if that file has the dual-key lookup fix (lines 46-56)

3. **THIRD**: Enable error logging and test sync with ONE test account

4. **FOURTH**: Implement preventive measures before allowing any more syncs

---

## Questions for User

1. **Are you running this locally (MAMP/XAMPP) or on production server (192.168.15.230)?**

2. **Did you or anyone else click the "Sync Accounts" button today?**

3. **Do you have ANY backup from BEFORE Nov 23 19:10 that might have the assignments?**

4. **Does your Stalker Portal have a `reseller` field in the accounts data?**
   - Check Stalker Portal admin interface
   - Or API documentation

5. **When was the last time you successfully synced accounts WITHOUT losing assignments?**

---

## Status: AWAITING USER INPUT

Before we can fix this permanently, we need to answer the questions above to understand:
- Which version of sync_accounts.php actually ran
- Whether Stalker Portal supports reseller field
- When/how the assignments were lost

**DO NOT run any more syncs until we understand what happened!**
