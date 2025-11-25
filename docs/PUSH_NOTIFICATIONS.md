# Push Notifications Feature

## Version: 1.11.46
## Date: 2025-11-25

---

## Overview

Push notifications allow administrators and reseller admins to receive real-time alerts when resellers create new accounts or renew existing accounts. This feature works on iOS PWA, Android, and desktop browsers.

---

## Features

### Notification Types

1. **New Account Created**
   - Triggered when a reseller (not super admin) creates a new account
   - Shows: Reseller name, account holder's full name, plan name
   - Example: `"John Reseller created account: Ali Mohammadi (1 Month Plan)"`

2. **Account Renewed**
   - Triggered when a reseller (not super admin) renews an account
   - Shows: Reseller name, account holder's full name, plan name, new expiry date
   - Example: `"John Reseller renewed: Ali Mohammadi (3 Month Plan) until 2025/12/25"`

### Who Receives Notifications

- **Super Admins** (super_user = 1)
- **Reseller Admins** (permissions contain is_reseller_admin)

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

1. **api/push_helper.php**
   - Main push notification helper using minishlink/web-push library
   - Functions: `sendPushNotification()`, `notifyAdmins()`, `notifyNewAccount()`, `notifyAccountRenewal()`

2. **api/push_subscribe.php**
   - Handles push subscription management
   - Methods: GET (check status), POST (subscribe), DELETE (unsubscribe)

3. **api/get_vapid_key.php**
   - Returns VAPID public key for client-side subscription

4. **scripts/migration_add_push_subscriptions.sql**
   - Database migration for `_push_subscriptions` table

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

## Version History

| Version | Date | Changes |
|---------|------|---------|
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

- [ ] Notification preferences (choose which events to receive)
- [ ] Sound customization
- [ ] Notification grouping for multiple events
- [ ] Rich notifications with images
- [ ] Action buttons (View Account, Mark as Read)
