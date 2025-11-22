# Changelog

All notable changes to the ShowBox Billing Panel will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.7.2] - 2025-11-22

### Added - STB Device Control System

**STB Control Features**
- New "STB Control" tab in dashboard for managing Set-Top Box devices
- Send events to STB devices via Stalker Portal API:
  - Reboot device
  - Reload portal
  - Update channels
  - Play channel (with channel ID)
  - Play radio channel (with channel ID)
  - Update image
  - Show menu
  - Cut off device
- Send custom messages to STB devices
- Real-time action history showing last 10 commands
- Event-specific input fields (e.g., channel ID for play channel events)
- Responsive grid layout for control forms
- Timestamp tracking for all actions

**Smart MAC Address Input Component**
- Intelligent MAC address input with enforced prefix (00:1A:79:)
- Auto-formatting with colons after every 2 hex digits
- Real-time validation as user types
- Visual error feedback with red borders and error messages
- Prevents modification of required prefix
- Cursor management to maintain prefix integrity
- Hex-only input validation (0-9, A-F)
- Applied to all MAC input fields system-wide
- Validates on blur with detailed error messages

**New API Endpoints**
- `send_stb_event.php` - Send events to Stalker Portal STB devices
- `send_stb_message.php` - Send messages to Stalker Portal STB devices

### Changed

**UI/UX Improvements**
- Added STB Control tab to main navigation
- Responsive two-column layout for STB forms
- Clean form design with consistent styling
- History list with hover effects and smooth transitions
- Mobile-responsive single-column layout
- Monospace font for MAC address inputs
- Enhanced visual feedback for form interactions

**Form Validation**
- MAC address validation integrated into all forms
- Pre-submission validation prevents invalid API calls
- Error messages displayed inline with animations
- Focus management after validation errors

### Fixed

**Input Handling**
- MAC address inputs now prevent invalid characters
- Prefix enforcement prevents accidental deletion
- Proper cursor positioning in MAC inputs
- Auto-uppercase conversion for hex digits

---

## [1.7.1] - 2025-11-22

### Added - Phone Number Support

**Phone Number Integration**
- Added `phone_number` column to accounts table
- Phone number field in Add Account and Edit Account forms
- Phone number sent to Stalker Portal API during account creation
- Phone number synced from Stalker Portal during account sync (single source of truth)
- Phone number displayed in accounts table (new column)
- Phone number included in Excel and PDF export reports
- New database migration utility: `add_phone_column.php`

**Data Integrity**
- Stalker Portal is the single source of truth for phone numbers
- Local database phone numbers are always overwritten during sync
- If Stalker has no phone number, local database sets to NULL
- Prevents data inconsistencies between systems

### Fixed

**UI/UX Improvements**
- Fixed pagination not resetting after account deletion
- Fixed search term persisting after account deletion
- Cleared filtered accounts state when accounts are deleted
- Improved colspan values in table rendering (8 → 9 columns)
- Better user experience when managing accounts

### Changed

**Database Schema**
- Updated `add_account.php` to INSERT phone_number field
- Updated `edit_account.php` to UPDATE phone_number field
- Updated `sync_accounts.php` to sync phone numbers from Stalker
- Updated `get_accounts.php` query (implicit - returns all columns)
- Modified dashboard.js to display phone_number in UI and exports
- Updated dashboard.html to include phone column in accounts table

---

## [1.7.0] - 2025-11-22

### Added - Account-to-Reseller Assignment System

**Reseller Assignment Feature**
- Added "Assign Reseller" button on each account row for admins and reseller admins
- New reseller column in accounts table showing current owner
- Modal interface with dropdown to select reseller from list
- "Not Assigned" option to unassign accounts (sets reseller to NULL)
- Real-time updates when assigning/reassigning accounts
- Permission-based button visibility (only for super admin and reseller admin)
- New `assign_reseller.php` API endpoint for backend processing
- Updated `get_accounts.php` to include reseller_name via LEFT JOIN
- Enhanced `sync_accounts.php` to set new accounts as "Not Assigned" by default

**Full Admin Features for Reseller Admins**
- Reseller admins now have access to all tabs (Accounts, Resellers, Plans, Reports, Transactions, Settings)
- Sync accounts functionality now available to reseller admins
- Access to view and manage all resellers
- Access to tariffs from Stalker Portal
- All stat cards visible (Total Accounts, Total Resellers, Total Plans)
- Can assign accounts to resellers
- Can delete accounts (with proper permission)

**Enhanced Permission System**
- Observer and Admin permissions are now mutually exclusive
- When Observer is checked, Admin permission is hidden and unchecked
- When Admin is checked, Observer permission is hidden and unchecked
- All other permissions (Edit, Add, Delete) hidden when Observer is checked
- Prevents permission conflicts and ensures proper role separation
- Updated `handleAdminPermissionToggle()` function to manage exclusive permissions
- Event listeners for both Admin and Observer checkboxes in Add/Edit Reseller modals

### Changed

**Permission Handling**
- Modified `assign_reseller.php` to allow both super admins and reseller admins
- Updated permission check to include `is_reseller_admin` flag
- Enhanced frontend button rendering to show assign button for reseller admins
- Modified `dashboard.js` checkAuth() function to give reseller admins full features

**Sync Behavior**
- Changed default reseller assignment from current user to NULL for new accounts
- Prevents automatic admin assignment during sync operations
- Accounts must be manually assigned to resellers via "Assign Reseller" button
- Preserves reseller-to-account ownership for existing accounts

**UI/UX Improvements**
- Reseller column displays reseller name or "Not Assigned" in gray italic
- Modal opens smoothly with `show` class instead of `active` class (bug fix)
- Clean dropdown interface showing all available resellers
- Consistent styling with existing modals

### Fixed

**Modal Opening Issue**
- Fixed assign reseller modal not opening due to CSS class mismatch
- Changed from `modal.classList.add('active')` to `modal.classList.add('show')`
- CSS expects `.modal.show` to display modal
- Added proper error handling and console logging

**Permission System Bugs**
- Fixed issue where Observer and Admin could be selected simultaneously
- Fixed permission checkboxes not hiding properly when Observer was selected
- Fixed permission states not being preserved correctly
- Enhanced setupAddResellerPermissions() and setupEditResellerPermissions() functions

---

## [1.1.0] - 2025-11-21

### Added - Auto-Sync on Login

**Automatic Synchronization**
- Implemented automatic account sync on login for both admin and resellers
- Admin: Syncs all accounts from Stalker Portal
- Reseller: Syncs only their assigned accounts
- Added full-screen loading overlay during sync process
- Users experience seamless loading without knowing sync is happening
- No timeout limit - sync completes regardless of internet speed or account volume
- Preserves existing reseller-to-account ownership mappings across syncs

**Loading UX**
- Added loading spinner overlay with animated graphics in [dashboard.html](dashboard.html:10-14)
- Overlay blocks interaction until sync completes
- Professional "Loading dashboard..." message
- CSS animations for smooth transitions in [dashboard.css](dashboard.css:1339-1375)
- Automatic overlay removal when all data is loaded

### Changed - Currency Standardization

**Currency Code Updates**
- Standardized Iranian currency code from `IRT` to `IRR` (ISO 4217 standard)
- Updated all frontend files (dashboard.html, dashboard.js, dashboard.css)
- Updated backend sync script ([sync_plans.php](sync_plans.php))
- Removed all Persian text (ریال) from currency displays
- Improved currency symbol display logic with fallback handling
- Enhanced balance formatting for all currencies
- Iranian Rial formatting with thousand separators (6,500,000)

**Currency Display Logic**
- IRR displays as "IRR " prefix with comma separators
- Other currencies (USD, EUR, GBP) show symbols with 2 decimal places
- Null/undefined currency defaults to IRR
- Consistent formatting across all pages (accounts, resellers, plans, transactions)

**UI/UX Improvements**
- Improved stat card value typography for better readability in [dashboard.css](dashboard.css:247-257)
- Reduced font size from 36px to 28px for balance displays
- Changed font weight from 700 to 600 for cleaner appearance
- Added proper word wrapping for long values
- Enhanced font styling with system font stack (-apple-system, BlinkMacSystemFont, Segoe UI)
- Adjusted font sizes, letter spacing, and line height for better visual hierarchy

### Fixed

**Auto-Sync Implementation**
- Fixed reseller-specific sync logic in [sync_accounts.php](sync_accounts.php:42-54)
- Resellers now only delete and sync their own accounts
- Admin deletes all accounts and syncs everything
- Preserved account-to-reseller mappings across syncs in [sync_accounts.php](sync_accounts.php:87-93)
- Fixed permission checks to prevent resellers from seeing other accounts

**Currency Display Bugs**
- Fixed null/undefined currency handling in [dashboard.js](dashboard.js:167-192)
- Fixed "£10000000.00" showing instead of "IRR 10,000,000"
- Fixed "null 10000000.00" display in adjust credit modal
- Added defensive programming for missing balance values
- Fixed plan price display in dropdowns showing raw decimals (6500000.00)
- Fixed plan table prices not formatted correctly in [dashboard.js](dashboard.js:761-783)
- Improved number formatting consistency across all currency displays

**JavaScript Errors**
- Fixed duplicate `formattedPrice` variable declaration that was preventing page load
- Fixed onclick attribute generation with null values in [dashboard.js](dashboard.js:702-722)
- Pre-process null balance/currency values before HTML generation

**Performance Issues**
- Removed 30-second timeout from auto-sync function in [dashboard.js](dashboard.js:124-141)
- Sync now waits indefinitely for completion (handles slow internet)
- Improved loading state management for large account databases

---

## [1.0.0] - 2025-01-17

### Initial Release

#### Added - Core Features

**Account Management**
- Create, edit, and delete IPTV accounts
- Search accounts by username, MAC address, or full name
- Pagination system (25 accounts per page)
- Visual status badges (Active, Expired, Expiring Soon)
- MAC address validation
- Unique username enforcement
- Automatic account number generation

**Stalker Portal Integration**
- One-click account synchronization
- Fetch all accounts from Stalker Portal API
- Automatic data mapping (login→username, mac, full_name, email, end_date, status)
- Duplicate prevention
- Multi-server support (Server 1 and Server 2)
- Fresh sync strategy (DELETE all + INSERT all)
- Invalid date handling (0000-00-00 → NULL)
- Progress tracking with real-time feedback

**Dashboard & Statistics**
- Real-time account statistics
- Total Accounts counter
- Active Accounts counter
- Total Plans counter
- Expiring Soon alerts (next 2 weeks)
- Expired Last Month tracker (not renewed)
- Dark/Light theme toggle
- User balance display
- Responsive grid layout

**Reports & Analytics**
- Comprehensive reports tab with 8 key metrics
- Dynamic date range filters for expired accounts (7, 14, 30, 60, 90, 180, 365 days + custom)
- Dynamic date range filters for expiring accounts (7, 14, 30, 60, 90 days + custom)
- Custom input for any date range (1-3650 days)
- "Expired & Not Renewed" tracking with sophisticated logic
- Real-time client-side report generation
- No server calls for filtering (performance optimization)

**Expiration Logic Innovation**
- Date-based expiration tracking (ignores status field)
- Status field reserved for admin control only
- Accurate renewal detection: accounts are "not renewed" if `end_date` remains in the past
- Renewal detected when `end_date` is updated to a future date

**Reseller Management**
- Create and manage resellers
- Edit reseller details
- Delete resellers
- Balance management (GBP, USD, EUR)
- Set maximum users per reseller
- Theme preferences
- Transaction history per reseller
- Multi-tier support (admin > resellers)

**Subscription Plans**
- Create plans with pricing and duration
- Multi-currency support (GBP, USD, EUR)
- Set expiry days
- Delete plans
- Plans populate in account creation dropdown

**Transaction History**
- View all financial transactions
- Filter by reseller (user-specific view)
- Track credits and debits
- Automatic transaction logging
- Balance calculation

**User Management**
- Admin and reseller roles
- Secure session-based authentication
- Password change functionality
- User-specific permissions (admin can delete, resellers cannot)

#### Added - Technical Features

**Frontend**
- Vanilla JavaScript (ES6+) - no dependencies
- Responsive HTML5/CSS3 design
- CSS Variables for theming
- Dark and light mode with LocalStorage persistence
- Client-side filtering and pagination
- Real-time DOM updates
- Smooth transitions and animations
- Modern card-based UI design

**Backend**
- PHP 7.4+ RESTful JSON APIs
- PDO prepared statements for SQL injection protection
- Session-based authentication
- Role-based access control
- Input validation and sanitization
- XSS prevention with htmlspecialchars
- Transaction support for database operations

**Database**
- MySQL 5.7+ with InnoDB engine
- 5 tables: _users, _accounts, _plans, _transactions, _currencies
- Proper indexes for performance
- Foreign key relationships
- UTF-8MB4 character set support

**Security**
- Session-based authentication
- SQL injection protection via PDO
- XSS prevention
- Role-based permissions
- Secure password storage (MD5 - upgrade to bcrypt recommended)
- Input validation on all endpoints

**API Integration**
- cURL-based HTTP client
- HTTP Basic Authentication
- JSON request/response format
- Multi-server support
- Error handling and logging

#### Changed - Major Updates

**Expired Accounts Logic**
- Removed status field check from expiration calculations
- Status field (ON/OFF) is now for administrative control only
- Accounts are "not renewed" if `end_date < current_date`
- Updated labels to reflect "Expired & Not Renewed"
- Updated all functions to use consistent date-based logic

**Reports Tab**
- Added dynamic date range filters
- Added custom input for any date range
- Renamed "Expired Accounts in Past" to "Expired & Not Renewed"
- Added real-time label updates showing selected period
- Improved visual hierarchy with gradient cards

**Dashboard Statistics**
- Added "Expired Last Month" card
- Enhanced visual design with gradients
- Added tooltips and descriptions
- Improved color coding for status

#### Fixed

**Database Sync**
- Fixed invalid date handling (0000-00-00 dates now converted to NULL)
- Fixed duplicate account prevention
- Fixed reseller assignment (default to admin user ID 1)

**UI/UX**
- Fixed pagination not updating on search
- Fixed theme toggle icon persistence
- Fixed responsive layout on mobile devices
- Fixed table overflow on small screens

**Reports**
- Fixed "Expired & Not Renewed" count accuracy
- Fixed date range calculations
- Fixed custom input validation
- Fixed label updates on filter change

#### Security

**Implemented**
- PDO prepared statements for all database queries
- Session validation on all authenticated endpoints
- Role-based access control for admin-only operations
- Input validation and sanitization
- XSS prevention with proper escaping

**Known Issues (To Be Fixed in Future Releases)**
- MD5 password hashing (should upgrade to bcrypt)
- SSL verification disabled in cURL (should enable in production)
- No CSRF token protection (should implement)
- No rate limiting (should implement)

#### Documentation

- Created comprehensive README.md
- Created MVP.md with product roadmap
- Created INSTALLATION.md with step-by-step setup
- Created API_DOCUMENTATION.md with all endpoints
- Created ARCHITECTURE.md with technical details
- Created CHANGELOG.md (this file)

#### Known Limitations

**Technical**
- Sync uses DELETE all + INSERT all strategy (no incremental sync)
- Client-side filtering loads all accounts in memory
- File-based PHP sessions (not scalable across multiple servers)
- Single database (no replication)

**Business**
- No email automation
- No payment gateway integration
- No automated renewals
- No SMS notifications
- No mobile app

**Scale**
- Tested with up to 10,000 accounts
- Sync may timeout with 50,000+ accounts
- Client-side filtering limited by browser memory

---

## [Unreleased] - Future Enhancements

### Planned for Version 1.1.0

#### High Priority
- [ ] Payment Gateway Integration (Stripe, PayPal)
- [ ] Automated Renewals with auto-charge
- [ ] Email Notifications for expiration warnings
- [ ] SMS Alerts via Twilio
- [ ] Incremental Sync (update changed accounts only)
- [ ] Automated Daily Backups
- [ ] Password Reset Self-Service
- [ ] Upgrade Password Hashing to Bcrypt

#### Medium Priority
- [ ] Advanced Search with multi-field filters
- [ ] Bulk Operations (mass update, delete, extend)
- [ ] Export Reports (CSV, PDF, Excel)
- [ ] API Documentation (Swagger/OpenAPI)
- [ ] Audit Logs for all user actions
- [ ] Two-Factor Authentication
- [ ] Custom Branding for resellers
- [ ] CSRF Token Protection
- [ ] Rate Limiting

#### Low Priority
- [ ] Mobile App (iOS and Android)
- [ ] Live Chat Support
- [ ] Advanced Analytics Dashboard
- [ ] Customer Self-Service Portal
- [ ] Referral System for resellers
- [ ] Multi-language Support (i18n)
- [ ] Auto Dark Mode (system theme detection)

### Planned for Version 2.0.0 (Major Overhaul)

#### Architecture
- [ ] Microservices Architecture
- [ ] Redis Caching for performance
- [ ] Queue System for background jobs
- [ ] Load Balancing support
- [ ] Database Sharding for horizontal scaling
- [ ] CDN Integration
- [ ] GraphQL API

#### Security
- [ ] OAuth 2.0 Authentication
- [ ] API Key Management
- [ ] IP Whitelisting
- [ ] Advanced Firewall Rules
- [ ] DDoS Protection
- [ ] Security Audit Logging

#### Features
- [ ] Real-Time Dashboard with WebSockets
- [ ] Advanced Business Intelligence
- [ ] Machine Learning for churn prediction
- [ ] Automated Marketing Campaigns
- [ ] Customer Lifetime Value Analysis

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.1.0 | 2025-11-21 | Currency standardization (IRT→IRR) and UI improvements |
| 1.0.0 | 2025-01-17 | Initial production release |
| 0.9.0 | 2025-01-10 | Beta testing phase |
| 0.5.0 | 2024-12-15 | Alpha release with core features |

---

## Migration Guide

### Upgrading to 1.0.0

This is the initial release. No migration required.

### Future Upgrades

When upgrading from 1.0.0 to future versions:

1. **Backup Everything**
   ```bash
   tar -czf showbox_backup_$(date +%Y%m%d).tar.gz /var/www/showbox
   mysqldump -u root -p showboxt_panel > backup.sql
   ```

2. **Read CHANGELOG**
   - Check for breaking changes
   - Review new features
   - Note deprecated features

3. **Run Database Migrations**
   ```bash
   mysql -u root -p showboxt_panel < migrations/v1.1.0.sql
   ```

4. **Update Configuration**
   - Compare config.php with new version
   - Add new settings
   - Keep your credentials

5. **Test Thoroughly**
   - Test login
   - Test account sync
   - Test all features

---

## Breaking Changes

### Version 1.0.0

No breaking changes (initial release).

### Future Versions

Breaking changes will be clearly documented here.

---

## Deprecations

### Version 1.0.0

No deprecations in initial release.

### Future Versions

- MD5 password hashing will be deprecated in v1.1.0
- Migration to bcrypt will be provided

---

## Contributors

### Core Team
- ShowBox Development Team

### Special Thanks
- Claude (AI Assistant) - Documentation and Architecture
- ShowBox Support Team

---

## Support

For version-specific support:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin
- **Documentation**: README.md

---

## License

Proprietary - ShowBox IPTV Billing System
All rights reserved.

---

**Maintained by:** ShowBox Development Team
**Last Updated:** January 2025
