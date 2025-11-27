# Auto-Disable Expired Accounts

## Overview

The Auto-Disable Expired Accounts feature automatically disables accounts that have passed their expiration date. This ensures that expired subscriptions are properly deactivated in both the billing system and the Stalker Portal server.

**Version:** 1.17.1
**Added:** November 28, 2025
**Status:** Production Release

---

## How It Works

1. **Cron job runs periodically** (recommended: every hour)
2. **Finds expired accounts** where:
   - `end_date < today` (using Asia/Tehran timezone)
   - `status = 1` (still enabled)
3. **For each expired account:**
   - Disables on Stalker Portal server via API
   - Updates billing database (`status = 0`)
   - Logs the action for audit trail

---

## File Location

```
cron/cron_disable_expired_accounts.php
```

---

## Features

| Feature | Description |
|---------|-------------|
| **Timezone Aware** | Uses PHP's Asia/Tehran timezone (from config.php) |
| **Dual Server Support** | Disables on both servers if dual-server mode enabled |
| **Audit Logging** | Logs all disabled accounts to `_audit_log` table |
| **Dry Run Mode** | Set `DRY_RUN = true` to test without making changes |
| **Error Handling** | Continues processing if one account fails |
| **Detailed Logging** | Outputs progress to stdout and error_log |

---

## Setup

### 1. Deploy the Cron File

The file is located at:
```
/var/www/showbox/cron/cron_disable_expired_accounts.php
```

### 2. Add to Crontab

```bash
# Edit crontab
crontab -e

# Add this line (runs every hour at minute 0)
0 * * * * php /var/www/showbox/cron/cron_disable_expired_accounts.php >> /var/log/showbox-disable-expired.log 2>&1
```

### 3. Verify Cron is Scheduled

```bash
crontab -l
```

---

## Cron Schedule Options

| Schedule | Cron Expression | Description |
|----------|-----------------|-------------|
| Every hour | `0 * * * *` | **Recommended** - Balances timeliness and server load |
| Every 30 minutes | `0,30 * * * *` | More frequent checking |
| Daily at midnight | `0 0 * * *` | Once per day |
| Every 6 hours | `0 */6 * * *` | Four times daily |

---

## Manual Execution

### Run from Command Line
```bash
php /var/www/showbox/cron/cron_disable_expired_accounts.php
```

### Run via Web (Testing Only)
```
https://yourdomain.com/cron/cron_disable_expired_accounts.php?run=test
```

**Note:** Web access is disabled by default for security. Only CLI execution is allowed in production.

---

## Example Output

```
[2025-11-28 01:17:49] === Starting Auto-Disable Expired Accounts ===
[2025-11-28 01:17:49] Server timezone: Asia/Tehran | Today's date: 2025-11-28 01:17:49
[2025-11-28 01:17:49] Found 2 expired accounts with status=enabled
[2025-11-28 01:17:49] Processing: Majid Khansari (MAC: 00:1A:79:99:18:BB, Expired: 2025-11-27)
[2025-11-28 01:17:49]   SUCCESS - Disabled: Majid Khansari
[2025-11-28 01:17:49] Processing: Kambiz Koosheshi (MAC: 00:1A:79:32:13:12, Expired: 2025-11-27)
[2025-11-28 01:17:49]   SUCCESS - Disabled: Kambiz Koosheshi
[2025-11-28 01:17:49] === Completed ===
[2025-11-28 01:17:49] Total: 2 | Success: 2 | Failed: 0
```

---

## Configuration

### Dry Run Mode

To test without making actual changes:

```php
// In cron_disable_expired_accounts.php
define('DRY_RUN', true);  // Change to true for testing
```

### Timezone

The script uses the timezone from `config.php`:

```php
// In config.php
date_default_timezone_set('Asia/Tehran');
```

---

## Database Query

The script uses this query to find expired accounts:

```sql
SELECT id, username, full_name, mac, end_date, status, reseller
FROM _accounts
WHERE DATE(end_date) < :today
  AND status = 1
ORDER BY end_date ASC
```

Where `:today` is PHP's `date('Y-m-d')` using Asia/Tehran timezone.

---

## Stalker API Call

To disable an account on Stalker Portal:

```
PUT /stalker_portal/api/accounts/{MAC}
Data: status=0
Auth: Basic (WEBSERVICE_USERNAME:WEBSERVICE_PASSWORD)
```

---

## Audit Log Entry

When an account is auto-disabled, an entry is added to `_audit_log`:

| Field | Value |
|-------|-------|
| `action` | `auto_disable` |
| `entity_type` | `account` |
| `entity_id` | MAC address |
| `changes` | `{"old":{"status":1},"new":{"status":0},"reason":"Account expired - auto-disabled by cron"}` |
| `description` | `Auto-disabled expired account: {name} ({mac})` |
| `performed_by` | `system_cron` |

---

## Troubleshooting

### No accounts being disabled

1. **Check timezone:** Ensure MySQL and PHP use the same timezone
   ```sql
   SELECT @@global.time_zone, NOW();
   ```

2. **Check for expired accounts:**
   ```sql
   SELECT username, full_name, end_date, status
   FROM _accounts
   WHERE DATE(end_date) < CURDATE() AND status = 1;
   ```

3. **Verify API credentials:** Test Stalker Portal connection

### Stalker API errors

1. **Check network:** Verify server can reach Stalker Portal
2. **Check credentials:** Verify `WEBSERVICE_USERNAME` and `WEBSERVICE_PASSWORD` in config.php
3. **Check MAC format:** Ensure MAC addresses are in correct format (XX:XX:XX:XX:XX:XX)

### Database connection errors

1. **Check credentials:** Verify database credentials in config.php
2. **Check MySQL service:** `systemctl status mysql`

---

## Monitoring

### View Logs

```bash
# Follow live logs
tail -f /var/log/showbox-disable-expired.log

# View last 50 lines
tail -50 /var/log/showbox-disable-expired.log

# Search for errors
grep "ERROR" /var/log/showbox-disable-expired.log

# Search for successes
grep "SUCCESS" /var/log/showbox-disable-expired.log
```

### Database Check

```sql
-- Recently disabled accounts (last 24 hours)
SELECT username, full_name, end_date, status
FROM _accounts
WHERE status = 0
  AND end_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
ORDER BY end_date DESC;

-- Audit log entries for auto-disable
SELECT * FROM _audit_log
WHERE action = 'auto_disable'
ORDER BY created_at DESC
LIMIT 20;
```

---

## Security Considerations

1. **CLI-only execution:** Script blocks web access by default
2. **Database credentials:** Uses existing config.php (keep secure)
3. **API credentials:** Uses existing Stalker credentials
4. **Logging:** Sensitive data (MAC, names) only in server logs

---

## Related Documentation

- [CRON_SETUP_INSTRUCTIONS.md](CRON_SETUP_INSTRUCTIONS.md) - General cron setup guide
- [PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md) - Push notification for expired accounts
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - API endpoint reference

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.17.1 | 2025-11-28 | Initial implementation |

---

## Summary

The Auto-Disable feature ensures:
- Expired accounts are disabled automatically
- Both billing system and Stalker Portal stay in sync
- Audit trail maintained for compliance
- No manual intervention required

**Recommended:** Run hourly via cron for optimal balance of timeliness and server load.
