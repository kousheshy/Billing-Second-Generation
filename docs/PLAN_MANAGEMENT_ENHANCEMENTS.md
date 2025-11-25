# Plan Management Enhancements

**Version:** 1.11.0 (Proposed)
**Date:** November 23, 2025
**Status:** Ready for Testing (Local Only)

## Overview

This update adds three major features to the Plan Management section:
1. **Edit Plan Functionality** - Admins can now edit existing plans
2. **Plan Categories** - Plans can be categorized for filtering (New Device, Application, Renew Device)
3. **Renewal Plan Filtering** - Resellers only see "Renew Device" plans when renewing accounts

---

## Features Implemented

### 1. Plan Category System

Plans can now be categorized into three types:
- **New Device** (`new_device`) - Plans for new device activations
- **Application** (`application`) - Plans for application-only subscriptions
- **Renew Device** (`renew_device`) - Plans specifically for renewals

**Purpose:** This categorization allows the system to filter plans based on context. For example, when a reseller is renewing an account, the system can show only "Renew Device" plans.

**UI Location:**
- Plans table now shows a "Category" column
- Add Plan modal has a new "Plan Category" dropdown (required field)
- Edit Plan modal includes the category dropdown

### 2. Edit Plan Functionality

Admins and Reseller Admins can now edit existing plans without deleting and recreating them.

**Editable Fields:**
- Plan Name
- Price
- Duration (Days)
- Category

**Non-Editable Fields:**
- Plan ID (External ID from Stalker Portal)
- Currency (cannot be changed after creation)

**Permissions:**
- Super Admin: Can edit all plans
- Reseller Admin: Can edit plans (based on existing permissions)
- Observers: Edit button is disabled

---

## Files Modified

### HTML Changes

**dashboard.html**

1. **Plans Table** (Lines 187-202)
   - Added "Category" column header
   - Updated colspan from 6 to 7 for empty state

2. **Add Plan Modal** (Lines 1227-1236)
   - Added "Plan Category" dropdown field with 3 options
   - Required field with validation

3. **Edit Plan Modal** (Lines 1242-1294) - NEW
   - Complete modal structure for editing plans
   - Hidden fields for plan ID, external ID, and currency
   - Read-only fields for Plan ID and Currency display
   - Editable fields: Name, Price, Days, Category
   - "Update Plan" submit button

### JavaScript Changes

**dashboard.js**

1. **loadPlans() Function** (Lines 1500-1556)
   - Added category label mapping for display
   - Added Edit button to action buttons
   - Updated empty state colspan to 7
   - Category display shows "New Device", "Application", "Renew Device", or "-" if not set

2. **editPlan() Function** (Lines 1852-1885) - NEW
   - Finds plan from availablePlans array by ID
   - Populates edit modal with plan data
   - Handles currency normalization (IRT → IRR)
   - Opens the edit modal

3. **submitEditPlan() Function** (Lines 1887-1911) - NEW
   - Handles form submission for plan updates
   - Calls edit_plan.php backend
   - Shows success/error alerts
   - Reloads plans table on success

4. **loadRenewalPlans() Function** (Lines 2287-2335) - MODIFIED
   - Added filtering to show only "Renew Device" category plans
   - Filter: `result.plans.filter(plan => plan.category === 'renew_device')`
   - Shows "No renewal plans available" if no renewal plans exist
   - Ensures resellers only see renewal-appropriate plans when renewing accounts

### PHP Backend Changes

**add_plan.php** (Modified)
- Line 68: Added `$category` parameter from GET request
- Lines 96-101: Added category validation (optional, must be one of 3 valid values)
- Line 114: Updated UPDATE query to include category
- Line 125: Updated INSERT query to include category

**edit_plan.php** (NEW FILE)
- Permission check: Super Admin or Reseller Admin only
- Validates all input parameters (plan_id, name, price, days, category)
- Category validation: Must be one of `new_device`, `application`, `renew_device`
- Checks if plan exists before updating
- Updates plan with new values
- Returns JSON success/error response

### Database Migration

**migration_add_plan_category.sql** (NEW FILE)
```sql
ALTER TABLE _plans
ADD COLUMN category VARCHAR(20) NULL
AFTER days;

CREATE INDEX idx_plans_category ON _plans(category);
```

---

## Testing Instructions

### Before Running Migration

1. **Backup your local database:**
   ```bash
   mysqldump -u root -p'rootpw@123' stalker_db _plans > _plans_backup_before_category.sql
   ```

### Running the Migration

2. **Execute the migration SQL:**
   ```bash
   mysql -u root -p'rootpw@123' stalker_db < migration_add_plan_category.sql
   ```

3. **Verify the column was added:**
   ```bash
   mysql -u root -p'rootpw@123' stalker_db -e "DESCRIBE _plans;"
   ```

   You should see a `category` column with type `varchar(20)` and nullable `YES`.

### Testing the Features

4. **Test Plan Listing:**
   - Log in to the dashboard
   - Navigate to the Plans tab
   - Verify the "Category" column appears
   - Existing plans should show "-" for category (NULL values)

5. **Test Add Plan with Category:**
   - Click "+ Add Plan"
   - Select a tariff
   - Fill in all fields including the new "Plan Category" dropdown
   - Submit the form
   - Verify the plan is created with the selected category
   - Check that the category displays correctly in the table

6. **Test Edit Plan:**
   - Click the "Edit" button on any plan
   - Verify the Edit Plan modal opens with correct data
   - Try changing the plan name, price, days, and category
   - Submit the form
   - Verify the plan is updated in the table
   - Confirm that Plan ID and Currency cannot be changed

7. **Test Category Display:**
   - Verify category labels display correctly:
     - `new_device` → "New Device"
     - `application` → "Application"
     - `renew_device` → "Renew Device"
     - `NULL` → "-"

8. **Test Observer Permissions:**
   - Log in as an observer user
   - Verify the Edit button is disabled and grayed out
   - Verify the Delete button is also disabled (existing behavior)

9. **Test Renewal Plan Filtering:**
   - Create/edit at least 2 plans with different categories:
     - Plan A: category = "new_device"
     - Plan B: category = "renew_device"
   - Log in as a reseller
   - Navigate to Account Management
   - Click "Renew" button on any existing account
   - **Expected Result:** Only Plan B (renew_device) should appear in the renewal modal
   - **Expected Result:** Plan A (new_device) should be hidden
   - If no renewal plans exist, should show "No renewal plans available"

### Edge Cases to Test

10. **Empty Category:**
   - Try editing a plan and leaving category empty
   - Should show validation error "Category is required"

11. **Invalid Category:**
    - Try manually sending an invalid category value via developer tools
    - Backend should reject with "Invalid category" error

12. **Currency Display:**
    - Edit a plan with IRT currency
    - Verify it displays as "IRR" in the edit modal

13. **Renewal Filtering with NULL Categories:**
    - Create a plan without setting category (NULL)
    - Try to renew an account
    - Verify that plans with NULL category are NOT shown in renewal modal
    - Only plans explicitly set to "renew_device" should appear

---

## Implemented Filtering Features

### 3. Renewal Plan Filtering (IMPLEMENTED)

**Feature:** When resellers open the Renew Account modal to renew an existing account, only plans categorized as "Renew Device" are displayed.

**Implementation:** Modified `loadRenewalPlans()` function in dashboard.js (Lines 2287-2335)

**Code:**
```javascript
// Filter plans to only show "Renew Device" category plans
const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');
```

**Behavior:**
- Resellers only see "Renew Device" plans when renewing accounts
- Plans with "New Device" or "Application" categories are hidden in renewal modal
- If no renewal plans exist, displays: "No renewal plans available"
- Fallback: Plans without category (NULL) are also filtered out

**Benefits:**
- Prevents resellers from accidentally selecting wrong plan types
- Simplifies renewal workflow by showing only relevant plans
- Maintains clean separation between new device and renewal pricing

**Testing:**
1. Create/edit plans with `category = 'renew_device'`
2. Log in as reseller
3. Click "Renew" button on any account
4. Verify only renewal plans are displayed

---

## Future Enhancements

### Additional Filtering Options

1. **Filter New Device Plans for Add Account:**
   ```javascript
   // When adding a new account
   const newDevicePlans = result.plans.filter(plan => plan.category === 'new_device');
   ```

2. **Filter Application Plans for App-Only Subscriptions:**
   ```javascript
   // For application-only accounts
   const appPlans = result.plans.filter(plan => plan.category === 'application');
   ```

3. **Add Category Filter Dropdown:**
   - Add a category filter dropdown above the Plans table
   - Allow admins to filter plans by category
   - Show plan count per category

### Example Filter Implementation

```javascript
function filterPlansByCategory(category) {
    const tbody = document.getElementById('plans-tbody');
    tbody.innerHTML = '';

    const filteredPlans = category === 'all'
        ? availablePlans
        : availablePlans.filter(plan => plan.category === category);

    filteredPlans.forEach(plan => {
        // Render plan rows
    });
}
```

---

## API Changes

### New Endpoint: edit_plan.php

**Method:** GET
**Authentication:** Required (Admin or Reseller Admin)

**Parameters:**
- `plan_id` (required) - Internal plan ID
- `external_id` (required) - Stalker Portal tariff ID
- `currency` (required) - Currency code (cannot be changed, passed for reference)
- `name` (required) - Plan name
- `price` (required) - Plan price (numeric, >= 0)
- `days` (required) - Duration in days (numeric, >= 1)
- `category` (optional) - Plan category: `new_device`, `application`, or `renew_device`

**Response:**
```json
{
    "error": 0,
    "err_msg": "",
    "message": "Plan updated successfully"
}
```

**Error Response:**
```json
{
    "error": 1,
    "err_msg": "Error message here"
}
```

### Modified Endpoint: add_plan.php

**New Parameter:**
- `category` (optional) - Plan category: `new_device`, `application`, or `renew_device`

---

## Database Schema Changes

### _plans Table

**New Column:**
```sql
category VARCHAR(20) NULL
```

**New Index:**
```sql
CREATE INDEX idx_plans_category ON _plans(category);
```

**Valid Values:**
- `new_device`
- `application`
- `renew_device`
- `NULL` (for legacy plans without category)

---

## Deployment Checklist (When Ready for Production)

- [ ] Backup production database
- [ ] Run migration_add_plan_category.sql on production
- [ ] Verify column was added: `DESCRIBE _plans;`
- [ ] Upload edit_plan.php to production server
- [ ] Upload modified add_plan.php to production server
- [ ] Upload modified dashboard.html to production server
- [ ] Upload modified dashboard.js to production server
- [ ] Clear browser cache
- [ ] Test all functionality on production
- [ ] Update existing plans with appropriate categories (optional)

---

## Version Compatibility

- Requires existing _plans table structure
- Backward compatible: Plans without category will display "-"
- All existing plans functionality continues to work

---

## Notes

- All changes are currently LOCAL ONLY (not deployed to production)
- Migration SQL is provided in `migration_add_plan_category.sql`
- The category field is optional (nullable) to maintain backward compatibility
- Observers cannot edit or delete plans (existing + new behavior)
- Currency cannot be changed after plan creation (intentional design)

---

**Developer:** Claude & Kambiz
**Repository:** Billing-Second-Generation
**Contact:** GitHub @kousheshy
