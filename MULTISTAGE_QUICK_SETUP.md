# Multi-Stage SMS Reminders - Quick Setup Guide

**3-Minute Setup** | ShowBox Billing Panel v1.9.0

---

## What You'll Get

Your system will automatically send **4 SMS reminders** to customers:

1. **7 days before expiry** → Early warning ⏰
2. **3 days before expiry** → Urgent reminder ⚠️
3. **1 day before expiry** → Final warning 🚨
4. **Account expired** → Service deactivated ❌

**Smart Features:**
- ✅ No duplicate messages
- ✅ Stops sending if customer renews
- ✅ Personalized with customer name
- ✅ Persian language support
- ✅ Fully automated

---

## Step 1: Run Database Upgrade (1 minute)

```bash
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"
php upgrade_multistage_reminders.php
```

**Expected output:**
```
✓ Reminder tracking table created
✓ Added enable_multistage_reminders column
✓ Added template '7 Days Before Expiry' for user admin
✓ Added template '3 Days Before Expiry' for user admin
✓ Added template '1 Day Before Expiry' for user admin
✓ Added template 'Account Expired' for user admin
✅ Multi-Stage Reminder System upgrade completed successfully!
```

---

## Step 2: Update Cron Job (1 minute)

### Option A: Update Existing Cron

Edit your crontab:
```bash
crontab -e
```

**Find this line:**
```bash
0 9 * * * /usr/bin/php /path/to/cron_send_expiry_sms.php >> /var/log/sms.log 2>&1
```

**Replace with:**
```bash
0 9 * * * /usr/bin/php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_multistage_expiry_reminders.php" >> /var/log/sms_multistage.log 2>&1
```

### Option B: Test Manually First

Run the cron script manually to test:
```bash
php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_multistage_expiry_reminders.php"
```

You should see:
```
═══════════════════════════════════════════════════════════════
  Multi-Stage SMS Expiry Reminder Cron Job
  2025-11-23 09:00:01
═══════════════════════════════════════════════════════════════

Found X user(s) with SMS enabled
...
```

---

## Step 3: Enable in Dashboard (1 minute)

1. **Login** to your ShowBox Billing Panel
2. Go to **Messaging → SMS Messages** tab
3. **Check these boxes:**
   - ☑ Enable Automatic Expiry SMS
   - ☑ Enable Multi-Stage Reminders (Recommended)
4. Click **"Save SMS Configuration"**

**Screenshot of what you'll see:**
```
┌─────────────────────────────────────────────┐
│ ☑ Enable Automatic Expiry SMS              │
│ Automatically send SMS reminders            │
│                                             │
│ ☑ Enable Multi-Stage Reminders             │
│ Send 4 reminders: 7 days, 3 days, 1 day    │
│ before expiry, and when account expires     │
└─────────────────────────────────────────────┘
```

---

## That's It! 🎉

Your multi-stage SMS reminder system is now active!

---

## What Happens Next?

### Daily at 9:00 AM:
The cron job runs and checks:

**Stage 1: 7 days before expiry**
- Finds accounts expiring in exactly 7 days
- Sends: "{name} عزیز، سرویس شما ۷ روز دیگر منقضی می‌شود..."

**Stage 2: 3 days before expiry**
- Finds accounts expiring in exactly 3 days (that haven't renewed)
- Sends: "{name} ⚠️ عزیز، فقط ۳ روز تا پایان سرویس..."

**Stage 3: 1 day before expiry**
- Finds accounts expiring tomorrow (that still haven't renewed)
- Sends: "{name} 🚨 عزیز، فقط ۱ روز تا قطع سرویس..."

**Stage 4: Account expired**
- Finds accounts that expired today or earlier
- Sends: "{name} ❌ عزیز، سرویس شما منقضی شد..."

---

## Customizing Messages

You can edit the message templates in the dashboard:

1. Go to **Messaging → SMS Messages**
2. Scroll to **Message Templates** section
3. Select template (e.g., "7 Days Before Expiry")
4. Edit the message
5. Use variables: `{name}`, `{mac}`, `{expiry_date}`, `{days}`

---

## Troubleshooting

### "No users with multi-stage reminders enabled"

**Solution:** Go to dashboard and enable both toggles:
- ☑ Enable Automatic Expiry SMS
- ☑ Enable Multi-Stage Reminders

---

### No SMS being sent

**Check 1:** Do you have accounts expiring in 7, 3, or 1 days?

```sql
SELECT id, full_name, phone_number, end_date, status
FROM _accounts
WHERE end_date IN (
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    DATE_ADD(CURDATE(), INTERVAL 3 DAY),
    DATE_ADD(CURDATE(), INTERVAL 1 DAY),
    CURDATE()
)
AND phone_number IS NOT NULL
AND phone_number != '';
```

**Check 2:** Run cron manually to see output:

```bash
php cron_multistage_expiry_reminders.php
```

**Check 3:** Verify database setup:

```sql
-- Should return 1 for multi-stage enabled users
SELECT auto_send_enabled, enable_multistage_reminders
FROM _sms_settings WHERE user_id = 1;
```

---

## Checking SMS History

### View in Dashboard:
1. Go to **Messaging → SMS Messages**
2. Scroll to **SMS History** section
3. Filter by type: "Expiry Reminder"

### View in Database:
```sql
SELECT
    recipient_name,
    recipient_number,
    message,
    status,
    sent_at
FROM _sms_logs
WHERE message_type = 'expiry_reminder'
ORDER BY sent_at DESC
LIMIT 20;
```

### View Tracking:
```sql
SELECT
    a.full_name,
    a.phone_number,
    t.reminder_stage,
    t.sent_at,
    t.end_date
FROM _sms_reminder_tracking t
JOIN _accounts a ON t.account_id = a.id
ORDER BY t.sent_at DESC
LIMIT 20;
```

---

## Cost Calculation

**Example: 100 customers**

| Stage | Customers | SMS Cost |
|-------|-----------|----------|
| 7 days | 100 | $0.30 |
| 3 days | 60 (40 renewed) | $0.18 |
| 1 day | 30 (30 renewed) | $0.09 |
| Expired | 10 (20 renewed) | $0.03 |
| **Total** | **200 SMS** | **$0.60/month** |

**ROI:**
- If just 5 more customers renew: 5 × $10 = $50 revenue
- Cost: $0.60
- **Profit: $49.40** 💰

---

## Next Steps

### Optional Enhancements:

1. **Customize templates** with your branding
2. **Test with a few accounts** first
3. **Monitor logs** for the first week
4. **Adjust timing** if needed (edit cron schedule)

### Documentation:

- Full Guide: [MULTISTAGE_SMS_GUIDE.md](MULTISTAGE_SMS_GUIDE.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Original SMS Guide: [SMS_IMPLEMENTATION_GUIDE.md](SMS_IMPLEMENTATION_GUIDE.md)

---

## Support

**Questions?**
- Check the full guide: `MULTISTAGE_SMS_GUIDE.md`
- View logs: `/var/log/sms_multistage.log`
- Test manually: `php cron_multistage_expiry_reminders.php`

**Everything working?**
Sit back and watch your renewal rates improve! 📈

---

**Setup completed!** 🚀
Your customers will now receive timely reminders, reducing churn and improving retention.
