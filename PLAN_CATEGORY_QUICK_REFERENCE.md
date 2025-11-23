# Plan Category System - Quick Reference

**Version:** 1.11.0 | **Last Updated:** November 23, 2025

---

## Category Values

| Category Value | Display Label | Use Case | Shown Where |
|---------------|---------------|----------|-------------|
| `new_device` | New Device | Plans for new device activations | Plans table, Add/Edit modals |
| `application` | Application | App-only subscription plans | Plans table, Add/Edit modals |
| `renew_device` | Renew Device | Plans for account renewals | **Plans table, Add/Edit modals, Renewal modal** |
| `NULL` | - | Legacy plans without category | Plans table only |

---

## Database Schema

```sql
-- Table: _plans
category VARCHAR(20) NULL

-- Valid values:
'new_device'
'application'
'renew_device'
NULL

-- Index:
CREATE INDEX idx_plans_category ON _plans(category);
```

---

## JavaScript Filtering Examples

### Filter Renewal Plans (IMPLEMENTED)
```javascript
// In loadRenewalPlans() function - dashboard.js:2287
const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');
```

### Filter New Device Plans (NOT YET IMPLEMENTED)
```javascript
const newDevicePlans = result.plans.filter(plan => plan.category === 'new_device');
```

### Filter Application Plans (NOT YET IMPLEMENTED)
```javascript
const applicationPlans = result.plans.filter(plan => plan.category === 'application');
```

### Show All Plans
```javascript
// No filtering needed, use result.plans directly
const allPlans = result.plans;
```

---

## Category Label Mapping

```javascript
// In dashboard.js - for display purposes
const categoryLabels = {
    'new_device': 'New Device',
    'application': 'Application',
    'renew_device': 'Renew Device'
};

const categoryDisplay = plan.category
    ? (categoryLabels[plan.category] || plan.category)
    : '-';
```

---

## SQL Queries

### Set Plan Category
```sql
UPDATE _plans SET category = 'renew_device' WHERE id = 123;
```

### Get Plans by Category
```sql
SELECT * FROM _plans WHERE category = 'renew_device';
```

### Count Plans by Category
```sql
SELECT
    category,
    COUNT(*) as count
FROM _plans
GROUP BY category;
```

### Find Plans Without Category
```sql
SELECT * FROM _plans WHERE category IS NULL;
```

### Set All Plans to Renewal
```sql
UPDATE _plans SET category = 'renew_device';
```

---

## API Endpoints

### add_plan.php
**Parameter:** `category` (optional)
**Valid Values:** `new_device`, `application`, `renew_device`

```javascript
// Example API call
fetch(`add_plan.php?tariff_id=1&name=Plan&currency=IRR&price=100&days=30&category=renew_device`)
```

### edit_plan.php
**Parameter:** `category` (optional)
**Valid Values:** `new_device`, `application`, `renew_device`

```javascript
// Example API call
fetch(`edit_plan.php?plan_id=123&name=Plan&price=100&days=30&category=renew_device`)
```

### get_plans.php
**Returns:** Plan objects with `category` field
**No filtering** - returns all plans, filtering happens client-side

---

## Common Tasks

### Task: Add New Renewal Plan
1. Go to Plans tab
2. Click "+ Add Plan"
3. Select tariff
4. Fill in name, price, days
5. **Set category to "Renew Device"**
6. Submit

### Task: Convert Existing Plan to Renewal
1. Go to Plans tab
2. Click "Edit" on plan
3. Change category to "Renew Device"
4. Click "Update Plan"

### Task: Check Which Plans are Renewal Plans
```sql
SELECT id, name, category FROM _plans WHERE category = 'renew_device';
```

### Task: Bulk Update All Plans
```sql
-- Make all plans renewal plans
UPDATE _plans SET category = 'renew_device';

-- Or update specific plans
UPDATE _plans
SET category = 'renew_device'
WHERE name LIKE '%Renewal%';
```

---

## Validation Rules

### Backend (PHP)
```php
$valid_categories = ['new_device', 'application', 'renew_device'];
if ($category && !in_array($category, $valid_categories)) {
    echo json_encode(['error' => 1, 'err_msg' => 'Invalid category']);
    exit;
}
```

### Frontend (HTML)
```html
<select name="category" required>
    <option value="">-- Select Category --</option>
    <option value="new_device">New Device</option>
    <option value="application">Application</option>
    <option value="renew_device">Renew Device</option>
</select>
```

---

## Testing Quick Checks

### ✅ Renewal Filtering Works
1. Set Plan ID 1: `category = 'new_device'`
2. Set Plan ID 2: `category = 'renew_device'`
3. Click Renew on account
4. **Expected:** Only Plan ID 2 visible

### ✅ Category Display Works
1. Check Plans table
2. **Expected:** Category column shows labels not codes
3. **Expected:** NULL shows as "-"

### ✅ Edit Plan Works
1. Click Edit on any plan
2. Change category
3. Submit
4. **Expected:** Category updated in database and UI

---

## Troubleshooting

| Problem | Cause | Solution |
|---------|-------|----------|
| "No renewal plans available" | No plans with `category='renew_device'` | Edit plans, set category to "Renew Device" |
| Wrong plans showing in renewal | Plans not categorized | Update plan categories |
| Category column missing | Migration not run | Run `migration_add_plan_category.sql` |
| Category not saving | Validation failing | Use valid values only |
| Filter not working | Browser cache | Hard refresh or clear cache |

---

## Code Locations

| Feature | File | Lines |
|---------|------|-------|
| Plans table display | dashboard.html | 187-202 |
| Add Plan modal | dashboard.html | 1227-1236 |
| Edit Plan modal | dashboard.html | 1242-1294 |
| Load plans function | dashboard.js | 1500-1556 |
| Edit plan function | dashboard.js | 1852-1885 |
| Submit edit function | dashboard.js | 1887-1911 |
| **Renewal filtering** | **dashboard.js** | **2287-2335** |
| Add plan backend | add_plan.php | 68, 96-101, 114, 125 |
| Edit plan backend | edit_plan.php | Complete file |
| Database migration | migration_add_plan_category.sql | Complete file |

---

## Migration Script

```sql
-- Run this once on local/production database
ALTER TABLE _plans
ADD COLUMN category VARCHAR(20) NULL
AFTER days;

CREATE INDEX idx_plans_category ON _plans(category);
```

**Local Database:**
```bash
mysql -u root showboxt_panel < migration_add_plan_category.sql
```

**Production Database:**
```bash
mysql -u root -p'kami1013' stalker_db < migration_add_plan_category.sql
```

---

## Key Design Decisions

1. **Category is NULLABLE** - Maintains backward compatibility with existing plans
2. **Validation is OPTIONAL** - Can save plans without category (shows as "-")
3. **Filtering is CLIENT-SIDE** - JavaScript filters, not SQL (more flexible)
4. **NULL plans are FILTERED OUT** - Only explicit categories shown in filtered views
5. **Three categories ONLY** - Simple, focused system; can expand later if needed

---

**Quick Access:**
- [Full Documentation](PLAN_MANAGEMENT_ENHANCEMENTS.md)
- [Renewal Filtering Details](RENEWAL_FILTERING_IMPLEMENTATION.md)
- [Migration Script](migration_add_plan_category.sql)

**Developer:** Claude & Kambiz | **Repository:** Billing-Second-Generation
