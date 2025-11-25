# Multi-Stage SMS Expiry Reminder System

**ShowBox Billing Panel v1.9.0**
**Implementation Date:** November 23, 2025

---

## Overview

The Multi-Stage SMS Reminder System automatically sends personalized SMS notifications to customers at 4 critical stages before and after account expiry:

1. **7 Days Before Expiry** - Early warning
2. **3 Days Before Expiry** - Urgent reminder (72 hours)
3. **1 Day Before Expiry** - Final warning (24 hours)
4. **Account Expired** - Service deactivation notification

This intelligent system ensures maximum customer retention by:
- ✅ Preventing duplicate messages
- ✅ Tracking each reminder stage independently
- ✅ Only sending if customer hasn't renewed
- ✅ Personalizing each message with customer data

---

## How It Works

### Stage 1: 7 Days Before Expiry
- **When**: Exactly 7 days before account end_date
- **Condition**: Account status = 'active'
- **Message**: Gentle reminder with expiry date
- **Action**: Customers have time to plan renewal

### Stage 2: 3 Days Before Expiry (72 Hours)
- **When**: Exactly 3 days before account end_date
- **Condition**: Account status = 'active' AND no renewal since Stage 1
- **Message**: More urgent tone with warning icon ⚠️
- **Action**: Prompts immediate action

### Stage 3: 1 Day Before Expiry (24 Hours)
- **When**: Exactly 1 day before account end_date
- **Condition**: Account status = 'active' AND no renewal since Stage 2
- **Message**: Critical alert with urgent icon 🚨
- **Action**: Last chance to prevent service interruption

### Stage 4: Account Expired
- **When**: On or after account end_date
- **Condition**: Account status = 'inactive'
- **Message**: Service deactivated notification ❌
- **Action**: Informs customer service has stopped, provides renewal contact

---

## Database Structure

### New Table: _sms_reminder_tracking

Tracks which reminders have been sent to prevent duplicates:

```sql
CREATE TABLE _sms_reminder_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    mac VARCHAR(20),
    reminder_stage ENUM('7days', '3days', '1day', 'expired') NOT NULL,
    sent_at DATETIME NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_stage (account_id, reminder_stage),
    INDEX idx_mac_stage (mac, reminder_stage),
    INDEX idx_end_date (end_date),
    UNIQUE KEY unique_reminder (account_id, reminder_stage, end_date)
);
```

**Key Features:**
- `UNIQUE KEY` prevents duplicate reminders for same account/stage/expiry
- Indexes for fast lookups
- Tracks MAC address for additional reference
- Records exact send time

### Updated Table: _sms_settings

Added new column:

```sql
ALTER TABLE _sms_settings
ADD COLUMN enable_multistage_reminders TINYINT(1) DEFAULT 1 AFTER auto_send_enabled;
```

### New Templates: _sms_templates

Four new pre-configured templates (Persian language):

1. **7 Days Before Expiry**
2. **3 Days Before Expiry**
3. **1 Day Before Expiry**
4. **Account Expired**

All templates support variables: `{name}`, `{mac}`, `{expiry_date}`, `{days}`

---

## Installation & Setup

### Step 1: Run Database Upgrade

```bash
cd "/path/to/billing/panel"
php upgrade_multistage_reminders.php
```

**Expected Output:**
```
=== Multi-Stage Expiry Reminder System - Database Upgrade ===

Step 1: Creating reminder tracking table...
✓ Reminder tracking table created

Step 2: Updating SMS settings table...
✓ Added enable_multistage_reminders column

Step 3: Adding new message templates...
  ✓ Added template '7 Days Before Expiry' for user admin
  ✓ Added template '3 Days Before Expiry' for user admin
  ✓ Added template '1 Day Before Expiry' for user admin
  ✓ Added template 'Account Expired' for user admin

✅ Multi-Stage Reminder System upgrade completed successfully!
```

### Step 2: Configure Cron Job

**Option A: Replace existing cron job**

Edit your crontab:
```bash
crontab -e
```

Replace the old cron line with:
```bash
# Multi-Stage SMS Expiry Reminders (runs daily at 9:00 AM)
0 9 * * * /usr/bin/php /path/to/billing/panel/cron_multistage_expiry_reminders.php >> /var/log/sms_reminders.log 2>&1
```

**Option B: Keep both cron jobs**

You can keep the old single-reminder cron for users who disable multi-stage:
```bash
# Old single-stage reminders
0 9 * * * /usr/bin/php /path/to/cron_send_expiry_sms.php >> /var/log/sms_old.log 2>&1

# New multi-stage reminders
0 9 * * * /usr/bin/php /path/to/cron_multistage_expiry_reminders.php >> /var/log/sms_new.log 2>&1
```

### Step 3: Enable in Dashboard

1. Login to your dashboard
2. Go to **Messaging → SMS Messages**
3. Enable **"Automatic Expiry SMS"** toggle
4. Enable **"Multi-Stage Reminders"** toggle (Recommended)
5. Click **"Save SMS Configuration"**

---

## User Interface

### SMS Configuration Section

**New Toggle:**
```
☑ Enable Multi-Stage Reminders (Recommended)
Send 4 reminders: 7 days, 3 days, 1 day before expiry, and when account expires
```

**Behavior:**
- When **ENABLED**: System sends 4-stage reminders automatically
- When **DISABLED**: Falls back to single reminder (old behavior)
- "Days Before Expiry" field is hidden when multi-stage is enabled

---

## Message Templates

### Template 1: 7 Days Before Expiry
```
{name}
عزیز، سرویس شما ۷ روز دیگر منقضی می‌شود
تاریخ اتمام: {expiry_date}
برای تمدید سریع با ما تماس بگیرید.

پشتیبانی: واتساپ 00447736932888
```

### Template 2: 3 Days Before Expiry
```
{name}
⚠️ عزیز، فقط ۳ روز تا پایان سرویس شما باقی مانده است!
تاریخ اتمام: {expiry_date}
لطفاً هرچه سریعتر تمدید کنید.

پشتیبانی: واتساپ 00447736932888
```

### Template 3: 1 Day Before Expiry
```
{name}
🚨 عزیز، فقط ۱ روز تا قطع سرویس شما باقی مانده!
تاریخ اتمام: {expiry_date}
برای جلوگیری از قطعی، همین حالا تمدید کنید.

پشتیبانی: واتساپ 00447736932888
```

### Template 4: Account Expired
```
{name}
❌ عزیز، سرویس شما منقضی شد
تاریخ اتمام: {expiry_date}
برای فعال‌سازی مجدد، لطفاً تمدید کنید.

پشتیبانی: واتساپ 00447736932888
```

**Customization:**
You can edit these templates in the Dashboard → Messaging → SMS Messages section.

---

## Duplicate Prevention Logic

### How It Works:

1. **Before sending any SMS**, the system checks `_sms_reminder_tracking`:
   ```sql
   SELECT id FROM _sms_reminder_tracking
   WHERE account_id = ?
   AND reminder_stage = ?
   AND end_date = ?
   ```

2. **If record exists**: Skip this account (already received this reminder)

3. **If no record**: Send SMS and insert tracking record

4. **Unique Key Constraint**: Database prevents duplicate entries
   ```sql
   UNIQUE KEY unique_reminder (account_id, reminder_stage, end_date)
   ```

### Renewal Scenario:

**Example:**
- Customer account expires: 2025-12-31
- Day 1 (2025-12-24): Receives "7 days" reminder ✅
- Day 2 (2025-12-25): Customer renews to 2026-01-31
- Day 3 (2025-12-28): No "3 days" reminder (end_date changed) ✅
- Day 4 (2026-01-24): Receives "7 days" reminder for NEW expiry ✅

**Why it works:**
- Tracking uses `end_date` as part of unique key
- When customer renews, `end_date` changes
- Old reminders don't apply to new expiry date
- Fresh reminder cycle starts for new expiry

---

## Cron Job Output

### Successful Run Example:

```
═══════════════════════════════════════════════════════════════
  Multi-Stage SMS Expiry Reminder Cron Job
  2025-11-23 09:00:01
═══════════════════════════════════════════════════════════════

Found 1 user(s) with SMS enabled

Processing user: admin (ID: 1)
  Stage: 7 days before expiry (target date: 2025-11-30)
    Found 5 account(s)
      - Skipping John Smith (reminder already sent)
    Sending SMS to 4 recipient(s)...
    ✓ SMS sent successfully (Bulk ID: 123456)
    Logged 4 SMS record(s)

  Stage: 3 days before expiry (target date: 2025-11-26)
    Found 3 account(s)
    Sending SMS to 3 recipient(s)...
    ✓ SMS sent successfully (Bulk ID: 123457)
    Logged 3 SMS record(s)

  Stage: 1 day before expiry (target date: 2025-11-24)
    Found 2 account(s)
    Sending SMS to 2 recipient(s)...
    ✓ SMS sent successfully (Bulk ID: 123458)
    Logged 2 SMS record(s)

  Stage: account expired (today) (target date: 2025-11-23)
    Found 1 account(s)
    Sending SMS to 1 recipient(s)...
    ✓ SMS sent successfully (Bulk ID: 123459)
    Logged 1 SMS record(s)

═══════════════════════════════════════════════════════════════
  Cron job completed successfully
═══════════════════════════════════════════════════════════════
```

---

## Testing

### Manual Test Run:

```bash
php cron_multistage_expiry_reminders.php
```

### Test with Specific Dates:

1. Create test account with `end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)`
2. Run cron manually
3. Check `_sms_logs` table for sent message
4. Check `_sms_reminder_tracking` table for tracking record

### SQL Queries for Testing:

**Check reminder tracking:**
```sql
SELECT * FROM _sms_reminder_tracking
ORDER BY sent_at DESC LIMIT 20;
```

**Check which accounts will receive 7-day reminder:**
```sql
SELECT id, mac, full_name, phone_number, end_date, status
FROM _accounts
WHERE end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
AND status = 'active'
AND phone_number IS NOT NULL
AND phone_number != '';
```

**Check expired accounts:**
```sql
SELECT id, mac, full_name, phone_number, end_date, status
FROM _accounts
WHERE end_date <= CURDATE()
AND status = 'inactive'
AND phone_number IS NOT NULL
AND phone_number != '';
```

---

## Troubleshooting

### Issue: No SMS Being Sent

**Check 1: Is multi-stage enabled?**
```sql
SELECT auto_send_enabled, enable_multistage_reminders
FROM _sms_settings WHERE user_id = 1;
```
Both should be `1`.

**Check 2: Are there accounts expiring?**
```sql
SELECT COUNT(*) FROM _accounts
WHERE end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
AND status = 'active'
AND phone_number IS NOT NULL;
```

**Check 3: Have reminders already been sent?**
```sql
SELECT * FROM _sms_reminder_tracking
WHERE reminder_stage = '7days'
AND end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY);
```

### Issue: Duplicate SMS Received

This should NOT happen due to UNIQUE constraint. If it does:

**Check tracking table:**
```sql
SELECT account_id, reminder_stage, end_date, COUNT(*)
FROM _sms_reminder_tracking
GROUP BY account_id, reminder_stage, end_date
HAVING COUNT(*) > 1;
```

**Fix duplicates:**
```sql
-- This should return 0 rows if unique constraint is working
-- If duplicates exist, the constraint may not have been created properly
```

### Issue: Cron Not Running

**Check cron logs:**
```bash
tail -f /var/log/sms_reminders.log
```

**Test manually:**
```bash
/usr/bin/php /path/to/cron_multistage_expiry_reminders.php
```

**Check cron permissions:**
```bash
ls -la /path/to/cron_multistage_expiry_reminders.php
# Should show -rwxr-xr-x (executable)
```

---

## Cost Estimation

### SMS Pricing (Faraz SMS):
- Local (Iran): ~$0.003 per SMS
- International: ~$0.10 per SMS

### Example Calculation:

**Scenario: 100 customers, all using multi-stage reminders**

**Month 1:**
- 100 customers receive "7 days" reminder = 100 SMS
- 80 don't renew, receive "3 days" reminder = 80 SMS
- 60 still don't renew, receive "1 day" reminder = 60 SMS
- 40 accounts expire, receive "expired" notification = 40 SMS
- **Total: 280 SMS × $0.003 = $0.84/month**

**Month 2 (retention improves):**
- 100 customers receive "7 days" reminder = 100 SMS
- 60 don't renew, receive "3 days" reminder = 60 SMS
- 30 still don't renew, receive "1 day" reminder = 30 SMS
- 10 accounts expire, receive "expired" notification = 10 SMS
- **Total: 200 SMS × $0.003 = $0.60/month**

**ROI Calculation:**
- If even 5 more customers renew due to reminders: 5 × $10 = $50 revenue
- Cost: $0.84
- **ROI: 5,852%** 🚀

---

## Benefits

### For Business Owners:
✅ **Reduced Churn**: Multi-touch reminders increase renewal rates
✅ **Automated**: Set-and-forget system
✅ **Intelligent**: Only sends if customer hasn't renewed
✅ **Scalable**: Handles thousands of accounts
✅ **Cost-Effective**: Minimal SMS costs, maximum ROI
✅ **Professional**: Timely, personalized communication

### For Customers:
✅ **Never Miss Renewal**: Multiple reminders ensure awareness
✅ **Timely Alerts**: Know exactly when to renew
✅ **Personalized**: Messages include their name and expiry date
✅ **Clear Action**: Direct contact info for support
✅ **Respectful**: No spam, only relevant reminders

---

## Version History

**v1.9.0 (November 23, 2025)**
- ✨ Multi-stage reminder system
- ✨ 4 reminder stages (7d, 3d, 1d, expired)
- ✨ Duplicate prevention tracking
- ✨ Persian message templates
- ✨ UI toggle for multi-stage mode

**v1.8.0 (November 22, 2025)**
- Initial SMS system
- Single-stage expiry reminders

---

## Files Reference

### New Files Created:
- `upgrade_multistage_reminders.php` - Database migration script
- `cron_multistage_expiry_reminders.php` - Cron job script
- `MULTISTAGE_SMS_GUIDE.md` - This documentation

### Modified Files:
- `dashboard.html` - Added multi-stage toggle
- `sms-functions.js` - Updated JS logic
- `update_sms_settings.php` - Added new field handling
- `CHANGELOG.md` - Version history

---

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review cron logs: `/var/log/sms_reminders.log`
3. Check database tracking tables
4. Test manually with `php cron_multistage_expiry_reminders.php`

---

**🎉 Enjoy your new multi-stage SMS reminder system!**
