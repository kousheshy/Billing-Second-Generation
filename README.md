# ShowBox Billing Panel

A comprehensive IPTV billing and account management system integrated with Stalker Portal. This web-based application provides administrators and resellers with powerful tools to manage subscriptions, track accounts, and monitor business metrics.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)
![Status](https://img.shields.io/badge/status-production-green.svg)

## Table of Contents

- [Features](#features)
- [System Status](#system-status)
- [Quick Start](#quick-start)
- [Technology Stack](#technology-stack)
- [Documentation](#documentation)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)

## System Status

✅ **FULLY OPERATIONAL**

Your IPTV/STB billing management system is running with complete UI integrated with backend APIs.

**Current Setup:**
- ✅ PHP Server: `http://localhost:8000`
- ✅ MySQL Database: `showboxt_panel`
- ✅ Frontend UI: Full dashboard with all features
- ✅ Backend APIs: All integrated and working

## Quick Start

### Access the System
1. Open your browser: `http://localhost:8000/index.html`
2. Login credentials:
   - Username: `admin`
   - Password: `admin` (⚠️ Change immediately!)

### Technology Stack
- **Backend**: PHP 7.4+, MySQL 5.7+
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **APIs**: Stalker Portal REST API Integration
- **Security**: Session-based authentication, PDO prepared statements

## Features

### Core Functionality
- **Account Management**: Create, edit, delete, and manage IPTV accounts
- **Stalker Portal Sync**: Real-time one-click synchronization with Stalker Portal API
- **Multi-Server Support**: Manage accounts across two separate servers
- **Reseller System**: Multi-tier reseller management with balance tracking
- **Tariff Plans**: Flexible subscription plans with automated expiration tracking
- **Transaction History**: Complete financial transaction logging
- **Advanced Analytics**: Comprehensive reports and business intelligence

### Dashboard Features
- Real-time account statistics with visual cards
- Expiring accounts alerts (next 2 weeks)
- Expired & not renewed tracking with custom date ranges
- Active/Inactive account monitoring
- Dynamic date range reports (7, 14, 30, 60, 90, 180, 365 days + custom)
- Search and pagination (25 accounts per page)
- Dark/Light theme toggle for comfortable viewing

### Reports & Analytics
- Total accounts overview
- Active vs expired accounts breakdown
- Expiring soon warnings (configurable periods)
- Custom date range filtering
- **Expired & Not Renewed** tracking with sophisticated logic:
  - Date-based expiration (ignores status field)
  - Status field is for admin control only
  - Accounts are "not renewed" if `end_date` remains in the past
  - Renewal detected when `end_date` is updated to a future date
- Unlimited plans monitoring
- Expired last month statistics

### User Management
- Admin and reseller roles
- Balance management system
- Secure session-based authentication
- Password change functionality
- User-specific permissions (admin can delete, resellers cannot)

## Database Schema

### Tables Created
- `_users` - Resellers and admins
- `_accounts` - Customer accounts
- `_plans` - Subscription plans
- `_currencies` - Currency types
- `_transactions` - Financial transactions

### Default Admin Account
- Username: `admin`
- Password: `admin` (⚠️ Change this immediately!)
- Balance: £1000
- Super User: Yes

## File Structure

```
Current Billing Shahrokh/
├── index.html           # Login page
├── dashboard.html       # Main dashboard
├── dashboard.css        # Styling
├── dashboard.js         # Frontend logic & API integration
├── config.php           # Configuration
├── login.php            # Authentication
├── logout.php           # Session destroy
├── api.php              # API helper functions
│
├── Account Management APIs:
├── add_account.php
├── update_account.php
├── remove_account.php
├── get_accounts.php
│
├── Reseller Management APIs:
├── add_reseller.php
├── update_reseller.php
├── remove_reseller.php
├── get_resellers.php
│
├── Plan Management APIs:
├── add_plan.php
├── remove_plan.php
├── get_plans.php
│
├── Other APIs:
├── get_transactions.php
├── get_user_info.php
├── update_password.php
├── change_status.php
├── send_message.php
├── send_event.php
└── new_transaction.php
```

## How to Use

### Adding a New Account
1. Go to **Accounts** tab
2. Click **+ Add Account**
3. Fill in:
   - Username (required)
   - Password (required)
   - MAC Address (required)
   - Plan (optional)
   - Other details
4. Click **Create Account**
5. Account will be created on both servers (if configured)

### Adding a Reseller
1. Go to **Resellers** tab
2. Click **+ Add Reseller**
3. Fill in:
   - Username, Password, Name
   - Email, Max Users
   - Currency, Theme
4. Click **Create Reseller**

### Adding a Plan
1. Go to **Plans** tab
2. Click **+ Add Plan**
3. Fill in:
   - Plan ID
   - Currency (GBP/USD/EUR)
   - Price
   - Duration (days)
4. Click **Create Plan**

### Changing Password
1. Go to **Settings** tab
2. Enter:
   - Old Password
   - New Password
   - Confirm New Password
3. Click **Update Password**

## API Integration

The system integrates with external IPTV middleware (Stalker Portal) for:
- STB account creation
- Account updates
- Device management
- Event sending (reboot, reload, etc.)
- Messaging to devices

### Configuring External APIs
Edit `config.php`:
```php
$SERVER_1_ADDRESS = "http://your-server-1.com";
$SERVER_2_ADDRESS = "http://your-server-2.com";
$WEBSERVICE_USERNAME = "your-api-username";
$WEBSERVICE_PASSWORD = "your-api-password";
$WEBSERVICE_BASE_URL = "http://your-api.com/api/";
$WEBSERVICE_2_BASE_URL = "http://your-api2.com/api/";
```

## Database Configuration

Current settings in `config.php`:
```php
$ub_main_db = "showboxt_panel";
$ub_db_host = "localhost";
$ub_db_username = "root";
$ub_db_password = "";
```

## Server Management

### Start the System
```bash
# Start MySQL
brew services start mysql

# Start PHP Server
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"
php -S localhost:8000
```

### Stop the System
```bash
# Stop PHP Server (Ctrl+C in terminal)

# Stop MySQL
brew services stop mysql
```

### Check Status
```bash
# Check MySQL
brew services list | grep mysql

# Check PHP processes
ps aux | grep php
```

## Security Notes

⚠️ **IMPORTANT SECURITY TASKS:**

1. **Change default admin password** immediately
2. **Set strong database password**
3. **Enable SSL/HTTPS** in production
4. **Update password hashing** from MD5 to bcrypt
5. **Protect config.php** from web access
6. **Enable SSL verification** in cURL requests
7. **Add rate limiting** to prevent abuse
8. **Implement CSRF protection**
9. **Add input validation** and sanitization
10. **Regular backups** of database

## Troubleshooting

### Can't Login
- Check MySQL is running: `brew services list`
- Verify database exists: `mysql -u root -e "SHOW DATABASES;"`
- Check credentials in config.php

### Database Connection Error
- Start MySQL: `brew services start mysql`
- Verify username/password in config.php
- Check database name is correct

### APIs Not Working
- Check PHP server is running
- View server logs in terminal
- Check browser console for errors
- Verify API files exist and have correct permissions

### Plans Not Showing
- Make sure you've added plans first
- Check Plans tab to verify they exist
- Refresh the page

## Development

### Adding New Features
1. Create backend PHP API file
2. Add route in dashboard.js
3. Add UI elements in dashboard.html
4. Style in dashboard.css

### Database Changes
```bash
mysql -u root showboxt_panel
# Run your SQL commands
```

## Documentation

Comprehensive documentation available:
- [MVP.md](MVP.md) - Product roadmap and feature priorities
- [INSTALLATION.md](INSTALLATION.md) - Detailed setup instructions
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - API endpoints reference
- [ARCHITECTURE.md](ARCHITECTURE.md) - System design and data flow
- [CHANGELOG.md](CHANGELOG.md) - Version history and updates

## Support

### Contact Information
- **24/7 Support**: WhatsApp +447736932888
- **Instagram**: @ShowBoxAdmin
- **Panel Name**: ShowBox

### Troubleshooting
- Check `server.log` for PHP errors
- Check browser console for JavaScript errors
- Review server terminal for request logs
- Verify MySQL is running: `brew services list`
- Check database connection in `config.php`

## Security Considerations

⚠️ **IMPORTANT SECURITY TASKS:**
1. Change default admin password immediately
2. Set strong database password
3. Enable SSL/HTTPS in production
4. Update password hashing from MD5 to bcrypt
5. Protect `config.php` from web access
6. Enable SSL verification in cURL requests
7. Add rate limiting to prevent abuse
8. Implement CSRF protection
9. Add input validation and sanitization
10. Regular backups of database

## Performance Optimization

- Client-side filtering reduces server load
- Pagination for large datasets (25 accounts per page)
- Efficient database indexing
- Minimal API calls with caching
- Optimized CSS and JavaScript

## Browser Compatibility

| Browser | Version | Status |
|---------|---------|--------|
| Chrome  | 90+     | ✅ Full Support |
| Firefox | 88+     | ✅ Full Support |
| Safari  | 14+     | ✅ Full Support |
| Edge    | 90+     | ✅ Full Support |
| Opera   | 76+     | ✅ Full Support |

## License

Proprietary - ShowBox IPTV Billing System. All rights reserved.
Unauthorized copying, modification, or distribution is prohibited.

## Credits

**Developed for ShowBox**
- IPTV Billing Management System
- Integrated with Stalker Portal
- Version 1.0.0 - January 2025

---

**Version:** 1.0.0
**Last Updated:** January 2025
**Status:** Production Ready ✅
**Maintained by:** ShowBox Development Team
