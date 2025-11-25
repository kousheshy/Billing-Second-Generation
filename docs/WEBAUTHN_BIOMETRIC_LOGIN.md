# WebAuthn Biometric Login (Face ID / Touch ID)

**Version:** 1.11.19+
**Date:** November 25, 2025

## Overview

The ShowBox Billing Panel now supports biometric authentication using WebAuthn (Web Authentication API). Users can login using:
- **Face ID** (iOS devices)
- **Touch ID** (Mac, iOS devices)
- **Windows Hello** (Windows devices)

## Features

### 1. Biometric Registration
- Users must first login with username/password
- Navigate to **Settings** tab
- Find **"Face ID / Touch ID Login"** section
- Click **"Enable Face ID / Touch ID"** to register biometric credentials

### 2. Biometric Login
- On the login page, enter username
- If biometric credentials exist, the **"Login with Face ID / Touch ID"** button appears
- Click the button to authenticate with biometric

### 3. PWA Auto-Login
- When opening the PWA app, biometric authentication starts automatically
- No need to click any button - Face ID/Touch ID prompt appears immediately
- If cancelled, user can still login with password

### 4. Multiple Devices
- Users can register biometric on multiple devices
- Each device stores its own credential
- Credentials can be managed in Settings

## Technical Architecture

### Database Schema

```sql
CREATE TABLE IF NOT EXISTS _webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id TEXT NOT NULL,
    public_key TEXT NOT NULL,
    counter INT DEFAULT 0,
    device_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### API Endpoints

#### 1. Register Biometric Credential

**GET** `/api/webauthn_register.php`
- Returns challenge and registration options
- Requires authentication

**POST** `/api/webauthn_register.php`
- Stores the credential after successful registration
- Body: `{ credential_id, public_key, device_name }`

#### 2. Authenticate with Biometric

**GET** `/api/webauthn_authenticate.php?username=<username>`
- Checks if user has biometric credentials
- Returns challenge and allowed credentials

**POST** `/api/webauthn_authenticate.php`
- Verifies credential and creates session
- Body: `{ credential_id, authenticator_data, client_data_json, signature, counter }`

#### 3. Manage Credentials

**GET** `/api/webauthn_manage.php`
- Lists all credentials for logged-in user
- Requires authentication

**DELETE** `/api/webauthn_manage.php`
- Removes a specific credential
- Body: `{ credential_id }`

### File Structure

```
api/
├── webauthn_register.php      # Registration endpoint
├── webauthn_authenticate.php  # Authentication endpoint
└── webauthn_manage.php        # Credential management

index.html                     # Login page with biometric button
dashboard.php                  # Settings with biometric management
dashboard.js                   # WebAuthn JavaScript functions
```

## Security Considerations

### HTTPS Requirement
WebAuthn requires a secure context (HTTPS). The feature will not work on:
- HTTP connections (except localhost)
- Non-secure origins

### Platform Authenticators
The system uses **platform authenticators** only:
- `authenticatorAttachment: 'platform'`
- This ensures only device-bound credentials (Face ID, Touch ID) are used
- No external security keys supported

### Challenge-Based Authentication
- Server generates random 32-byte challenge
- Challenge is stored in session
- Challenge must be signed by the authenticator
- Single-use challenge prevents replay attacks

## User Flow

### Registration Flow
```
1. User logs in with username/password
2. User navigates to Settings → Biometric Login
3. User clicks "Enable Face ID / Touch ID"
4. Browser prompts for biometric (Face ID/Touch ID)
5. On success, credential is stored in database
6. Username is saved to localStorage for auto-login
```

### Login Flow
```
1. User opens login page
2. If saved username exists, it's pre-filled
3. Biometric button appears if credentials exist
4. User clicks button (or auto-started in PWA)
5. Browser prompts for biometric
6. On success, session is created
7. User is redirected to dashboard
```

### PWA Auto-Login Flow
```
1. User opens PWA app
2. System detects PWA mode (standalone)
3. Saved username is loaded from localStorage
4. System checks if biometric credentials exist
5. If yes, biometric prompt starts automatically
6. On success, user is logged in without any clicks
```

## Configuration

### Relying Party (RP) Configuration
```javascript
rp: {
    name: 'ShowBox Billing',
    id: window.location.hostname  // e.g., 'billing.apamehnet.com'
}
```

### User Verification
```javascript
userVerification: 'required'  // Always require biometric
```

### Supported Algorithms
```javascript
pubKeyCredParams: [
    { type: 'public-key', alg: -7 },   // ES256 (ECDSA)
    { type: 'public-key', alg: -257 }  // RS256 (RSA)
]
```

## Troubleshooting

### "Your device does not support biometric authentication"
- **Cause:** WebAuthn not available or device lacks biometric hardware
- **Solution:** Use a device with Face ID/Touch ID, ensure HTTPS

### Biometric not prompting
- **Cause:** Credential not registered or username mismatch
- **Solution:** Re-register biometric in Settings

### PWA not auto-starting biometric
- **Cause:** Not in standalone mode or no saved username
- **Solution:** Install as PWA, register biometric after login

## Browser Support

| Browser | Face ID | Touch ID | Windows Hello |
|---------|---------|----------|---------------|
| Safari (iOS) | ✅ | ✅ | N/A |
| Safari (Mac) | N/A | ✅ | N/A |
| Chrome | ✅ | ✅ | ✅ |
| Firefox | ✅ | ✅ | ✅ |
| Edge | ✅ | ✅ | ✅ |

## Related Files

- [index.html](../index.html) - Login page with biometric UI
- [dashboard.php](../dashboard.php) - Settings with biometric management
- [dashboard.js](../dashboard.js) - WebAuthn JavaScript (lines 6106-6523)
- [api/webauthn_register.php](../api/webauthn_register.php)
- [api/webauthn_authenticate.php](../api/webauthn_authenticate.php)
- [api/webauthn_manage.php](../api/webauthn_manage.php)
