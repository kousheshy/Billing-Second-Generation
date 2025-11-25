# Renewal Plan Filtering Implementation

**Version:** 1.11.0
**Date:** November 23, 2025
**Feature:** Automatic plan filtering for account renewals
**Status:** Implemented (Local Only)

---

## Feature Summary

When resellers open the **Renew Account** modal to renew an existing customer account, the system now automatically filters plans to show **only** plans categorized as "Renew Device". This ensures resellers cannot accidentally select incorrect plan types (New Device or Application plans) during the renewal process.

---

## Business Purpose

### Problem Solved
- Previously, resellers saw ALL plans when renewing accounts
- Risk of selecting wrong plan type (e.g., new device plan for a renewal)
- Confusing UI with too many irrelevant options
- Potential pricing errors if wrong plan type selected

### Solution Implemented
- Automatic filtering based on plan category
- Only "Renew Device" (`category = 'renew_device'`) plans shown
- Clean, focused renewal workflow
- Prevents accidental selection errors

---

## Technical Implementation

### File Modified
**dashboard.js** - Lines 2287-2335

### Function Modified
`loadRenewalPlans()`

### Code Change

**Before:**
```javascript
if(result.error == 0 && result.plans) {
    result.plans.forEach(plan => {
        // Display all plans
        const card = document.createElement('div');
        // ... render plan card
        container.appendChild(card);
    });
}
```

**After:**
```javascript
if(result.error == 0 && result.plans) {
    // Filter plans to only show "Renew Device" category plans
    const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');

    if(renewalPlans.length > 0) {
        renewalPlans.forEach(plan => {
            // Display only renewal plans
            const card = document.createElement('div');
            // ... render plan card
            container.appendChild(card);
        });
    } else {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">No renewal plans available</p>';
    }
}
```

### Key Changes
1. Added filter: `result.plans.filter(plan => plan.category === 'renew_device')`
2. Check if filtered array has plans: `if(renewalPlans.length > 0)`
3. Display specific message if no renewal plans: "No renewal plans available"

---

## Behavior

### What Gets Shown
✅ Plans with `category = 'renew_device'`
✅ "No renewal plans available" message if none exist

### What Gets Hidden
❌ Plans with `category = 'new_device'`
❌ Plans with `category = 'application'`
❌ Plans with `category = NULL` (no category set)

---

## User Flow

1. **Reseller logs in** to the dashboard
2. **Navigate to Account Management**
3. **Click "Renew" button** on any existing account
4. **Renew Account modal opens**
5. **System automatically:**
   - Fetches all plans from database
   - Filters to show only renewal category plans
   - Displays filtered plans as clickable cards
6. **Reseller selects** renewal plan
7. **Completes renewal** process

---

## Testing Instructions

### Prerequisites
1. Ensure `category` column exists in `_plans` table
2. Have at least one plan with `category = 'renew_device'`
3. Have reseller account credentials

### Test Steps

**Test 1: Verify Filtering Works**
1. Create two test plans:
   ```sql
   -- Plan A: New Device (should be HIDDEN)
   UPDATE _plans SET category = 'new_device' WHERE id = 1;

   -- Plan B: Renew Device (should be SHOWN)
   UPDATE _plans SET category = 'renew_device' WHERE id = 2;
   ```

2. Log in as reseller
3. Go to Account Management
4. Click "Renew" on any account
5. **Expected:** Only Plan B appears in renewal modal
6. **Expected:** Plan A is not visible

**Test 2: No Renewal Plans Available**
1. Set all plans to `category = 'new_device'`
2. Log in as reseller
3. Click "Renew" on any account
4. **Expected:** Message shows "No renewal plans available"

**Test 3: NULL Category Handling**
1. Create plan with `category = NULL`
2. Log in as reseller
3. Click "Renew" on any account
4. **Expected:** Plan with NULL category is NOT shown
5. **Expected:** Only explicit `renew_device` plans appear

---

## Database Requirements

### Required Column
```sql
ALTER TABLE _plans ADD COLUMN category VARCHAR(20) NULL;
```

### Valid Category Values
- `'new_device'` - New device plans
- `'application'` - Application-only plans
- `'renew_device'` - **Renewal plans** (shown in renewal modal)
- `NULL` - Legacy plans without category

### Index (Recommended)
```sql
CREATE INDEX idx_plans_category ON _plans(category);
```

---

## Benefits

### For Resellers
- ✅ Simpler renewal process
- ✅ Fewer options = less confusion
- ✅ Can't select wrong plan type by mistake
- ✅ Faster workflow

### For Admins
- ✅ Better plan organization
- ✅ Separate pricing for renewals vs new devices
- ✅ Reduced support tickets from incorrect plan selection
- ✅ Clean data separation

### For Business
- ✅ Prevent revenue loss from pricing errors
- ✅ Maintain correct pricing strategies
- ✅ Better reporting per plan category
- ✅ Professional, organized system

---

## Edge Cases Handled

### Case 1: No Renewal Plans Exist
**Scenario:** All plans have categories other than `renew_device`
**Behavior:** Displays "No renewal plans available"
**Impact:** Prevents error, informs user clearly

### Case 2: Mixed Categories
**Scenario:** Database has new_device, application, and renew_device plans
**Behavior:** Only renew_device plans displayed
**Impact:** Correct filtering regardless of data mix

### Case 3: NULL Categories
**Scenario:** Legacy plans without category set
**Behavior:** NULL plans are filtered out (not shown)
**Impact:** Forces proper categorization of plans

### Case 4: Empty Plans Array
**Scenario:** `get_plans.php` returns empty array
**Behavior:** Displays "No plans available"
**Impact:** Graceful handling of empty state

---

## Future Enhancements

### Potential Extensions

1. **Filter New Device Plans for Add Account**
   - Show only `new_device` plans when adding new accounts
   - Similar filtering approach

2. **Filter Application Plans**
   - Create separate flow for application-only subscriptions
   - Show only `application` category plans

3. **Category-Based Permissions**
   - Allow resellers access to specific plan categories only
   - More granular control beyond filtering

4. **Plan Category Statistics**
   - Dashboard showing count per category
   - Revenue breakdown by category

---

## API Impact

### Endpoint Used
**get_plans.php**
- No changes required
- Already returns `category` field in plan objects
- Filtering happens client-side in JavaScript

### Data Flow
1. `loadRenewalPlans()` calls `get_plans.php`
2. Receives full plan array with category field
3. JavaScript filters array: `.filter(plan => plan.category === 'renew_device')`
4. Displays filtered results

---

## Deployment Checklist

When deploying to production:

- [ ] Verify `category` column exists in production `_plans` table
- [ ] Categorize existing plans appropriately
- [ ] Upload modified `dashboard.js` to production
- [ ] Clear browser cache
- [ ] Test renewal modal with reseller account
- [ ] Verify filtering works correctly
- [ ] Check "No renewal plans available" message displays if needed
- [ ] Monitor for any errors in browser console

---

## Rollback Plan

If issues occur:

1. **Quick Fix:** Set all plans to `category = 'renew_device'`
   ```sql
   UPDATE _plans SET category = 'renew_device';
   ```

2. **Full Rollback:** Revert `loadRenewalPlans()` function to remove filter
   ```javascript
   // Remove this line:
   const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');

   // Use result.plans directly:
   result.plans.forEach(plan => { ... });
   ```

---

## Support Notes

### Common Issues

**Issue:** "No renewal plans available" showing when plans exist
**Cause:** Plans not categorized as `renew_device`
**Solution:** Edit plans and set category to "Renew Device"

**Issue:** Wrong plans still showing
**Cause:** Browser cache or old JavaScript
**Solution:** Hard refresh (Ctrl+Shift+R) or clear cache

**Issue:** Filter not working
**Cause:** Database missing `category` column
**Solution:** Run migration SQL to add column

---

## Related Documentation

- [PLAN_MANAGEMENT_ENHANCEMENTS.md](PLAN_MANAGEMENT_ENHANCEMENTS.md) - Complete feature documentation
- [migration_add_plan_category.sql](migration_add_plan_category.sql) - Database migration
- [edit_plan.php](edit_plan.php) - Plan editing backend
- [dashboard.js](dashboard.js#L2287) - Implementation code

---

**Developer:** Claude & Kambiz
**Repository:** Billing-Second-Generation
**Contact:** GitHub @kousheshy
**Environment:** Local Development Only (Not Yet Deployed)
