# Push Notifications Feature

## Version: 1.11.49
## Date: 2025-11-26

---

## Overview

Push notifications provide real-time alerts for account operations in the ShowBox Billing Panel. This feature works on iOS PWA (16.4+), Android, and desktop browsers.

**Three notification types:**
1. **Activity Notifications** - When ANY user creates/renews accounts (sent to admins)
2. **Expiry Notifications** - When accounts expire (sent to resellers, NOT admins)

---

## Features

### Notification Types

1. **📱 New Account Created** (v1.11.47+)
   - Triggered when ANY user (admin, reseller admin, or reseller) creates an account
   - Shows: Actor name, account holder's full name, plan name
   - Example: `"📱 New Account Created: John Reseller added: Ali Mohammadi (1 Month Plan)"`
   - **Recipients**: Super Admin + Reseller Admins

2. **🔄 Account Renewed** (v1.11.47+)
   - Triggered when ANY user renews an account
   - Shows: Actor name, account holder's full name, plan name, new expiry date
   - Example: `"🔄 Account Renewed: John Reseller renewed: Ali Mohammadi (3 Month Plan) until 2025/12/25"`
   - **Recipients**: Super Admin + Reseller Admins

3. **⚠️ Account Expired** (v1.11.48+)
   - Triggered automatically via cron when accounts expire
   - Shows: Account holder's full name, expiry date/time
   - Example: `"⚠️ Account Expired: Ali Mohammadi has expired (2025-11-26 14:30)"`
   - **Recipients**: Account owner (reseller) + Reseller Admins
   - **NOT sent to**: Super Admin (intentional - admin doesn't need individual expiry alerts)

### Who Receives Which Notifications

| Notification Type | Super Admin | Reseller Admin | Reseller (Owner) |
|-------------------|-------------|----------------|------------------|
| New Account | ✅ | ✅ | ❌ |
| Account Renewed | ✅ | ✅ | ❌ |
| Account Expired | ❌ | ✅ | ✅ (own accounts only) |

### Who Can Enable Push Notifications (v1.11.48+)

- **All users** can now enable push notifications
- Previously only admins could enable; now resellers can too
- Resellers only receive expiry notifications for their own accounts

### Platform Support

| Platform | Support Status |
|----------|---------------|
| iOS PWA (16.4+) | ✅ Full Support |
| Android Chrome | ✅ Full Support |
| Desktop Chrome | ✅ Full Support |
| Desktop Firefox | ✅ Full Support |
| Desktop Safari | ✅ Full Support |
| iOS Safari (browser) | ❌ Not Supported |

**Important:** iOS requires the PWA to be installed on the home screen for push notifications to work.

---

## Technical Implementation

### Files Created/Modified

#### New Files

1. **api/push_helper.php** (v1.11.48)
   - Main push notification helper using minishlink/web-push library
   - Functions: `sendPushNotification()`, `notifyAdmins()`, `notifyNewAccount()`, `notifyAccountRenewal()`, `notifyAccountExpired()`

2. **api/push_subscribe.php**
   - Handles push subscription management
   - Methods: GET (check status), POST (subscribe), DELETE (unsubscribe)

3. **api/get_vapid_key.php**
   - Returns VAPID public key for client-side subscription

4. **scripts/migration_add_push_subscriptions.sql**
   - Database migration for `_push_subscriptions` table

5. **api/cron_check_expired.php** (v1.11.48)
   - Cron job script for automatic expiry notifications
   - Runs every 10 minutes via crontab
   - Creates `_push_expiry_tracking` table for duplicate prevention
   - Only checks accounts expired within last 24 hours

#### Modified Files

1. **api/add_account.php**
   - Added push notification call when reseller creates account (line 684)
   - Uses account holder's full name (not username) in notification

2. **api/edit_account.php**
   - Added push notification call when reseller renews account (lines 335-336)
   - Uses account holder's full name with fallback to username
   - Disabled HTML error display to prevent JSON parsing issues

3. **api/login.php**
   - Added `$_SESSION['user_id']` for push subscription storage

4. **dashboard.php**
   - Added push notification settings UI in Settings tab
   - Added mobile push notification button and modal

5. **dashboard.js**
   - Added `initPushNotifications()` function
   - Added `subscribePush()`, `unsubscribePush()`, `togglePushNotifications()`
   - Added mobile push settings functions
   - Global variables: `pushSubscription`, `vapidPublicKey`

6. **service-worker.js**
   - Added `push` event listener for displaying notifications
   - Added `notificationclick` event listener for handling taps
   - Updated cache version to v1.11.46

---

## Database Schema

### Table: _push_subscriptions

```sql
CREATE TABLE IF NOT EXISTS `_push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_endpoint` (`endpoint`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: _push_expiry_tracking (v1.11.48)

Tracks sent expiry notifications to prevent duplicates:

```sql
CREATE TABLE IF NOT EXISTS `_push_expiry_tracking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `notified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_account_expiry` (`account_id`, `expiry_date`),
  INDEX `idx_notified_at` (`notified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:**
- Prevents duplicate notifications for the same account expiry
- Auto-cleaned: Records older than 30 days are automatically deleted
- Unique constraint ensures one notification per account per expiry date

---

## VAPID Keys

VAPID (Voluntary Application Server Identification) keys are used for authentication with push services.

**Current Keys (v1.11.46):**
- Public Key: `BI8Gdm9PK3LeO2mvhV9yt5NzIBFhSrlKRbfHbaDFfvMqJGmI0T0R-huUK7yeo6aPoasqBnu7SLjNUjqb4J_j5L0`
- Subject: `https://billing.apamehnet.com`

**Important:** If you change VAPID keys, all existing subscriptions become invalid and users must re-enable notifications.

---

## Dependencies

### PHP Library

The feature uses **minishlink/web-push** library for proper Web Push implementation.

**Installation:**
```bash
cd /var/www/showbox
composer require minishlink/web-push
```

**Requirements:**
- PHP 8.0+
- php-gmp extension
- php-curl extension
- OpenSSL 3.0+

---

## User Guide

### Enabling Push Notifications

1. **Desktop Browser:**
   - Go to Settings tab
   - Find "Push Notifications" section
   - Click "Enable Notifications"
   - Allow notifications when browser prompts

2. **iOS PWA:**
   - Install PWA to home screen first
   - Go to Settings (gear icon)
   - Tap "Push Notifications"
   - Tap "Enable Notifications"
   - Allow notifications when prompted

3. **Android:**
   - Go to Settings tab
   - Click "Enable Notifications"
   - Allow when prompted

### Disabling Push Notifications

Follow same steps but click "Disable Notifications" button.

---

## Troubleshooting

### Common Issues

1. **"Push notifications not supported"**
   - iOS: Must use PWA installed on home screen
   - Browser too old - update browser

2. **"Notifications blocked"**
   - Go to browser/system settings
   - Find site notifications
   - Change from "Block" to "Allow"

3. **Notifications not received (iOS)**
   - Ensure iOS 16.4 or later
   - Ensure PWA is installed on home screen
   - Check Focus mode isn't blocking notifications

4. **BadJwtToken error**
   - VAPID subject must be valid URL (not mailto: with .local domain)
   - Regenerate VAPID keys if issue persists

---

## API Reference

### GET /api/get_vapid_key.php

Returns VAPID public key for subscription.

**Response:**
```json
{
  "error": 0,
  "publicKey": "BI8Gdm9PK3LeO2mvhV9yt5NzIBFhSrlKRbfHbaDFfvMqJGmI0T0R-huUK7yeo6aPoasqBnu7SLjNUjqb4J_j5L0"
}
```

### GET /api/push_subscribe.php

Check subscription status.

**Response:**
```json
{
  "error": 0,
  "subscribed": true,
  "count": 1
}
```

### POST /api/push_subscribe.php

Subscribe to push notifications.

**Request Body:**
```json
{
  "endpoint": "https://web.push.apple.com/...",
  "keys": {
    "p256dh": "...",
    "auth": "..."
  }
}
```

**Response:**
```json
{
  "error": 0,
  "message": "Subscribed to notifications"
}
```

### DELETE /api/push_subscribe.php

Unsubscribe from push notifications.

**Request Body (optional):**
```json
{
  "endpoint": "https://web.push.apple.com/..."
}
```

If no endpoint provided, removes all subscriptions for user.

---

## Cron Job Setup (v1.11.48)

The expiry notification cron job automatically checks for expired accounts and sends notifications.

### Installation

```bash
# Edit root crontab
sudo crontab -e

# Add this line (runs every 10 minutes)
*/10 * * * * /usr/bin/php /var/www/showbox/api/cron_check_expired.php >> /var/log/showbox_expiry.log 2>&1
```

### Configuration

The cron script has these settings (in `cron_check_expired.php`):

| Setting | Value | Description |
|---------|-------|-------------|
| `EXPIRY_CHECK_WINDOW_HOURS` | 24 | Only check accounts expired in last 24 hours |
| Cron interval | 10 minutes | Frequency of checks |
| Auto-cleanup | 30 days | Old tracking records automatically deleted |

### How It Works

1. **Query expired accounts** - Finds accounts where `end_date < NOW()` and expired within the last 24 hours
2. **Check tracking table** - Skips accounts that have already been notified for this expiry date
3. **Send notifications** - Sends push notification to reseller owner and all reseller admins
4. **Record notification** - Marks the account/expiry combination as notified
5. **Cleanup** - Removes tracking records older than 30 days

### Monitoring

View cron output:
```bash
tail -f /var/log/showbox_expiry.log
```

Example output:
```
2025-11-26 14:30:00 - Expiry check completed. Sent: 3, Skipped: 15, Failed: 0
```

### Performance

- **Execution time**: ~50-100ms (with no expired accounts)
- **Database queries**: 2-3 indexed queries per run
- **Server impact**: Minimal (designed for every 10 minutes)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.11.49 | 2025-11-26 | Version bump for cache refresh |
| 1.11.48 | 2025-11-26 | Added expiry notifications, cron job, tracking table, all users can enable |
| 1.11.47 | 2025-11-26 | Expanded to notify for ALL account operations, fixed permission query |
| 1.11.46 | 2025-11-25 | Fixed VAPID subject for Apple push service |
| 1.11.45 | 2025-11-25 | Installed minishlink/web-push library, regenerated VAPID keys |
| 1.11.44 | 2025-11-25 | Fixed session variable bug, added !isSuperUser check for card selection |
| 1.11.43 | 2025-11-25 | Added mobile push notification UI, fixed variable declaration order |
| 1.11.41 | 2025-11-25 | Initial push notification implementation |

---

## Security Considerations

1. **Subscription Storage:** Subscriptions are stored server-side and tied to user_id
2. **VAPID Authentication:** All push requests are signed with VAPID private key
3. **HTTPS Required:** Push notifications only work over HTTPS
4. **User Consent:** Browser requires explicit user permission

---

## Future Enhancements

- [x] ~~Expiry notifications for resellers~~ (Completed v1.11.48)
- [x] ~~All users can enable push notifications~~ (Completed v1.11.48)
- [ ] Notification preferences (choose which events to receive)
- [ ] Sound customization
- [ ] Notification grouping for multiple events
- [ ] Rich notifications with images
- [ ] Action buttons (View Account, Mark as Read)
