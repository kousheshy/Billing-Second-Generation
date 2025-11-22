# SMS Messaging - Quick Start Guide

**Version:** 1.8.0

---

## 🚀 Quick Setup (5 Minutes)

### Step 1: Create Database Tables

Run this command from your project directory:

```bash
php create_sms_tables.php
```

You should see:
```
✅ SMS TABLES CREATED SUCCESSFULLY!
```

### Step 2: Get Your Faraz SMS Credentials

1. Go to https://sms.farazsms.com/dashboard
2. Login to your account
3. Navigate to **Developer** section
4. Generate an **API Token**
5. Note your **Sender Number** (e.g., +983000505)

### Step 3: Configure in Dashboard

1. Login to ShowBox Billing Panel
2. Go to **Messaging** tab
3. Click **SMS Messages**
4. Fill in:
   - **API Token**: [paste your token]
   - **Sender Number**: [your number, e.g., +983000505]
   - **Enable Automatic Expiry SMS**: ☑️ ON (if you want automatic reminders)
   - **Days Before Expiry**: 7
   - **Expiry Message Template**:
     ```
     Dear {name}, your ShowBox subscription expires in {days} days on {expiry_date}. Please renew to continue enjoying our service. Contact: 00447736932888
     ```
5. Click **Save SMS Configuration**

### Step 4 (Optional): Set Up Automatic Reminders

If you enabled automatic expiry SMS, add this cron job:

**On Linux Server:**
```bash
crontab -e
```

Add this line (runs daily at 9 AM):
```
0 9 * * * /usr/bin/php /var/www/showbox/cron_send_expiry_sms.php >> /var/log/sms-reminders.log 2>&1
```

**That's it! SMS is ready to use.**

---

## 📱 How to Send SMS

### Send to a Single Number

1. Go to **Messaging** → **SMS Messages**
2. Click **Send to Number**
3. Enter phone number: **+989120000000** (E.164 format)
4. Type your message (max 500 characters)
5. Click **📱 Send SMS**

### Send to Multiple Accounts

1. Go to **Messaging** → **SMS Messages**
2. Click **Send to Accounts**
3. Select accounts from the list
4. Choose a template or type custom message
5. Use variables:
   - `{name}` → Customer name
   - `{mac}` → MAC address
   - `{expiry_date}` → Expiry date
6. Click **📱 Send SMS to Selected Accounts**

---

## 📊 View SMS History

1. Go to **Messaging** → **SMS Messages**
2. Scroll to **SMS History**
3. Select date or click **Today**
4. Use filters to search

---

## ⚙️ Message Variables

Use these in your templates:

| Variable | Replaced With | Example |
|----------|---------------|---------|
| `{name}` | Customer full name | John Smith |
| `{mac}` | Device MAC | 00:1A:79:12:34:56 |
| `{expiry_date}` | Account expiry | 2025-12-31 |
| `{days}` | Days until expiry | 7 |

**Example:**
```
Dear {name}, your subscription expires in {days} days on {expiry_date}.
```

**Becomes:**
```
Dear John Smith, your subscription expires in 7 days on 2025-12-31.
```

---

## 🔧 Troubleshooting

### "SMS failed" error?

1. **Check API Token**:
   - Go to Faraz SMS Dashboard
   - Verify token is valid
   - Re-generate if needed

2. **Check Phone Numbers**:
   - Must be in E.164 format: **+989120000000**
   - Include country code (+98 for Iran)
   - No spaces or dashes

3. **Check API Connection**:
   ```bash
   curl -I https://edge.ippanel.com
   ```

### Automatic reminders not sending?

1. **Check cron job is running**:
   ```bash
   crontab -l
   ```

2. **Test manually**:
   ```bash
   php cron_send_expiry_sms.php
   ```

3. **Check auto-send is enabled** in SMS Configuration

---

## 💰 Cost Estimate

Example costs (check with Faraz SMS for current pricing):
- Local SMS (Iran): ~0.003 USD per SMS
- International: ~0.10 USD per SMS

**For 1000 customers, 1 reminder/month:**
- 1000 × 0.003 = **$3/month**

---

## 📖 Full Documentation

For complete details, see: [SMS_IMPLEMENTATION_GUIDE.md](SMS_IMPLEMENTATION_GUIDE.md)

---

## ✅ Features Checklist

- [x] SMS configuration with API token
- [x] Manual SMS to single number
- [x] Bulk SMS to multiple accounts
- [x] Message templates with variables
- [x] Automatic expiry reminders
- [x] SMS history with filters
- [x] Statistics dashboard
- [x] Character counter
- [x] Phone number validation

---

## 🆘 Support

If you need help:
1. Check [SMS_IMPLEMENTATION_GUIDE.md](SMS_IMPLEMENTATION_GUIDE.md)
2. Review SMS logs in database: `SELECT * FROM _sms_logs ORDER BY sent_at DESC LIMIT 10;`
3. Contact Faraz SMS support for API issues

---

**Happy Messaging! 📱**
