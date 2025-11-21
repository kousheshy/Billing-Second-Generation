# ShowBox Billing Panel

A comprehensive IPTV billing and account management system integrated with Stalker Portal. This web-based application provides administrators and resellers with powerful tools to manage subscriptions, track accounts, and monitor business metrics.

![Version](https://img.shields.io/badge/version-1.7.1-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)
![Status](https://img.shields.io/badge/status-production-green.svg)
![PWA](https://img.shields.io/badge/PWA-Ready-green.svg)

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
3. On first login, accounts will automatically sync from Stalker Portal
   - Wait for "Loading dashboard..." overlay to complete
   - No user action required - sync happens automatically

### Technology Stack
- **Backend**: PHP 7.4+, MySQL 5.7+
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **APIs**: Stalker Portal REST API Integration
- **Security**: Session-based authentication, PDO prepared statements

## Features

### Core Functionality
- **Account Management**: Create, edit, delete, and manage IPTV accounts
- **Auto-Sync on Login**: Automatic account synchronization from Stalker Portal when logging in
  - Admin: Syncs all accounts from server
  - Reseller: Syncs only their assigned accounts
  - Seamless loading experience with no timeout limits
  - Preserves reseller-to-account ownership mappings
- **Stalker Portal Sync**: Manual one-click synchronization available from Accounts tab
- **Multi-Server Support**: Manage accounts across two separate servers
- **Reseller System**: Multi-tier reseller management with balance tracking
- **Tariff Plans**: Flexible subscription plans with automated expiration tracking
- **Transaction History**: Complete financial transaction logging
- **Advanced Analytics**: Comprehensive reports and business intelligence

### Stalker Portal Integration (v1.6.6)
- **Bidirectional Reseller Sync**: Reseller ownership synced between billing panel and Stalker Portal
- **Source of Truth**: Stalker Portal is primary source for reseller assignments
- **Account Creation**: Reseller ID automatically sent to Stalker when creating accounts
- **Account Updates**: Reseller ownership maintained in Stalker during updates
- **Smart Sync Logic**: Three-tier priority for reseller assignment:
  1. Stalker Portal reseller field (primary)
  2. Existing local database mapping (fallback)
  3. Current user performing sync (last resort)
- **Data Integrity**: Prevents reseller reassignment during sync operations
- **Migration Tools**: Scripts provided for existing deployments
- **Comprehensive Logging**: Debug output for reseller tracking and troubleshooting

### Dashboard Features
- Real-time account statistics with visual cards
- Automatic data synchronization on login with loading overlay
- Expiring accounts alerts (next 2 weeks)
- Expired & not renewed tracking with custom date ranges
- Active/Inactive account monitoring
- Dynamic date range reports (7, 14, 30, 60, 90, 180, 365 days + custom)
- Search and pagination (25 accounts per page)
- Dark/Light theme toggle (dark mode default in v1.6.5)
- Multi-currency support (GBP, USD, EUR, IRR) with proper formatting

### Plan Management (v1.3.0)
- **Tariff Integration**: Auto-fetch tariffs from Stalker Portal on login
- **Smart Plan Creation**: Select tariff plans directly from your server
- **Auto-populated Fields**: Name and duration filled automatically from tariff
- **Enhanced UI**: Checkbox-based plan assignment for resellers
- **Multi-currency Pricing**: Set prices per plan for each currency
- **Cleaner Forms**: Improved number inputs without spinner arrows

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

### Interactive Reports (v1.4.0)
- **Clickable Report Cards**: One-click access to detailed account lists
- **8 Interactive Reports**: All report cards now clickable with tooltips
- **Smart Filtering**: Click any report to see filtered accounts in Accounts tab
- **Seamless Navigation**: Auto-switches tabs and applies filters
- **Enhanced UI**: Hover effects and visual feedback on all report cards
- **Report Types**:
  - Total Accounts - View all accounts
  - Active Accounts - View only active subscriptions
  - Expired Accounts - View all expired accounts
  - Expiring Soon - View accounts expiring in next 2 weeks
  - Unlimited Plans - View accounts with no expiration
  - Expired Last Month - View accounts expired in last 30 days
  - Dynamic Expired - View expired accounts based on selected date filter
  - Dynamic Expiring - View expiring accounts based on selected date filter

### Report Exports (v1.5.0)
- **PDF Export**: Generate professional PDF reports with branded headers
- **Excel Export**: Export data to Excel with formatted columns
- **One-Click Export**: Export buttons on all 8 report cards
- **Smart Filtering**: Exports respect current report filters
- **Professional Layout**: Landscape PDF, auto-sized Excel columns
- **Comprehensive Data**: Includes MAC, Name, Phone, Status, Expiry, Reseller
- **Timestamped Files**: Auto-generated filenames with dates
- **Export Libraries**: Integrated XLSX.js, jsPDF, and jsPDF-autoTable

### Reseller Assignment (v1.7.0)
- **Account-to-Reseller Mapping**: Assign any account to any reseller with one click
- **Assign Reseller Button**: Available on each account row for admins
- **Modal Interface**: Clean dropdown showing all resellers
- **Not Assigned Option**: Accounts can be unassigned (set to NULL)
- **Reseller Column**: View current reseller owner in account table
- **Bidirectional Updates**: Changes sync between billing panel and local database
- **Admin & Reseller Admin Access**: Both super admins and reseller admins can assign accounts
- **Smart Filtering**: Reseller column displays:
  - Reseller name if assigned
  - "Not Assigned" in gray italic for unassigned accounts
- **Permission-Based Visibility**: Assign button only visible to authorized users
- **Improved Sync Logic**:
  - New accounts from Stalker sync as "Not Assigned" by default
  - Manual assignment required for proper reseller ownership
  - Prevents automatic admin assignment during sync

### Reseller Admin Permissions (v1.6.0, Enhanced v1.6.5, v1.7.0)
- **Three-Tier Permission System**:
  - Super Admin: Full system access including assign reseller
  - Reseller Admin: Admin-level permissions within reseller scope (now includes assign reseller)
  - Regular Reseller: Limited permissions based on settings
- **Full Admin Features for Reseller Admins (v1.7.0)**:
  - Access to all tabs (Accounts, Resellers, Plans, Reports, Transactions, Settings)
  - View and manage all resellers
  - Access tariffs from Stalker Portal
  - Sync accounts functionality
  - Assign accounts to resellers
  - Delete accounts (if permission granted)
- **Granular Permission Control (v1.6.5)**:
  - Can Edit Accounts: Permission to modify existing accounts
  - Can Add Accounts: Permission to create new accounts
  - Can Delete Accounts: Permission to delete accounts (new in v1.6.5)
  - Admin Permissions: Full admin-level access (hides other checkboxes)
  - Observer: Read-only access (mutually exclusive with Admin - v1.7.0)
- **View Mode Toggle**: Reseller admins can switch between:
  - "My Accounts" - View only their own accounts
  - "All Accounts" - View all accounts in the system
- **Smart UI Adaptation**:
  - Reseller admins see full admin-level interface
  - All stat cards visible (Total Accounts, Total Resellers, Total Plans)
  - Dynamic report visibility based on view mode
  - Account and plan filtering synchronized with view mode
- **Edit Reseller Functionality**: Admin can now edit reseller details and permissions
- **Permission Persistence**: View mode preference saved to localStorage
- **Enhanced Security**: Permission checks in all PHP backend files

### Progressive Web App (PWA) (v1.6.2)
- **Installable Application**: Install on mobile and desktop devices
- **Offline Support**: Service Worker caching for offline functionality
- **Fast Loading**: Cached resources for instant app startup
- **Auto-Updates**: Automatic detection and prompts for new versions
- **App Icons**: Multiple sizes (72x72 to 512x512) for all devices
- **iOS Support**: Apple Touch Icons for iPhone and iPad
- **Responsive Design**: Optimized for mobile, tablet, and desktop
- **App-Like Experience**: Full-screen mode, splash screen, theme color
- **Manifest File**: Complete PWA configuration
- **Push Notification Ready**: Infrastructure for future notifications

### Observer Mode (v1.6.3, Enhanced v1.6.4, v1.7.0)
- **Read-Only Access**: View all data without ability to modify
- **Four-Tier User System**:
  - Super Admin: Full system access
  - Reseller Admin: Admin-level permissions within scope
  - Regular Reseller: Limited permissions
  - Observer: Read-only access to everything
- **Complete Visibility**: Observers can view:
  - All accounts across all resellers
  - All plans and pricing
  - All transactions with reseller information
  - All resellers and their details
  - All reports and analytics
- **Zero Modification**: No ability to:
  - Add, edit, or delete accounts
  - Create or modify plans
  - Adjust credits or balances
  - Sync accounts from server
  - Change any settings or configurations
  - Assign accounts to resellers (new in v1.7.0)
- **Enhanced UI (v1.6.4)**:
  - Disabled buttons with visual feedback (grayed out)
  - Settings tab completely hidden
  - Reseller column in transactions view
  - Consistent button states across all tables
  - Clear visual distinction between viewing and editing modes
- **Mutually Exclusive Permissions (v1.7.0)**:
  - Observer and Admin permissions cannot be selected together
  - When Observer is checked, all other permissions are hidden and disabled
  - When Admin is checked, Observer is hidden and disabled
  - Ensures proper role separation and prevents permission conflicts
- **Security**: Perfect for auditors, accountants, or monitoring staff

### User Management
- Four-tier user system: Super Admin, Reseller Admin, Regular Reseller, Observer
- Granular permission control for each user type
- Balance management system
- Secure session-based authentication
- Password change functionality
- Observer mode for read-only access (perfect for auditors)
- Permission-based UI visibility and feature access

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
├── assign_reseller.php    # NEW v1.7.0 - Assign accounts to resellers
│
├── Plan Management APIs:
├── add_plan.php
├── remove_plan.php
├── get_plans.php
├── get_tariffs.php      # NEW v1.3.0 - Fetch tariffs from Stalker Portal
├── sync_plans_web.php   # NEW v1.3.0 - Web-based plan sync
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

### Adding a Plan (Enhanced in v1.3.0)
1. Go to **Plans** tab
2. Click **+ Add Plan**
3. Select a **Tariff Plan** from dropdown (auto-fetched from Stalker Portal)
4. Plan name and duration are auto-filled
5. Choose **Currency** (GBP/USD/EUR/IRR)
6. Enter **Price** for this currency
7. Click **Create Plan**

**Note**: Plan name and duration can be edited but are pre-populated from the selected tariff.

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
- Version 1.6.6 - November 2025

---

**Version:** 1.7.1
**Last Updated:** November 2025
**Status:** Production Ready ✅
**Maintained by:** ShowBox Development Team
**Developer:** Kambiz Koosheshi

## Version History

- **v1.7.1** (Nov 2025) - Phone number support with Stalker Portal integration
  - Phone number field added to accounts table
  - Phone number synced from Stalker Portal (single source of truth)
  - Phone numbers displayed in UI, reports, and exports
  - Fixed pagination and search state bugs after account deletion
- **v1.7.0** (Nov 2025) - Account-to-Reseller assignment system with full admin features for reseller admins
  - New "Assign Reseller" button on account rows for admins and reseller admins
  - Reseller column added to accounts table
  - Modal interface for selecting reseller from dropdown
  - Accounts sync as "Not Assigned" by default (prevents auto-admin assignment)
  - Reseller admins now have full admin-level features (all tabs, sync, manage resellers)
  - Mutually exclusive Observer and Admin permissions (cannot select both)
  - Enhanced permission system with proper role separation
  - Backend support for reseller admin account assignment
  - New `assign_reseller.php` API endpoint
- **v1.6.6** (Nov 2025) - Stalker Portal reseller integration with bidirectional sync
- **v1.6.5** (Nov 2025) - Granular delete permissions, dark mode default, sync bug fixes
- **v1.6.4** (Nov 2025) - Observer Mode UI improvements with disabled buttons and transaction reseller column
- **v1.6.3** (Nov 2025) - Observer (Read-Only) mode for auditing and monitoring
- **v1.6.2** (Nov 2025) - Progressive Web App (PWA) support with offline functionality
- **v1.6.1** (Nov 2025) - Currency validation for plan assignment to resellers
- **v1.6.0** (Nov 2025) - Reseller admin permissions system with view mode toggle
- **v1.5.0** (Nov 2025) - Report export functionality with PDF and Excel support
- **v1.4.0** (Nov 2025) - Interactive reports with clickable report cards
- **v1.3.0** (Nov 2025) - Enhanced plan management with tariff integration
- **v1.2.0** (Nov 2025) - Dark mode improvements and legacy data compatibility
- **v1.1.0** (Nov 2025) - Currency standardization and auto-sync features
- **v1.0.0** (Jan 2025) - Initial production release
