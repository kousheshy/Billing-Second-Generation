# Auto-Logout / Session Timeout Feature

**Version:** 1.11.20+
**Date:** November 25, 2025

## Overview

The ShowBox Billing Panel includes an automatic logout feature that logs users out after a period of inactivity. This enhances security by ensuring unattended sessions are terminated.

## Features

### 1. Configurable Timeout
- Super admin can configure the timeout duration
- Options: Disabled, 1-60 minutes
- Default: 5 minutes

### 2. Server-Side Tracking
- Activity is tracked on the server using PHP sessions
- Works across page refreshes
- Persists even if browser tab is closed and reopened

### 3. Activity Detection
- Mouse movements
- Mouse clicks
- Keyboard input
- Scrolling
- Touch events

### 4. Session Expired Message
- When session expires, user is redirected to login page
- Clear message: "Your session has expired due to inactivity"

## Technical Architecture

### Database Schema

```sql
CREATE TABLE IF NOT EXISTS _app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default auto_logout_timeout setting
INSERT IGNORE INTO _app_settings (setting_key, setting_value)
VALUES ('auto_logout_timeout', '5');
```

### Session Variables

```php
$_SESSION['last_activity']  // Unix timestamp of last activity
$_SESSION['login']          // Login status (1 = logged in)
$_SESSION['username']       // Logged in username
```

### API Endpoints

#### 1. Get/Set Auto-Logout Settings

**GET** `/api/auto_logout_settings.php`
- Returns current timeout setting
- No authentication required (needed by login timer)

Response:
```json
{
    "error": 0,
    "auto_logout_timeout": 5,
    "timeout_seconds": 300
}
```

**POST** `/api/auto_logout_settings.php`
- Updates timeout setting
- Requires super admin authentication

Request:
```json
{
    "timeout": 5
}
```

#### 2. Session Heartbeat

**GET** `/api/session_heartbeat.php`
- Checks if session is valid
- Updates last_activity timestamp

**POST** `/api/session_heartbeat.php`
- Updates last_activity timestamp (heartbeat ping)
- Called by JavaScript on user activity

Response:
```json
{
    "error": 0,
    "expired": false,
    "timeout_minutes": 5,
    "time_remaining_seconds": 280,
    "last_activity": 1732561234
}
```

### File Structure

```
api/
├── auto_logout_settings.php   # Get/set timeout setting
└── session_heartbeat.php      # Heartbeat and session check

dashboard.php                  # PHP session timeout check
dashboard.js                   # JavaScript activity tracking
index.html                     # Login page with expired message
```

## How It Works

### Server-Side Check (PHP)

```php
// In dashboard.php (at the top)
session_start();

// Check if session has expired
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    if ($inactive_time >= $timeout_seconds) {
        // Session expired - destroy and redirect
        $_SESSION = array();
        session_destroy();
        header('Location: index.html?expired=1');
        exit();
    }
}

// Update last activity on page load
$_SESSION['last_activity'] = time();
```

### Client-Side Tracking (JavaScript)

```javascript
// Activity events to track
const activityEvents = [
    'mousedown', 'mousemove', 'keydown',
    'scroll', 'touchstart', 'click', 'wheel'
];

// Throttled activity handler (every 30 seconds)
const handleActivity = () => {
    const now = Date.now();
    if (now - lastActivity > 30000) {
        lastActivity = now;
        sendHeartbeat();  // Update server
    }
};

// Add event listeners
activityEvents.forEach(event => {
    document.addEventListener(event, handleActivity, { passive: true });
});
```

### Timeout Flow

```
1. User logs in
   └── dashboard.php sets $_SESSION['last_activity'] = time()

2. User is active on dashboard
   └── JavaScript detects activity every 30 seconds
   └── Sends heartbeat to server
   └── Server updates $_SESSION['last_activity']

3. User becomes inactive
   └── No activity events detected
   └── No heartbeats sent
   └── $_SESSION['last_activity'] stays unchanged

4. User refreshes page after timeout
   └── dashboard.php checks: time() - last_activity >= timeout
   └── Session expired → destroy session
   └── Redirect to index.html?expired=1

5. Login page shows message
   └── "Your session has expired due to inactivity"
```

## Configuration (Super Admin Only)

### Accessing Settings

1. Login as super admin
2. Navigate to **Settings** tab
3. Find **"Auto-Logout Settings"** section

### Available Options

| Value | Description |
|-------|-------------|
| Disabled | Auto-logout is turned off |
| 1 minute | Very short timeout (for testing) |
| 2 minutes | Short timeout |
| 3 minutes | Short timeout |
| 5 minutes | **Default** - Recommended for most use cases |
| 10 minutes | Medium timeout |
| 15 minutes | Medium timeout |
| 30 minutes | Long timeout |
| 60 minutes | Very long timeout |

### Saving Settings

1. Select desired timeout from dropdown
2. Click **"Save"** button
3. Success message appears: "Auto-logout set to X minutes"
4. Setting takes effect immediately for all users

## Security Considerations

### Server-Side Validation
- Timeout check happens on server (PHP), not just client (JavaScript)
- Even if JavaScript is disabled, session will expire on next page load
- Cannot be bypassed by manipulating client code

### Session Destruction
- On timeout, session is completely destroyed
- `$_SESSION = array()` clears all session data
- `session_destroy()` destroys the session file
- User must re-authenticate

### Throttled Heartbeat
- Heartbeat only sent every 30 seconds to reduce server load
- Activity is still tracked locally for client-side timer
- Balance between responsiveness and performance

## Troubleshooting

### Session not expiring on refresh
- **Cause:** `>` instead of `>=` comparison (fixed in v1.11.22)
- **Solution:** Ensure using latest version

### Session expiring too quickly
- **Cause:** Multiple tabs may be sending heartbeats
- **Solution:** Each tab tracks independently, this is expected

### "Session expired" showing unexpectedly
- **Cause:** Server time may differ from client
- **Solution:** Ensure server time is correct

## Testing the Feature

1. Login to dashboard as super admin
2. Go to Settings → Auto-Logout Settings
3. Set timeout to **1 minute**
4. Click Save
5. **Do not move mouse or touch keyboard** for 1 minute
6. After 1 minute, **refresh the page**
7. You should be redirected to login page with "session expired" message

## Related Files

- [dashboard.php](../dashboard.php) - PHP session check (lines 1-52)
- [dashboard.js](../dashboard.js) - JavaScript auto-logout (lines 6527-6740)
- [index.html](../index.html) - Expired message display (lines 731-737)
- [api/auto_logout_settings.php](../api/auto_logout_settings.php)
- [api/session_heartbeat.php](../api/session_heartbeat.php)

## Version History

| Version | Changes |
|---------|---------|
| 1.11.20 | Initial implementation |
| 1.11.21 | Added server-side session tracking |
| 1.11.22 | Fixed `>` to `>=` comparison bug |
