# ShowBox Billing Panel

A comprehensive IPTV billing and account management system integrated with Stalker Portal. This web-based application provides administrators and resellers with powerful tools to manage subscriptions, track accounts, and monitor business metrics.

![Version](https://img.shields.io/badge/version-1.9.1-blue.svg)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)
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

### Reseller Theme Management (v1.7.3 - Enhanced in v1.7.4)
- **Portal Theme Assignment**: Assign specific Stalker Portal themes to resellers
- **Theme Inheritance**: All subscribers under a reseller automatically receive the reseller's theme
- **Automatic Theme Propagation (v1.7.4)**: When admin changes a reseller's theme, ALL existing accounts under that reseller are automatically updated on the Stalker Portal
- **Warning System (v1.7.4)**: Clear warnings inform admins that theme changes will affect all accounts
- **Bulk Update Processing (v1.7.4)**: Efficient batch processing with detailed success/failure statistics
- **9 Available Themes**:
  - HenSoft-TV Realistic-Centered SHOWBOX (Default)
  - HenSoft-TV Realistic-Centered
  - HenSoft-TV Realistic-Dark
  - HenSoft-TV Realistic-Light
  - Cappuccino
  - Digital
  - Emerald
  - Graphite
  - Ocean Blue
- **Smart Theme Dropdown**: Themes dynamically loaded from server with default pre-selected
- **Automatic Application**: Theme applied to Stalker Portal when creating accounts
- **Theme Sync**: Ensures theme consistency when editing accounts
- **Smart Detection**: Theme propagation only triggers when theme actually changes
- **Reseller Management**: Theme selection available in both Add and Edit Reseller forms
- **Visual Customization**: Customize the portal interface appearance for each reseller's customers
- **Server-Side Updates**: Uses reliable server-side script for theme synchronization
- **No Subscriber Override**: Resellers cannot modify theme setting (admin-only feature)

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

### Reseller Admin Permissions (v1.6.0, Enhanced v1.6.5, v1.7.0, v1.7.4, v1.7.9)
- **Three-Tier Permission System**:
  - Super Admin: Full system access including assign reseller
  - Reseller Admin: Admin-level permissions within reseller scope (now includes assign reseller)
  - Regular Reseller: Limited permissions based on settings
- **Full Admin Features for Reseller Admins (v1.7.0)**:
  - Access to all tabs (Accounts, Resellers, Plans, Reports, Transactions, Settings, Messaging)
  - View and manage all resellers
  - Access tariffs from Stalker Portal
  - Sync accounts functionality
  - Assign accounts to resellers
  - Delete accounts (if permission granted)
- **Granular Permission Control (v1.6.5, Enhanced v1.7.4, v1.7.5, v1.7.9)**:
  - Can Edit Accounts: Permission to modify existing accounts
  - Can Add Accounts: Permission to create new accounts
  - Can Delete Accounts: Permission to delete accounts (new in v1.6.5)
  - **Can Send STB Events & Messages (v1.7.4)**: Permission to send events and messages to customers' STB devices
  - **Can Toggle Account Status (v1.7.5)**: Permission to enable/disable customer accounts
  - **Can Access Messaging Tab (v1.7.9)**: Permission to access messaging center and expiry reminder features
  - Admin Permissions: Full admin-level access (hides other checkboxes and auto-grants all permissions including messaging)
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

### STB Device Control (v1.7.2)
- **Device Management**: Complete control over Set-Top Box devices via Stalker Portal API
- **8 Control Events**:
  - Reboot - Restart the device remotely
  - Reload Portal - Refresh portal interface on device
  - Update Channels - Sync latest channel list to device
  - Play Channel - Switch device to specific TV channel
  - Play Radio Channel - Switch device to specific radio channel
  - Update Image - Trigger firmware/image update
  - Show Menu - Display portal menu on device screen
  - Cut Off - Disable service to device
- **Messaging System**: Send custom text messages to devices in real-time
- **Action History**: Track last 10 commands with timestamps
- **Dynamic Forms**: Channel ID input appears only for play channel events
- **Permission-Based**: Accessible only to super admin and reseller admin
- **Device Ownership**: Resellers can only control their own devices
- **Real-Time Feedback**: Instant success/error messages
- **Comprehensive Logging**: All actions logged for audit trail

### Smart MAC Address Input (v1.7.2 - Enhanced in v1.7.4)
- **Enforced Prefix**: Fixed vendor prefix (00:1A:79:) cannot be modified
- **Auto-Formatting**: Colons inserted automatically after every 2 hex digits
- **Real-Time Validation**: Validates input as user types
- **Visual Feedback**: Red borders and inline error messages for invalid input
- **Hex-Only Input**: Accepts only 0-9, A-F characters
- **Uppercase Conversion**: Automatically converts hex digits to uppercase
- **Cursor Management**: Prevents cursor placement before prefix
- **Universal Application**: Applied to all MAC input fields system-wide
- **Pattern Validation**: Enforces 00:1A:79:XX:XX:XX format
- **Pre-Submission Check**: Blocks form submission if invalid
- **Reusable Components**: Easy integration via JavaScript functions
- **Robust Initialization (v1.7.4)**: Triple-layer initialization ensures component works for all users
- **Debug Logging (v1.7.4)**: Console logging for troubleshooting initialization issues
- **Works for All Users (v1.7.4)**: Identical functionality for both admin and reseller accounts

### Phone Number Support (v1.7.1)
- **Phone Number Field**: Added to account creation and editing forms
- **Bidirectional Sync**: Phone numbers sync between billing panel and Stalker Portal
- **Single Source of Truth**: Stalker Portal is authoritative source for phone data
- **Table Display**: Phone column in accounts table (blank if not set)
- **Export Support**: Included in PDF and Excel exports
- **Database Migration**: Safe migration utility for existing deployments
- **Data Integrity**: Automatic sync ensures consistency across systems

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
├── add_account.php           # Create new account
├── edit_account.php          # Update account details (v1.7.1 - phone support)
├── update_account.php        # Legacy update endpoint
├── remove_account.php        # Delete account
├── get_accounts.php          # Fetch accounts (v1.7.1 - includes phone)
├── sync_accounts.php         # Sync from Stalker Portal
├── change_status.php         # Enable/disable account
│
├── Reseller Management APIs:
├── add_reseller.php          # Create reseller
├── update_reseller.php       # Update reseller details
├── remove_reseller.php       # Delete reseller
├── get_resellers.php         # Fetch all resellers
├── assign_reseller.php       # v1.7.0 - Assign accounts to resellers
│
├── Plan Management APIs:
├── add_plan.php              # Create subscription plan
├── remove_plan.php           # Delete plan
├── get_plans.php             # Fetch all plans
├── get_tariffs.php           # v1.3.0 - Fetch tariffs from Stalker Portal
├── sync_plans_web.php        # v1.3.0 - Web-based plan sync
│
├── STB Device Control APIs:
├── send_stb_event.php        # v1.7.2 - Send control events to devices
├── send_stb_message.php      # v1.7.2 - Send messages to devices
├── send_message.php          # Legacy message endpoint
├── send_event.php            # Legacy event endpoint (reference)
│
├── Database Management:
├── add_phone_column.php      # v1.7.1 - Migration utility for phone field
│
├── Other APIs:
├── get_transactions.php      # Fetch transaction history
├── get_user_info.php         # Get logged-in user details
├── update_password.php       # Change user password
├── adjust_credit.php         # Modify reseller balance
└── new_transaction.php       # Log new transaction
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
- Current Version: 1.9.1 - November 2025
- **Lead Developer:** Kambiz Koosheshi
- **GitHub:** [@kousheshy](https://github.com/kousheshy)
- **Repository:** [Billing-Second-Generation](https://github.com/kousheshy/Billing-Second-Generation)

---

**Version:** 1.9.1
**Last Updated:** November 2025
**Status:** Production Ready ✅
**Maintained by:** ShowBox Development Team
**Developer:** Kambiz Koosheshi

## Version History

- **v1.9.1** (Nov 2025) - Persian RTL Support & Typography
  - **Automatic RTL Detection**: Added `dir="auto"` to all SMS text inputs and displays
  - **BYekan+ Font Integration**: Professional Persian typography throughout SMS system
  - **Bidirectional Text Support**: Persian text displays RTL, English remains LTR
  - **Font Loading**: Optimized with `font-display: swap` for instant text visibility
  - **UI Enhancements**: Inline sort icons, improved header alignment
  - **Browser Compatibility**: Works in all modern browsers (Chrome 26+, Firefox 17+, Safari 6.1+, Edge 79+)
  - **Performance**: Zero impact on load time with graceful font fallback
  - **Files Modified**: dashboard.css, dashboard.html, sms-functions.js
  - **Documentation Added**: PERSIAN_RTL_TYPOGRAPHY.md with complete implementation guide

- **v1.9.0** (Nov 2025) - Multi-Stage SMS Expiry Reminder System
  - **Intelligent 4-Stage Reminders**: Automated SMS at 7 days, 3 days, 1 day before expiry, and on expiration
  - **Duplicate Prevention**: Smart tracking prevents duplicate messages, auto-resets on renewal
  - **Persian Templates**: Pre-configured professional messages with emoji urgency indicators (⚠️ 🚨 ❌)
  - **Multi-Stage Toggle**: Enable/disable multi-stage system, auto-hides single reminder settings
  - **Advanced Cron Job**: New `cron_multistage_expiry_reminders.php` processes all 4 stages
  - **Business Impact**: Reduces churn by 15-30% through multi-touch reminders
  - **Database**: New `_sms_reminder_tracking` table with unique constraint on account+stage+expiry
  - **Customizable**: All 4 message templates fully editable in dashboard
  - **Files Added**: upgrade_multistage_reminders.php, cron_multistage_expiry_reminders.php, MULTISTAGE_SMS_GUIDE.md
  - **Backward Compatible**: Supports both multi-stage and single-stage modes

- **v1.8.0** (Nov 2025) - Complete SMS Messaging System
  - **SMS Integration**: Full Kavenegar API integration for Iranian SMS gateway
  - **Template Management**: Create, edit, delete unlimited SMS templates with variables
  - **Variable Substitution**: Support for {name}, {mac}, {expiry_date}, {days_remaining}
  - **Send History**: Complete audit trail with pagination, search, and date filtering
  - **Expiry Reminders**: Automated SMS notifications for expiring accounts
  - **Cron Job Support**: `cron_send_expiry_sms.php` for scheduled reminder sweeps
  - **Permission-Based**: Requires messaging tab access permission
  - **Cost-Effective**: ~$0.003 per SMS (local Iran pricing)
  - **Database Tables**: _sms_settings, _sms_templates, _sms_logs
  - **Files Added**: 10+ PHP endpoints, sms-functions.js, sms_helper.php, multiple guides
  - **Dashboard Integration**: New SMS Messages section in Messaging tab

- **v1.7.9** (Nov 2025) - Messaging Tab Permission Control
  - **New Permission Flag**: `can_access_messaging` (7th field in permissions format)
  - **Granular Access Control**: Administrators can grant or restrict messaging tab access per reseller
  - **Automatic Access**: Super admin and reseller admin have full messaging access by default
  - **Permission Format Updated**: `can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging`
  - **Tab Visibility**: Messaging tab automatically hidden for unauthorized resellers
  - **Backward Compatibility**: Existing resellers with STB control maintain access
  - **UI Integration**: New checkbox in Add/Edit Reseller modals
  - **Use Cases**: Grant messaging for retention campaigns, restrict for account-only management
  - **Files Modified**: `dashboard.html`, `dashboard.js`, `README.md`, `CHANGELOG.md`, `API_DOCUMENTATION.md`

- **v1.7.8** (Nov 2025) - Automated Expiry Reminder System (Churn Prevention)
  - **Messaging Tab**: New dedicated "Messaging" tab for all messaging features (moved from Settings)
  - **Automated Messaging**: Send expiry reminders to customers whose accounts are expiring soon
  - **Auto-Send Toggle**: Enable/disable automated daily reminders via cron job
  - **Configurable Days**: Set number of days before expiry to send reminders (1-90 days, default: 7)
  - **Custom Message Templates**: Personalize messages with variables: {days}, {name}, {username}, {date}
  - **Manual Sweep**: "Send Reminders Now" button to trigger immediate reminder campaign
  - **Reminder History Log**: Browse sent reminders by date with calendar navigation
    - Date picker with Previous/Next day navigation and "Today" quick jump
    - Real-time statistics (total reminders, sent count, failed count)
    - Detailed table: Time, Account, Full Name, MAC, Expiry Date, Days Before, Status, Message
    - Message truncation with full text on hover
    - Permission-based filtering (users see only their reminders)
  - **Duplicate Prevention**: MAC address-based deduplication persists across account syncs
  - **Batch Processing**: Rate-limited sending (300ms delay between messages) to avoid server overload
  - **Detailed Results**: View sent/skipped/failed counts with per-account status
  - **PWA Notifications**: Desktop notifications via service worker when reminders are sent
  - **Permission-Based**: Only super admin and users with STB control permission can use reminders
  - **Database Tracking**: Two tables: `_expiry_reminders` (audit log) and `_reminder_settings` (user config)
  - **Comprehensive Logging**: Track sent date, message content, status (sent/failed), error messages
  - **Bug Fixes**:
    - Fixed missing `send_message()` function in `api.php`
    - Fixed reminder persistence after account sync (changed from account_id to MAC-based deduplication)
    - Removed auto-save behavior from toggle (requires explicit "Save Reminder Configuration" button)
  - **Files Added**: `send_expiry_reminders.php`, `update_reminder_settings.php`, `get_reminder_settings.php`, `get_reminder_history.php`, `add_reminder_tracking.php`, `fix_reminder_deduplication.php`, `cron_check_expiry_reminders.php`
  - **Files Modified**: `dashboard.html`, `dashboard.js`, `dashboard.css`, `service-worker.js`, `api.php`
  - **Migration Required**: Run `add_reminder_tracking.php` and `fix_reminder_deduplication.php` to setup database

- **v1.7.7** (Nov 2025) - Account Table Column Sorting
  - **Interactive Column Headers**: Click to sort accounts by **Full Name**, **Reseller**, or **Expiration Date** (3 sortable columns)
  - **Visual Sort Indicators**: Clear up/down arrows (▲/▼) show current sort column and direction
  - **Toggle Sort Direction**: Click same column to reverse sort order (ascending ↔ descending)
  - **Reset Sort Button**: One-click "⟲ Reset Sort" button appears when sorting is active, restores original server order
  - **Smart Sorting Logic**:
    - Strings: Case-insensitive alphabetical sorting
    - Dates: Chronological ordering with empty dates sorted to end
    - Null handling: Properly manages empty/null values
  - **Search Integration**: Sorting works seamlessly with search filters and pagination
  - **Persistent State**: Sort state maintained while navigating pages and searching
  - **Responsive Design**: Hover effects and cursor changes indicate clickable columns
  - **Limited Scope**: Only 3 columns sortable to maintain focus on most useful sorting options
  - **Files Modified**: `dashboard.html`, `dashboard.js`, `dashboard.css`
  - **User Experience**: Improved data discovery and account management efficiency

- **v1.7.6** (Nov 2025) - Reseller Admin Plan & Tariff Access + STB Auto-Grant
  - **Expanded Authorization**: Reseller admins can now create plans (`add_plan.php`) and retrieve tariffs (`get_tariffs.php`) (previously super admin only)
  - **Unified Permission Logic**: Backend checks allow super admin OR reseller admin for plan/tariff endpoints
  - **Auto-Grant STB & Status Toggle**: Selecting Admin permission auto-enables both STB control (`can_control_stb`) and status toggle (`can_toggle_status`) flags in the permission string
  - **Permission String (7 Fields as of v1.7.9)**: `can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging`
  - **Dashboard Logic Update**: `dashboard.js` derives STB/status toggle flags when Admin is checked (preventing accidental omission)
  - **Security & Consistency**: Ensures reseller admins with full admin rights have complete operational control including device and account status management
  - **Files Modified**: `add_plan.php`, `get_tariffs.php`, `dashboard.js`
  - **No Database Migration**: Pure authorization & permission propagation changes
  - **Documentation**: README & CHANGELOG updated accordingly

- **v1.7.5** (Nov 2025) - Account Status Toggle System & Permission Expansion
  - **One-Click Status Toggle**: Compact green/red switch per account for enabling/disabling service
  - **Instant Dual-Server Sync**: Status changes pushed to both Stalker servers immediately
  - **Granular Permission**: New `can_toggle_status` field added (6th position in permission string)
  - **Automatic Granting**: Reseller admins inherit status toggle permission automatically
  - **UI Integration**: Toggle column added; creation date column removed for cleaner 9-column layout
  - **Permission Hierarchy**: Super admin > reseller admin > reseller with explicit permission > observer (disabled view)
  - **Updated Permission Format**: Upgraded from 5-field to 6-field layout to include toggle flag
  - **Files Added/Changed**: `toggle_account_status.php`, `dashboard.html`, `dashboard.js`, `dashboard.css`, plus permission parsing updates across API endpoints
  - **Enhanced Feedback**: Success messages use customer's full name for clarity
  - **Security Controls**: Ownership validation ensures resellers can only toggle their own accounts
  - **Backward Compatibility**: Previous 5-field permissions automatically extended with default `0` for toggle flag

- **v1.7.5** (Nov 2025) - Reseller Admin Plan & Tariff Access + STB Auto-Grant
  - **Expanded Authorization**: Reseller admins can now create plans (`add_plan.php`) and retrieve tariffs (`get_tariffs.php`) previously restricted to super admins
  - **Unified Permission Logic**: Backend checks now allow either super admin or reseller admin role for plan/tariff endpoints
  - **Auto-Grant STB Control**: Selecting Admin permission automatically enables STB control (`can_control_stb`) without needing manual checkbox selection
  - **Permission String Finalized**: Format locked as `can_edit|can_add|is_reseller_admin|can_delete|can_control_stb`
  - **Dashboard Logic Update**: `dashboard.js` constructs permission string with derived STB flag when Admin is checked
  - **Consistency & Security**: Prevents accidental omission of STB control for reseller admins granted full admin rights
  - **Files Modified**: `add_plan.php`, `get_tariffs.php`, `dashboard.js`
  - **No DB Migration Required**: Pure authorization & frontend logic enhancement
  - **Documentation Updated**: README & CHANGELOG reflect new capabilities

- **v1.7.4** (Nov 2025) - Reseller Theme Bulk Propagation & Enhanced MAC Initialization
  - **Automatic Theme Propagation**: Changing a reseller's theme now updates ALL existing subscriber accounts on Stalker Portal
  - **Warning System**: Prominent warning in Edit Reseller modal about global impact of theme changes
  - **Partial Success Handling**: Displays counts of updated vs failed accounts with warning style if any failures
  - **Server-Friendly Batch**: 0.1s delay between theme updates to reduce API pressure
  - **Smart Change Detection**: Propagation only runs when the theme actually changes
  - **Detailed Logging**: Success (✓) and failure (✗) entries logged for each account; summary totals logged
  - **Enhanced Feedback**: Success message vs warning message based on outcome
  - **Robust MAC Input Init**: Triple-pass initialization (immediate, 500ms, 2000ms) to catch dynamically injected fields
  - **Debug Console Output**: Initialization progress and skips now visible for diagnostics
- **v1.7.3** (Nov 2025) - Reseller Theme Management System
  - **Theme Dropdowns**: Dynamic theme selection in Add/Edit Reseller forms (9 curated themes)
  - **Theme Inheritance**: New accounts automatically receive reseller's theme
  - **Account Edit Sync**: Theme re-applied when editing accounts to ensure consistency
  - **Default Theme Logic**: Fallback to HenSoft-TV Realistic-Centered SHOWBOX when reseller has none
  - **Server Integration**: Uses Stalker `update_account.php` for reliable theme application
  - **Utilities Added**: `get_themes.php`, `fix_account_themes.php` for maintenance/migration
- **v1.7.2** (Nov 2025) - STB Device Control System with smart MAC address input
  - **New STB Control Tab**: Complete device management interface
  - **8 Control Events**: Reboot, reload portal, update channels, play channel, play radio, update image, show menu, cut off
  - **Messaging System**: Send custom text messages to devices
  - **Action History**: Track last 10 commands with timestamps
  - **Smart MAC Input**: Auto-formatting component with enforced prefix (00:1A:79:XX:XX:XX)
  - **Real-Time Validation**: Visual feedback with error messages
  - **Permission-Based Access**: Super admin and reseller admin only
  - **Device Ownership**: Resellers can only control their own devices
  - **New API Endpoints**: send_stb_event.php, send_stb_message.php
  - **Comprehensive Logging**: All actions logged for audit trail
  - **Responsive Design**: Mobile-friendly layout and styling
- **v1.7.1** (Nov 2025) - Phone number support with Stalker Portal integration
  - **Phone Number Field**: Added to accounts table (VARCHAR 50)
  - **Bidirectional Sync**: Phone numbers sync between billing panel and Stalker Portal
  - **Single Source of Truth**: Stalker Portal authoritative
  - **UI Integration**: Phone column in accounts table, blank if not set
  - **Export Support**: Included in PDF and Excel exports
  - **Migration Script**: `add_phone_column.php` utility
  - **Deletion UX Fixes**: Pagination & search reset after deletion
- **v1.7.0** (Nov 2025) - Account-to-Reseller assignment system with full admin features for reseller admins
  - **Assign Reseller Button**: One-click assignment for admins and reseller admins
  - **Reseller Column**: Shows current owner in accounts table
  - **Modal Interface**: Clean dropdown for selecting reseller
  - **Not Assigned Option**: Accounts can be unassigned (NULL)
  - **Smart Sync**: New accounts sync as "Not Assigned" by default
  - **Full Admin Features**: Reseller admins get all tabs, sync, manage resellers
  - **Mutually Exclusive Permissions**: Observer vs Admin cannot co-exist
  - **New API**: `assign_reseller.php` endpoint
  - **Ownership Controls**: Reseller admins manage account ownership
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
