# Stalker Reseller Integration Fix

## Problem Diagnosis

**Test Results (2025-11-22):**
- ✗ Stalker Portal does NOT have a `reseller` field in its database
- ✗ Stalker API ignores the `reseller` parameter when creating/updating accounts
- ✗ Stalker API does not return any `reseller` field in account responses

**Impact:**
The billing system is sending the reseller parameter to Stalker, but Stalker is silently ignoring it. This means:
- Reseller assignments are only stored in the billing database (not in Stalker)
- When syncing from Stalker, reseller assignments are lost (unless we use fallback logic)
- The billing system cannot use Stalker as the "single source of truth" for reseller data

---

## Solution

You have **two options** to fix this:

### Option 1: Add Reseller Field to Stalker Portal (RECOMMENDED)

This is the long-term solution that makes Stalker the authoritative source for reseller data.

#### Step 1: Add Database Column

Connect to your Stalker Portal database and run this SQL:

```sql
-- Add reseller column to Stalker's users table
ALTER TABLE users
ADD COLUMN reseller INT NULL
COMMENT 'Billing system reseller ID';

-- Add index for better query performance
CREATE INDEX idx_reseller ON users(reseller);
```

**Note:** The exact table name might be different. Common variations:
- `users`
- `stb_users`
- `accounts`

To find the correct table, run:
```sql
SHOW TABLES LIKE '%user%';
SHOW TABLES LIKE '%account%';
```

#### Step 2: Modify Stalker API

You need to modify Stalker Portal's API code to:

1. **Accept the `reseller` parameter** in POST and PUT requests
2. **Return the `reseller` field** in GET responses

**Location:** The API code is typically in Stalker's `server` directory.

Example modifications needed:

```php
// In the create/update account endpoint:
// Add this to the list of accepted fields
$allowed_fields = [
    'login',
    'password',
    'full_name',
    // ... existing fields
    'reseller'  // ADD THIS
];

// In the get account endpoint:
// Add reseller to the response
$response = [
    'login' => $user['login'],
    'full_name' => $user['full_name'],
    // ... existing fields
    'reseller' => $user['reseller']  // ADD THIS
];
```

#### Step 3: Migrate Existing Data

After adding the database column and updating the API, run this migration script to push existing reseller assignments from billing to Stalker:

```php
// File: migrate_resellers_to_stalker.php
<?php
include('config.php');
include('api.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

// Get all accounts with reseller assignments
$stmt = $pdo->prepare('SELECT username, mac, reseller FROM _accounts WHERE reseller IS NOT NULL');
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Migrating " . count($accounts) . " reseller assignments to Stalker...\n";

$success = 0;
$failed = 0;

foreach($accounts as $account) {
    // Update Stalker with reseller info
    $data = 'reseller=' . $account['reseller'];
    $case = 'accounts';
    $op = "PUT";

    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME,
                           $WEBSERVICE_PASSWORD, $case, $op, $account['mac'], $data);
    $decoded = json_decode($res);

    if($decoded->status == 'OK') {
        $success++;
        echo "✓ {$account['username']} -> reseller {$account['reseller']}\n";
    } else {
        $failed++;
        echo "✗ {$account['username']} FAILED\n";
    }
}

echo "\nMigration complete: $success succeeded, $failed failed\n";
?>
```

---

### Option 2: Keep Using Fallback Logic (CURRENT STATE)

If you cannot modify Stalker Portal, the billing system will continue using the fallback approach:

**Current Behavior:**
1. When syncing from Stalker, preserve existing reseller mappings from billing database
2. New accounts get assigned to whoever created them
3. Reseller assignments are only stored in billing database

**Advantages:**
- No Stalker modifications needed
- Works right now

**Disadvantages:**
- Billing database is the single point of failure for reseller data
- If billing database is lost, reseller assignments are lost
- Manual intervention needed if data gets out of sync

**This is already implemented** in the current code (sync_accounts.php lines 46-52, 121-139).

---

## Current Code Status

✓ Billing system sends `reseller` parameter to Stalker (ready for when Stalker supports it)
✓ Billing system reads `reseller` field from Stalker responses (with fallback logic)
✓ Sync operation preserves existing reseller mappings
✓ Debug logging added to track integration status

**The billing system code is READY for Stalker integration.**
You just need to add the field to Stalker's database and API.

---

## Testing After Fix

Once you've added the reseller field to Stalker:

1. Run the test script:
   ```bash
   php test_stalker_reseller.php
   ```

2. You should see:
   ```
   ✓ RESELLER FIELD EXISTS in Stalker response!
   ✓✓ SUCCESS! Stalker stored and returned the reseller value!
   ```

3. Test the full workflow:
   - Login as reseller (kamiksh)
   - Create a new account
   - Login as admin
   - Sync accounts from server
   - Login as kamiksh again
   - Verify the account is still visible

---

## Recommendation

**I strongly recommend Option 1** (adding the reseller field to Stalker).

This provides:
- Data redundancy and backup
- Single source of truth in Stalker Portal
- Easier disaster recovery
- Consistent data across systems

The database change is simple (just one column), and the API modifications are minimal.

---

## Questions?

If you need help with:
- Finding the correct table name in Stalker
- Locating the Stalker API code
- Implementing the API changes

Let me know and I can provide more specific guidance.
