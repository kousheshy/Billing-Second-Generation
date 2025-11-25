# Reseller Admin Permissions - Complete Documentation

## Version: 1.11.0
## Date: 2025-11-25
## Status: ✅ Fully Implemented and Tested

---

## Table of Contents
1. [Overview](#overview)
2. [Permission System Architecture](#permission-system-architecture)
3. [Implemented Features](#implemented-features)
4. [Security Controls](#security-controls)
5. [Backend Changes](#backend-changes)
6. [Frontend Changes](#frontend-changes)
7. [Testing Guide](#testing-guide)
8. [Troubleshooting](#troubleshooting)

---

## Overview

### What is a Reseller Admin?

A **Reseller Admin** is a special type of reseller account that has elevated permissions to manage other resellers while remaining a non-super-user account. This role sits between regular resellers and super admins in the permission hierarchy.

### Permission Hierarchy

```
Super Admin (super_user = 1)
    ↓
Reseller Admin (permissions index 2 = '1')
    ↓
Regular Reseller (permissions index 2 = '0')
    ↓
Observer (is_observer = 1)
```

### Core Capabilities

**Reseller Admins Can:**
- ✅ Manage all resellers (add, edit, delete, adjust credit)
- ✅ Assign plans to resellers
- ✅ Toggle between "All Accounts" and "My Accounts" view
- ✅ See card-based plan selection in "My Accounts" mode
- ✅ Edit username/password fields (unlike regular resellers)
- ✅ Access all reseller management features

**Reseller Admins Cannot:**
- ❌ Remove their own admin permission
- ❌ Delete their own account
- ❌ Become super admins (super_user flag locked at 0)

---

## Permission System Architecture

### Permission String Format

```
can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
```

**Example:**
- Super Admin: `1|1|0|1|1|1|1` (super_user = 1)
- Reseller Admin: `1|1|1|1|1|1|0` (super_user = 0)
- Regular Reseller: `1|1|0|0|1|0|0` (super_user = 0)

### Field Breakdown

| Index | Field Name | Description |
|-------|------------|-------------|
| 0 | can_edit | Can edit accounts |
| 1 | can_add | Can add accounts |
| 2 | **is_reseller_admin** | **Admin-level permissions** |
| 3 | can_delete | Can delete accounts |
| 4 | can_control_stb | Can control STB devices |
| 5 | can_toggle_status | Can toggle account status |
| 6 | can_access_messaging | Can access messaging features |

### Backend Permission Check Pattern

```php
// Standard check used across all backend files
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

if($user_info['super_user'] != 1 && !$is_reseller_admin) {
    $response['error'] = 1;
    $response['message'] = 'Permission denied. Admin or Reseller Admin only.';
    exit();
}
```

### Frontend Permission Check Pattern

```javascript
// Check in JavaScript (dashboard.js)
const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
```

---

## Implemented Features

### 1. Reseller Management Permissions

#### Backend Files Modified:
1. **assign_plans.php** (lines 41-54)
2. **adjust_credit.php** (lines 41-54)
3. **get_resellers.php** (lines 34-50)
4. **remove_reseller.php** (lines 48-60, 76-83)
5. **update_reseller.php** (lines 94-108)
6. **get_themes.php** (lines 38-49)

#### What Changed:
- Added reseller admin permission checks to all reseller management endpoints
- Previously only `super_user == 1` could access these features
- Now both super admins AND reseller admins can access

#### Example from assign_plans.php:
```php
// OLD CODE (lines 41-54):
if($user_info['super_user'] != 1) {
    exit();
}

// NEW CODE:
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

if($user_info['super_user'] != 1 && !$is_reseller_admin) {
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied. Admin or Reseller Admin only.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

---

### 2. Plan Filtering by Category

#### Problem:
When reseller admins toggled to "My Accounts" view, they could see all plan types instead of only their relevant plans:
- Edit/Renew modal showed `new_device` plans (should only show `renew_device`)
- Add Account modal showed `renew_device` plans (should only show `new_device`)

#### Solution:

##### Backend: get_plans.php (lines 92-101)
```php
// For reseller admins viewing "My Accounts", filter by category
if($is_reseller_admin && !$viewAllAccounts) {
    // Only show renewal plans (renew_device) and new device plans
    $category = $plan['category'] ?? '';
    if($category === 'renew_device' || $category === 'new_device') {
        $plans[] = $plan;
        error_log('[get_plans.php] Reseller admin - included plan (category: ' . $category . ')');
    } else {
        error_log('[get_plans.php] Reseller admin - skipped plan (category: ' . $category . ')');
    }
} else {
    // Regular resellers see all their assigned plans
    $plans[] = $plan;
}
```

**Key Fix:** Changed category from `'renewal'` to `'renew_device'` to match database values.

##### Frontend: dashboard.js

**loadPlansForEdit() - Lines 2750-2751, 2760-2761:**
```javascript
async function loadPlansForEdit() {
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const response = await fetch(`get_plans.php?viewAllAccounts=${viewAllAccounts}`);
    const result = await response.json();

    if(result.error == 0 && result.plans) {
        // Filter for renewal plans only
        const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');
        // ... populate dropdown
    }
}
```

**loadPlans() - Line 1899:**
```javascript
// For Add Account dropdown in "My Accounts" mode, reseller admins only see new_device plans
if (!isResellerAdmin || viewAllAccounts || plan.category === 'new_device') {
    const option = document.createElement('option');
    option.value = `${plan.external_id}-${plan.currency_id}`;
    option.textContent = `${plan.name} - ${formattedPrice} (${plan.days} days)`;
    planSelect.appendChild(option);
}
```

**loadRenewalPlans() - Lines 2776-2777:**
```javascript
async function loadRenewalPlans() {
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const response = await fetch(`get_plans.php?viewAllAccounts=${viewAllAccounts}`);
    // ... loads renew_device plans for card display
}
```

**loadNewDevicePlans() - Lines 2831-2832:**
```javascript
async function loadNewDevicePlans() {
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const response = await fetch(`get_plans.php?viewAllAccounts=${viewAllAccounts}`);
    // ... loads new_device plans for card display
}
```

---

### 3. Card-Based Plan Selection

#### Feature Request:
When reseller admins toggle to "My Accounts" mode, they should see beautiful card-based plan selection (like regular resellers) instead of dropdowns.

#### Implementation:

##### Add Account Modal - dashboard.js (lines 1035-1077)
```javascript
function openModal(mode) {
    // ... modal setup code ...

    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

    if (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode) {
        // Show card-based selection
        if (isResellerWithoutAdmin) {
            // Regular resellers can't edit username/password
            document.getElementById('account-username').readOnly = true;
            document.getElementById('account-password').readOnly = true;
        } else {
            // Reseller admins CAN edit username/password
            document.getElementById('account-username').readOnly = false;
            document.getElementById('account-password').readOnly = false;
        }

        document.getElementById('add-admin-plan-group').style.display = 'none';
        document.getElementById('add-reseller-plan-section').style.display = 'block';
        loadNewDevicePlans(); // Load card-based plans
    } else {
        // Show dropdown selection for super admins or reseller admins in "All Accounts" mode
        document.getElementById('add-admin-plan-group').style.display = 'block';
        document.getElementById('add-reseller-plan-section').style.display = 'none';
    }
}
```

##### Edit/Renew Modal - dashboard.js (lines 2717-2760)
```javascript
async function editAccountCore(account) {
    // ... setup code ...

    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

    if (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode) {
        // Show card-based selection
        if (isResellerWithoutAdmin) {
            document.getElementById('edit-username').readOnly = true;
            document.getElementById('edit-password').readOnly = true;
        } else {
            // Reseller admins can edit
            document.getElementById('edit-username').readOnly = false;
            document.getElementById('edit-password').readOnly = false;
        }

        document.getElementById('edit-plan-group').style.display = 'none';
        document.getElementById('reseller-renewal-section').style.display = 'block';
        await loadRenewalPlans(); // Load card-based plans
    } else {
        // Show dropdown selection
        document.getElementById('edit-plan-group').style.display = 'block';
        document.getElementById('reseller-renewal-section').style.display = 'none';
        await loadPlansForEdit(); // Load dropdown plans
    }
}
```

#### Visual Differences:

**Dropdown Mode (All Accounts):**
```
┌─────────────────────────────┐
│ Plan: [▼ Select a plan...] │
│                             │
└─────────────────────────────┘
```

**Card Mode (My Accounts):**
```
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Plan Name    │ │ Plan Name    │ │ Plan Name    │
│ 100,000 IRR  │ │ 200,000 IRR  │ │ 300,000 IRR  │
│ 30 days      │ │ 60 days      │ │ 90 days      │
│ [Select]     │ │ [Select]     │ │ [Select]     │
└──────────────┘ └──────────────┘ └──────────────┘
```

---

### 4. Delete Button Visibility

#### Feature Request:
Reseller admins must have delete button in reseller section to delete resellers, but cannot delete themselves.

#### Implementation:

##### Frontend: dashboard.js (lines 1782-1804)
```javascript
async function loadResellers() {
    // ... fetch resellers ...

    result.resellers.forEach(reseller => {
        const tr = document.createElement('tr');
        const resellerBalance = reseller.balance || 0;
        const resellerCurrency = reseller.currency_name || 'IRR';

        // Check if user is observer
        const isObserver = currentUser && currentUser.is_observer == 1;
        const isSuperAdmin = currentUser && currentUser.super_user == 1;
        const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');

        // ... other buttons ...

        // Show delete button for super admin and reseller admin
        // Reseller admin cannot delete themselves (checked in backend)
        const canDelete = isSuperAdmin || isResellerAdmin;
        const deleteButton = canDelete
            ? `<button class="btn-sm btn-delete" onclick="deleteReseller(${reseller.id})">Delete</button>`
            : '';

        tr.innerHTML = `
            <td>${reseller.name || ''}</td>
            <td>${reseller.username || ''}</td>
            <td>${reseller.email || ''}</td>
            <td>${getCurrencySymbol(reseller.currency_name)}${formatBalance(reseller.balance || 0, reseller.currency_name)}</td>
            <td>${reseller.account_count || 0}</td>
            <td>
                <div class="action-buttons">
                    ${editButton}
                    ${adjustCreditButton}
                    ${assignPlansButton}
                    ${deleteButton}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}
```

##### Backend: remove_reseller.php (lines 76-83)
```php
// CRITICAL SECURITY CHECK: Reseller admins cannot delete themselves
if($is_reseller_admin && $user_info['id'] == $id) {
    $response['error'] = 1;
    $response['err_msg'] = 'You cannot delete your own account. Contact a super admin.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

---

## Security Controls

### 1. Self-Permission Removal Protection

**File:** update_reseller.php (lines 94-108)

**Purpose:** Prevent reseller admins from removing their own admin permissions.

```php
// CRITICAL SECURITY CHECK: Reseller admins cannot modify their own admin permission
// They can modify other resellers' permissions, but not their own
if($is_reseller_admin && $user_info['id'] == $id) {
    // Parse the incoming permissions to check if they're trying to remove admin flag
    $new_permissions = explode('|', $permissions);
    $new_is_reseller_admin = isset($new_permissions[2]) && $new_permissions[2] === '1';

    // If they're trying to remove their own admin permission, deny it
    if(!$new_is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'You cannot remove your own admin permissions. Contact a super admin.']);
        exit();
    }

    error_log("Reseller admin {$user_info['username']} attempted to edit their own account - admin permission preserved");
}
```

**Test Case:**
1. Login as reseller admin
2. Go to Resellers tab
3. Click Edit on your own account
4. Try to uncheck "Reseller Admin" checkbox
5. Click Save
6. **Expected Result:** Error message "You cannot remove your own admin permissions. Contact a super admin."

---

### 2. Self-Deletion Protection

**File:** remove_reseller.php (lines 76-83)

**Purpose:** Prevent reseller admins from deleting their own accounts.

```php
// CRITICAL SECURITY CHECK: Reseller admins cannot delete themselves
if($is_reseller_admin && $user_info['id'] == $id) {
    $response['error'] = 1;
    $response['err_msg'] = 'You cannot delete your own account. Contact a super admin.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

**Test Case:**
1. Login as reseller admin
2. Go to Resellers tab
3. Find your own account in the list
4. Click Delete button
5. Confirm deletion
6. **Expected Result:** Error message "You cannot delete your own account. Contact a super admin."

---

### 3. Super User Lock

**All Files:** Resellers and reseller admins ALWAYS have `super_user = 0`

**Purpose:** Maintain separation between super admins and reseller admins. No privilege escalation possible.

```php
// In update_reseller.php and add_reseller.php
// All resellers remain with super_user = 0
// Admin-level permissions are stored in permissions string (index 2)
$stmt = $pdo->prepare('UPDATE _users SET ... super_user = 0 WHERE id = ?');
```

---

### 4. Account Deletion Validation

**File:** remove_reseller.php (lines 85-101)

**Purpose:** Prevent deletion of resellers who have active accounts.

```php
// Check if reseller has any accounts
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM _accounts WHERE reseller = ?');
$stmt->execute([$id]);
$account_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if($account_count > 0)
{
    $response['error']=1;
    $response['err_msg']='Cannot delete reseller with active accounts. Please delete all accounts first.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

**Test Case:**
1. Login as reseller admin
2. Create a test reseller
3. Add an account to that reseller
4. Try to delete the reseller
5. **Expected Result:** Error "Cannot delete reseller with active accounts. Please delete all accounts first."

---

## Backend Changes

### Summary Table

| File | Lines Modified | Purpose | Permission Check Added |
|------|---------------|---------|----------------------|
| assign_plans.php | 41-54 | Allow reseller admins to assign plans | ✅ Yes |
| adjust_credit.php | 41-54 | Allow reseller admins to adjust credit | ✅ Yes |
| get_resellers.php | 34-50 | Allow reseller admins to view resellers | ✅ Yes |
| remove_reseller.php | 48-60, 76-83 | Allow reseller admins to delete (with self-protection) | ✅ Yes |
| update_reseller.php | 94-108 | Self-permission protection (already existed) | ✅ Yes |
| get_themes.php | 38-49 | Allow reseller admins to access themes | ✅ Yes |
| get_plans.php | 92-101 | Category filtering for reseller admins | ✅ Yes |

### Complete File List with Changes

#### 1. assign_plans.php
```php
Lines 41-54: Added reseller admin permission check
- Changed from super_user-only to super_user OR is_reseller_admin
- Added proper error messages
- Permission string parsing: explode('|', ...) and check index 2
```

#### 2. adjust_credit.php
```php
Lines 41-54: Added reseller admin permission check
- Same pattern as assign_plans.php
- Allows reseller admins to add/deduct/set credit for other resellers
```

#### 3. get_resellers.php
```php
Lines 34-50: Added reseller admin permission check
- Previously had no permission check (security issue)
- Now requires super_user OR is_reseller_admin
- Returns list of all resellers with account counts
```

#### 4. remove_reseller.php
```php
Lines 48-60: Added reseller admin permission check
Lines 76-83: Added self-deletion protection
- Cannot delete themselves
- Cannot delete resellers with active accounts
```

#### 5. update_reseller.php
```php
Lines 94-108: Self-permission protection (already existed)
- Prevents reseller admins from removing own admin flag
- Can modify other resellers
- Logs attempts to edit own account
```

#### 6. get_themes.php
```php
Lines 38-49: Added reseller admin permission check
- Allows reseller admins to access theme list
- Needed for editing resellers
```

#### 7. get_plans.php
```php
Lines 92-101: Category filtering for reseller admins
- Fixed category name from 'renewal' to 'renew_device'
- Filters plans based on view mode
- Only shows relevant plan categories in "My Accounts" mode
```

---

## Frontend Changes

### Summary Table

| File | Function | Lines | Purpose |
|------|----------|-------|---------|
| dashboard.js | loadResellers() | 1782-1804 | Delete button visibility, isResellerAdmin definition |
| dashboard.js | openModal() | 1035-1077 | Card-based plan selection for Add Account |
| dashboard.js | loadPlans() | 1899 | Category filter for Add Account dropdown |
| dashboard.js | editAccountCore() | 2717-2760 | Card-based plan selection for Edit/Renew |
| dashboard.js | loadPlansForEdit() | 2750-2751, 2760-2761 | Pass viewAllAccounts, filter by category |
| dashboard.js | loadRenewalPlans() | 2776-2777 | Pass viewAllAccounts parameter |
| dashboard.js | loadNewDevicePlans() | 2831-2832 | Pass viewAllAccounts parameter |

### Critical Bug Fix - Line 1785

**Problem:** Site not loading, error "error loading resellers"

**Root Cause:** `isResellerAdmin` variable used at line 1801 but not defined in function scope.

**Solution:** Added definition at line 1785
```javascript
const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
```

**Impact:** Fixed critical JavaScript error that prevented entire site from loading.

---

## Testing Guide

### Test Case 1: Reseller Admin Can Manage Resellers

**Setup:**
1. Create a test reseller admin account
2. Set permissions to `1|1|1|1|1|1|0` (is_reseller_admin = 1)
3. Login as that reseller admin

**Test Steps:**
1. Go to Resellers tab
2. Click "Add Reseller" button
3. Fill in reseller details
4. Click Save
5. **Expected:** Reseller created successfully

**Verify:**
- ✅ Can add new resellers
- ✅ Can edit existing resellers
- ✅ Can delete resellers (except self)
- ✅ Can adjust credit
- ✅ Can assign plans

---

### Test Case 2: Plan Filtering in My Accounts Mode

**Setup:**
1. Login as reseller admin
2. Assign multiple plans with different categories:
   - Plan A: category = `new_device`
   - Plan B: category = `renew_device`
   - Plan C: category = `application`

**Test Steps:**
1. Toggle to "My Accounts" mode (toggle switch at top)
2. Click "Add Account"
3. Check plan options shown
4. **Expected:** Only Plan A (`new_device`) shown in cards
5. Close modal
6. Click "Edit" on an existing account
7. Check plan options shown
8. **Expected:** Only Plan B (`renew_device`) shown in cards

**Verify:**
- ✅ Add Account shows only `new_device` plans
- ✅ Edit/Renew shows only `renew_device` plans
- ✅ Application plans hidden in both modals

---

### Test Case 3: Card vs Dropdown Display

**Setup:**
1. Login as reseller admin
2. Ensure you have at least one assigned plan

**Test Steps - My Accounts Mode:**
1. Toggle to "My Accounts" mode
2. Click "Add Account"
3. **Expected:** Beautiful card-based plan selection visible
4. **Expected:** Username and password fields are editable
5. Close modal

**Test Steps - All Accounts Mode:**
6. Toggle to "All Accounts" mode
7. Click "Add Account"
8. **Expected:** Dropdown plan selection visible (no cards)
9. **Expected:** Username and password fields are editable
10. Close modal

**Verify:**
- ✅ Cards shown in "My Accounts" mode
- ✅ Dropdown shown in "All Accounts" mode
- ✅ Reseller admin can edit username/password in both modes
- ✅ Regular resellers see cards but cannot edit username/password

---

### Test Case 4: Self-Permission Protection

**Setup:**
1. Login as reseller admin
2. Note your own user ID

**Test Steps:**
1. Go to Resellers tab
2. Find your own account in the list
3. Click "Edit" on your account
4. Uncheck "Reseller Admin" checkbox
5. Click Save
6. **Expected:** Error "You cannot remove your own admin permissions. Contact a super admin."

**Verify:**
- ✅ Cannot remove own admin permission
- ✅ Can edit other fields (name, email, balance)
- ✅ Can edit other resellers' permissions

---

### Test Case 5: Self-Deletion Protection

**Setup:**
1. Login as reseller admin
2. Note your own user ID

**Test Steps:**
1. Go to Resellers tab
2. Find your own account in the list
3. Click "Delete" button on your account
4. Confirm deletion
5. **Expected:** Error "You cannot delete your own account. Contact a super admin."

**Verify:**
- ✅ Cannot delete own account
- ✅ Delete button is visible (not hidden)
- ✅ Backend prevents deletion
- ✅ Can delete other resellers

---

### Test Case 6: Delete Button Visibility

**Setup:**
1. Create three test accounts:
   - Account A: Super admin
   - Account B: Reseller admin
   - Account C: Regular reseller

**Test Steps:**
1. Login as Account A (super admin)
2. Go to Resellers tab
3. **Expected:** Delete buttons visible for all resellers
4. Logout

5. Login as Account B (reseller admin)
6. Go to Resellers tab
7. **Expected:** Delete buttons visible for all resellers
8. Logout

9. Login as Account C (regular reseller)
10. Go to Resellers tab
11. **Expected:** No delete buttons visible

**Verify:**
- ✅ Super admin sees delete buttons
- ✅ Reseller admin sees delete buttons
- ✅ Regular reseller doesn't see delete buttons

---

### Test Case 7: View Mode Toggle Persistence

**Setup:**
1. Login as reseller admin

**Test Steps:**
1. Toggle to "My Accounts" mode
2. Refresh page
3. **Expected:** Still in "My Accounts" mode
4. Toggle to "All Accounts" mode
5. Refresh page
6. **Expected:** Still in "All Accounts" mode

**Verify:**
- ✅ View mode saved to localStorage
- ✅ Persists across page refreshes
- ✅ Affects plan filtering correctly

---

## Troubleshooting

### Issue 1: "Error loading resellers" on page load

**Symptoms:**
- Alert message: "Error loading resellers"
- Console error: "isResellerAdmin is not defined"
- Site doesn't load properly

**Cause:**
Variable `isResellerAdmin` used but not defined in `loadResellers()` function.

**Solution:**
Check dashboard.js line 1785 has:
```javascript
const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
```

**Status:** ✅ FIXED in this version

---

### Issue 2: Wrong plans showing in modals

**Symptoms:**
- Add Account shows renewal plans
- Edit/Renew shows new device plans
- Plans not filtered by category

**Causes:**
1. Backend checking wrong category name (`'renewal'` instead of `'renew_device'`)
2. Frontend not passing `viewAllAccounts` parameter
3. Frontend not filtering by category

**Solutions:**
1. Check get_plans.php line 96: must be `'renew_device'`
2. Check dashboard.js functions pass `viewAllAccounts=${viewAllAccounts}`
3. Check dashboard.js filters plans by `plan.category`

**Status:** ✅ FIXED in this version

---

### Issue 3: Permission denied errors

**Symptoms:**
- Reseller admin gets "Permission denied" when accessing reseller features
- Cannot assign plans, adjust credit, or edit resellers

**Cause:**
Backend files don't have reseller admin permission check.

**Solution:**
Check these files have the permission check pattern:
- assign_plans.php (lines 41-54)
- adjust_credit.php (lines 41-54)
- get_resellers.php (lines 34-50)
- remove_reseller.php (lines 48-60)
- get_themes.php (lines 38-49)

Each should have:
```php
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

if($user_info['super_user'] != 1 && !$is_reseller_admin) {
    // Permission denied
}
```

**Status:** ✅ FIXED in this version

---

### Issue 4: Reseller admin can remove own permissions

**Symptoms:**
- Reseller admin edits own account
- Unchecks "Reseller Admin" checkbox
- Saves successfully (should fail)

**Cause:**
Missing self-permission protection in update_reseller.php.

**Solution:**
Check update_reseller.php lines 94-108 have:
```php
// CRITICAL SECURITY CHECK: Reseller admins cannot modify their own admin permission
if($is_reseller_admin && $user_info['id'] == $id) {
    $new_permissions = explode('|', $permissions);
    $new_is_reseller_admin = isset($new_permissions[2]) && $new_permissions[2] === '1';

    if(!$new_is_reseller_admin) {
        echo json_encode(['error' => 1, 'err_msg' => 'You cannot remove your own admin permissions. Contact a super admin.']);
        exit();
    }
}
```

**Status:** ✅ Already exists (not modified in this version)

---

### Issue 5: Cards not showing for reseller admin

**Symptoms:**
- Reseller admin in "My Accounts" mode
- Opens Add Account or Edit modal
- Sees dropdown instead of cards

**Cause:**
View mode check not properly implemented in modal display logic.

**Solution:**
Check dashboard.js:

**For Add Account (lines 1035-1077):**
```javascript
const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

if (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode) {
    // Show cards
}
```

**For Edit/Renew (lines 2717-2760):**
```javascript
const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

if (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode) {
    // Show cards
}
```

**Status:** ✅ FIXED in this version

---

### Issue 6: Delete button not visible

**Symptoms:**
- Reseller admin goes to Resellers tab
- No delete buttons visible for any reseller

**Cause:**
Delete button visibility check doesn't include `isResellerAdmin`.

**Solution:**
Check dashboard.js lines 1799-1804:
```javascript
const canDelete = isSuperAdmin || isResellerAdmin;
const deleteButton = canDelete
    ? `<button class="btn-sm btn-delete" onclick="deleteReseller(${reseller.id})">Delete</button>`
    : '';
```

**Status:** ✅ FIXED in this version

---

## Database Schema Reference

### _users Table

```sql
CREATE TABLE _users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    username VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    email VARCHAR(255),
    theme VARCHAR(255),
    currency_id INT,
    balance DECIMAL(10,2) DEFAULT 0,
    super_user TINYINT DEFAULT 0,  -- 0 = reseller, 1 = super admin
    permissions VARCHAR(50),        -- Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
    is_observer TINYINT DEFAULT 0,
    plans TEXT,                     -- Assigned plans (format: planID-currency,planID-currency)
    use_ip_ranges TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Permission Index Reference

```
Index 0: can_edit           - Can edit accounts
Index 1: can_add            - Can add accounts
Index 2: is_reseller_admin  - Admin-level permissions (KEY FIELD)
Index 3: can_delete         - Can delete accounts
Index 4: can_control_stb    - Can control STB devices
Index 5: can_toggle_status  - Can toggle account status
Index 6: can_access_messaging - Can access messaging features
```

### Example User Records

**Super Admin:**
```sql
INSERT INTO _users (name, username, password, super_user, permissions)
VALUES ('Admin User', 'admin', '$2y$10$...', 1, '1|1|0|1|1|1|1');
```

**Reseller Admin:**
```sql
INSERT INTO _users (name, username, password, super_user, permissions)
VALUES ('Reseller Admin User', 'reselleradmin', '$2y$10$...', 0, '1|1|1|1|1|1|0');
```

**Regular Reseller:**
```sql
INSERT INTO _users (name, username, password, super_user, permissions)
VALUES ('Regular Reseller', 'reseller', '$2y$10$...', 0, '1|1|0|0|1|0|0');
```

---

## API Endpoints Reference

### Reseller Management Endpoints

| Endpoint | Method | Permission Required | Description |
|----------|--------|-------------------|-------------|
| get_resellers.php | GET | Super Admin OR Reseller Admin | Get list of all resellers |
| add_reseller.php | POST | Super Admin OR Reseller Admin | Create new reseller |
| update_reseller.php | POST | Super Admin OR Reseller Admin | Update reseller details |
| remove_reseller.php | GET | Super Admin OR Reseller Admin | Delete reseller (with protections) |
| adjust_credit.php | POST | Super Admin OR Reseller Admin | Adjust reseller credit |
| assign_plans.php | POST | Super Admin OR Reseller Admin | Assign plans to reseller |
| get_themes.php | GET | Super Admin OR Reseller Admin | Get available themes |

### Plan Endpoints

| Endpoint | Method | Parameters | Description |
|----------|--------|-----------|-------------|
| get_plans.php | GET | viewAllAccounts (bool) | Get plans filtered by user type and view mode |

**Parameters:**
- `viewAllAccounts=true`: Show all plans (for "All Accounts" mode)
- `viewAllAccounts=false`: Show only assigned plans, filtered by category (for "My Accounts" mode)

---

## Version History

### v1.11.0 (2025-11-25) - Reseller Admin Permissions

**New Features:**
- ✅ Reseller admins can manage all resellers
- ✅ Reseller admins can assign plans and adjust credit
- ✅ Plan filtering by category in "My Accounts" mode
- ✅ Card-based plan selection for reseller admins
- ✅ Delete button visibility for reseller admins

**Security Enhancements:**
- ✅ Self-permission removal protection
- ✅ Self-deletion protection
- ✅ Account deletion validation
- ✅ Permission checks on all reseller endpoints

**Bug Fixes:**
- ✅ Fixed "error loading resellers" - undefined variable
- ✅ Fixed category name from 'renewal' to 'renew_device'
- ✅ Fixed missing viewAllAccounts parameter
- ✅ Fixed plan filtering in modals

**Files Modified:**
- Backend: assign_plans.php, adjust_credit.php, get_resellers.php, remove_reseller.php, update_reseller.php, get_themes.php, get_plans.php
- Frontend: dashboard.js (7 functions modified)

---

## Support and Maintenance

### Log Files

**Backend Logs:**
- Check PHP error log for permission check failures
- get_plans.php includes debug logging (can be removed in production)

**Example log entries:**
```
[get_plans.php] User: reselleradmin | Assigned plans: 1-1,2-1
[get_plans.php] Plan combinations: Array([0] => 1-1, [1] => 2-1)
[get_plans.php] Looking for plan: external_id=1, currency_id=1
[get_plans.php] Found plan: Basic Plan
[get_plans.php] Reseller admin - included plan (category: new_device)
```

**Frontend Debugging:**
```javascript
// Check current user permissions
console.log(currentUser);
console.log('Is Reseller Admin:', currentUser.is_reseller_admin);
console.log('View All Accounts:', localStorage.getItem('viewAllAccounts'));
```

---

## Future Enhancements

### Potential Improvements:

1. **Audit Trail**
   - Log all reseller admin actions (edits, deletions, credit adjustments)
   - Track who made changes and when
   - Store in separate audit table

2. **Granular Permissions**
   - Allow super admins to customize which features reseller admins can access
   - Per-permission toggles (can assign plans, can adjust credit, etc.)

3. **Bulk Operations**
   - Bulk credit adjustment
   - Bulk plan assignment
   - Bulk reseller import/export

4. **Notifications**
   - Email notifications when reseller admin makes changes
   - SMS alerts for credit adjustments
   - Webhook integration for external systems

5. **Dashboard Analytics**
   - Reseller admin activity dashboard
   - Credit adjustment history graph
   - Plan assignment statistics

---

## Conclusion

This implementation provides a complete reseller admin permission system with:

- ✅ Full reseller management capabilities
- ✅ Intelligent plan filtering by category
- ✅ Dual UI modes (cards vs dropdowns)
- ✅ Comprehensive security controls
- ✅ Self-service protection mechanisms
- ✅ Backward compatibility with existing code

All features have been tested and verified to work correctly. The system maintains security while providing flexibility for reseller admin users to manage their subordinate resellers effectively.

---

**Document Version:** 1.0
**Last Updated:** 2025-11-25
**Author:** Development Team
**Status:** Production Ready ✅
