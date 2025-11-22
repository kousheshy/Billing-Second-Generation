# Automated Expiry Reminder System - Cron Job Setup

## Overview
The automatic expiry reminder system requires a cron job to run daily. This job checks all users with `auto_send_enabled = 1` and automatically sends reminders to their expiring customers.

---

## Quick Setup (Linux/macOS)

### 1. Make the script executable
```bash
chmod +x "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php"
```

### 2. Test the script manually
```bash
php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php"
```

**Expected output if no users have auto-send enabled:**
```
[2025-11-22 09:00:00] Starting automated expiry reminder check...
[2025-11-22 09:00:00] No users have auto-send enabled. Exiting.
```

### 3. Setup cron job

Edit your crontab:
```bash
crontab -e
```

Add one of these lines (choose based on your preference):

**Option A: Run daily at 9:00 AM**
```cron
0 9 * * * /usr/bin/php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php" >> "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/logs/reminder_cron.log" 2>&1
```

**Option B: Run daily at 8:00 AM**
```cron
0 8 * * * /usr/bin/php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php" >> "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/logs/reminder_cron.log" 2>&1
```

**Option C: Run twice daily (9 AM and 6 PM)**
```cron
0 9,18 * * * /usr/bin/php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php" >> "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/logs/reminder_cron.log" 2>&1
```

**Option D: Run every hour (for testing)**
```cron
0 * * * * /usr/bin/php "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/cron_check_expiry_reminders.php" >> "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/logs/reminder_cron.log" 2>&1
```

### 4. Create logs directory
```bash
mkdir -p "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh/logs"
```

### 5. Verify cron job is scheduled
```bash
crontab -l
```

---

## Windows Setup (Task Scheduler)

### 1. Open Task Scheduler
- Press `Win + R`
- Type `taskschd.msc` and press Enter

### 2. Create Basic Task
1. Click **Create Basic Task** in the Actions panel
2. Name: `Expiry Reminder Cron`
3. Description: `Automated expiry reminder system`

### 3. Set Trigger
- **When**: Daily
- **Start**: Tomorrow at 9:00 AM
- **Recur every**: 1 days

### 4. Set Action
- **Action**: Start a program
- **Program/script**: `C:\php\php.exe` (adjust to your PHP path)
- **Add arguments**: `"C:\path\to\cron_check_expiry_reminders.php"`
- **Start in**: `C:\path\to\` (your project directory)

### 5. Additional Settings
- Check **Run whether user is logged on or not**
- Check **Run with highest privileges**
- In **Conditions** tab, uncheck **Start the task only if the computer is on AC power**

---

## Production Server Setup (cPanel/Plesk)

### cPanel

1. Log in to cPanel
2. Navigate to **Advanced** → **Cron Jobs**
3. Set schedule:
   - **Minute**: 0
   - **Hour**: 9
   - **Day**: *
   - **Month**: *
   - **Weekday**: *
4. Command:
```bash
/usr/bin/php /home/username/public_html/cron_check_expiry_reminders.php >> /home/username/public_html/logs/reminder_cron.log 2>&1
```

### Plesk

1. Log in to Plesk
2. Go to **Websites & Domains** → **Scheduled Tasks**
3. Click **Add Task**
4. Set schedule: **Daily** at **9:00 AM**
5. Command:
```bash
/usr/bin/php /var/www/vhosts/yourdomain.com/httpdocs/cron_check_expiry_reminders.php
```

### Direct SSH Access

```bash
# Log in via SSH
ssh user@yourserver.com

# Edit crontab
crontab -e

# Add cron job
0 9 * * * /usr/bin/php /var/www/html/cron_check_expiry_reminders.php >> /var/www/html/logs/reminder_cron.log 2>&1
```

---

## Cron Schedule Examples

| Schedule | Cron Expression | Description |
|----------|-----------------|-------------|
| Daily at 9 AM | `0 9 * * *` | Recommended for most use cases |
| Daily at 8 AM | `0 8 * * *` | Early morning reminders |
| Twice daily (9 AM & 6 PM) | `0 9,18 * * *` | Morning and evening sweeps |
| Every 6 hours | `0 */6 * * *` | Frequent checking |
| Weekdays only at 9 AM | `0 9 * * 1-5` | Business days only |
| First day of month at 9 AM | `0 9 1 * *` | Monthly sweep |

---

## Testing the Cron Job

### 1. Enable auto-send for your account
1. Log in to dashboard as admin
2. Go to **Settings** → **Expiry Reminder Settings**
3. Toggle **Enable Automatic Reminders** to ON
4. Set **Days Before Expiry** to 7
5. Click **Save Settings**

### 2. Create test accounts
```sql
-- Create accounts expiring in 7 days
UPDATE _accounts
SET end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
WHERE username IN ('test001', 'test002')
LIMIT 2;
```

### 3. Run cron manually
```bash
php cron_check_expiry_reminders.php
```

### 4. Expected output
```
[2025-11-22 09:00:00] Starting automated expiry reminder check...
[2025-11-22 09:00:00] Found 1 user(s) with auto-send enabled
[2025-11-22 09:00:00] Processing user: admin (ID: 1)
[2025-11-22 09:00:00]   Days before expiry: 7
[2025-11-22 09:00:00]   Target expiry date: 2025-11-29
[2025-11-22 09:00:00]   Found 2 account(s) expiring on 2025-11-29
[2025-11-22 09:00:00]     SENT: test001 (Test User 001)
[2025-11-22 09:00:00]     SENT: test002 (Test User 002)
[2025-11-22 09:00:00]   User summary: Sent=2, Skipped=0, Failed=0
[2025-11-22 09:00:00] ===== CRON JOB COMPLETE =====
[2025-11-22 09:00:00] Total sent: 2
[2025-11-22 09:00:00] Total skipped: 0
[2025-11-22 09:00:00] Total failed: 0
[2025-11-22 09:00:00] ==============================
```

### 5. Verify in database
```sql
-- Check reminder log
SELECT * FROM _expiry_reminders
ORDER BY sent_at DESC
LIMIT 10;

-- Check last sweep timestamp
SELECT user_id, last_sweep_at, auto_send_enabled
FROM _reminder_settings
WHERE user_id = 1;
```

---

## Monitoring & Logs

### View cron logs
```bash
# View latest log entries
tail -f logs/reminder_cron.log

# View last 50 lines
tail -50 logs/reminder_cron.log

# Search for errors
grep "ERROR" logs/reminder_cron.log

# Search for sent reminders
grep "SENT" logs/reminder_cron.log
```

### Database monitoring
```sql
-- Daily reminder statistics
SELECT
    DATE(sent_at) as date,
    COUNT(*) as total_sent,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM _expiry_reminders
GROUP BY DATE(sent_at)
ORDER BY date DESC
LIMIT 30;

-- Users with auto-send enabled
SELECT u.id, u.username, u.full_name,
       rs.days_before_expiry,
       rs.auto_send_enabled,
       rs.last_sweep_at
FROM _users u
JOIN _reminder_settings rs ON u.id = rs.user_id
WHERE rs.auto_send_enabled = 1;
```

---

## Troubleshooting

### Cron job not running

**Check cron status (Linux):**
```bash
# Check if cron service is running
sudo service cron status

# Or on systemd systems
sudo systemctl status cron
```

**Check cron execution:**
```bash
# View system cron logs
sudo grep CRON /var/log/syslog

# Or
sudo tail -f /var/log/cron
```

### Script errors

**Common issues:**

1. **PHP path incorrect**
   ```bash
   # Find PHP path
   which php
   # Use the output in your cron job
   ```

2. **Permission denied**
   ```bash
   # Make script executable
   chmod +x cron_check_expiry_reminders.php
   ```

3. **Database connection error**
   - Verify config.php has correct credentials
   - Ensure MySQL is accessible from cron context

4. **No output in logs**
   - Check log directory exists and is writable
   - Verify file paths are absolute, not relative

### No reminders being sent

**Checklist:**
- ✓ Auto-send is enabled in dashboard (toggle ON)
- ✓ Accounts exist with end_date matching target (today + N days)
- ✓ Accounts have status = 1 (active)
- ✓ No duplicate reminders already sent
- ✓ User has STB control permission
- ✓ Stalker Portal API is accessible

**Debug command:**
```bash
# Run with verbose output
php cron_check_expiry_reminders.php 2>&1 | tee debug.log
```

---

## Security Considerations

1. **File permissions:**
   ```bash
   # Set secure permissions
   chmod 600 cron_check_expiry_reminders.php
   chmod 600 config.php
   chmod 700 logs/
   ```

2. **Log rotation:**
   ```bash
   # Create logrotate config
   sudo nano /etc/logrotate.d/reminder-cron

   # Add content:
   /path/to/logs/reminder_cron.log {
       weekly
       rotate 4
       compress
       missingok
       notifempty
   }
   ```

3. **Restrict cron access:**
   - Only run as web server user or specific cron user
   - Never run as root

---

## Advanced Configuration

### Multiple reminder waves

To send reminders at 14 days, 7 days, and 3 days before expiry:

1. Create 3 separate user accounts (or same user with different settings)
2. Set each to different `days_before_expiry` values
3. Enable auto-send for all
4. System will handle deduplication automatically

**OR** Run cron multiple times with different configs:
```bash
# 14 days before
0 9 * * * /usr/bin/php cron_check_expiry_reminders.php --days=14

# 7 days before
0 9 * * * /usr/bin/php cron_check_expiry_reminders.php --days=7

# 3 days before
0 9 * * * /usr/bin/php cron_check_expiry_reminders.php --days=3
```

(Note: Script modification needed for `--days` parameter support)

### Email notifications on failure

Add to cron job:
```bash
MAILTO=admin@yourdomain.com
0 9 * * * /usr/bin/php cron_check_expiry_reminders.php || echo "Reminder cron failed" | mail -s "Cron Failure Alert" admin@yourdomain.com
```

---

## Summary

✅ **Required Steps:**
1. Create logs directory
2. Test script manually
3. Add cron job (daily at 9 AM recommended)
4. Enable auto-send in dashboard
5. Monitor first run via logs

✅ **Recommended Schedule:**
**Daily at 9:00 AM** - Balances server load and timely reminders

✅ **For Production:**
Always use absolute paths and log output for debugging

The system is now fully automatic! When users enable the toggle and save, reminders will be sent daily without any manual intervention. 🚀
