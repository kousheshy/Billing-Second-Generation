# Changelog

All notable changes to the ShowBox Billing Panel will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.7.4] - 2025-11-22

### Added - Theme Propagation & Enhanced MAC Input

**Automatic Theme Propagation**
- **Theme Change Propagation**: When admin changes a reseller's theme, ALL existing accounts under that reseller are automatically updated
- **Warning System**: Clear warning message in Edit Reseller modal informing admins that theme changes affect all accounts
- **Bulk Update Process**: Efficient batch processing of account theme updates with detailed logging
- **Progress Feedback**: Success/failure statistics for theme propagation operations
- **Smart Detection**: Only triggers propagation when theme actually changes (not on every reseller edit)
- **Error Handling**: Graceful handling of partial failures with detailed error reporting
- **Server-Safe Delays**: 0.1 second delay between updates to prevent server overload

**Enhanced MAC Address Input**
- **Robust Initialization**: Triple-layer initialization strategy ensures MAC input component works for all users
- **Immediate Init**: MAC inputs initialized right when DOM loads
- **Delayed Init**: Re-initialization after 500ms to catch dynamically loaded elements
- **Final Init**: Final initialization after 2 seconds to ensure everything is loaded
- **Debug Logging**: Console logging for troubleshooting MAC input initialization
- **Universal Application**: Works identically for both admin and reseller users
- **Smart Duplicate Prevention**: Prevents re-initializing already initialized inputs

**Warning Messages**
- **Edit Reseller Modal**: "⚠️ Warning: Changing the theme will update the Stalker Portal theme for ALL existing accounts under this reseller. This change will take effect immediately."
- **Update Feedback**:
  - Full success: "Reseller updated successfully. Theme changed for all X accounts."
  - Partial success: "Reseller updated. Theme changed for X/Y accounts (Z failed)."

**Technical Implementation**
- New endpoint: `update_reseller_accounts_theme.php` - Standalone bulk theme update endpoint (for future use)
- Updated `update_reseller.php`:
  - Theme change detection logic
  - Bulk account update on theme change
  - Detailed error logging with success/failure tracking
  - Conditional response messages based on update results
- Updated `dashboard.html`:
  - Warning caption in Edit Reseller form
- Updated `dashboard.js`:
  - Enhanced `initAllMacInputs()` with console logging
  - Triple initialization strategy for MAC inputs
  - Warning message handling for theme updates

**Files Modified**
- `update_reseller_accounts_theme.php` - NEW (standalone bulk update endpoint)
- `update_reseller.php` - Added theme propagation logic
- `dashboard.html` - Added warning message in Edit Reseller modal
- `dashboard.js` - Enhanced MAC input initialization and theme update feedback

---

## [1.7.3] - 2025-11-22

### Added - Reseller Theme Management

**Theme Assignment Feature**
- New theme management system for resellers and their subscribers
- Resellers can be assigned a specific portal theme that applies to all their subscribers
- Theme dropdown in Add/Edit Reseller forms with 9 available themes:
  - **HenSoft-TV Realistic-Centered SHOWBOX** (Default)
  - HenSoft-TV Realistic-Centered
  - HenSoft-TV Realistic-Dark
  - HenSoft-TV Realistic-Light
  - Cappuccino
  - Digital
  - Emerald
  - Graphite
  - Ocean Blue
- Automatic theme application when creating new subscriber accounts
- Theme inheritance: all subscribers under a reseller automatically receive the reseller's theme
- Theme persistence: theme is synchronized to Stalker Portal server
- Theme updates when editing accounts to ensure consistency with reseller's current theme

**Technical Implementation**
- New endpoint: `get_themes.php` - Fetches available themes list
- Updated `add_reseller.php` and `update_reseller.php` to handle theme field
- Updated `add_account.php` to apply reseller's theme after account creation
- Updated `edit_account.php` to sync theme when updating accounts
- Theme dropdown dynamically populated from server
- Default theme pre-selected in Add Reseller form
- Uses existing server-side script method (`/stalker_portal/update_account.php`) for reliable theme updates
- Theme stored in `_users` table `theme` column (VARCHAR 50)

**Files Modified**
- `get_themes.php` - NEW
- `add_reseller.php` - Theme field handling (already existed)
- `update_reseller.php` - Theme field handling (already existed)
- `edit_account.php` - Added theme sync on account update
- `dashboard.html` - Added theme dropdown to reseller forms
- `dashboard.js` - Added theme loading and population logic

---

## [1.7.2] - 2025-11-22

### Added - STB Device Control System

**STB Control Features**
- New "STB Control" tab in dashboard for managing Set-Top Box devices via Stalker Portal API
- Send 8 different control events to STB devices:
  - **Reboot** - Restart the device
  - **Reload Portal** - Refresh the portal interface
  - **Update Channels** - Sync latest channel list
  - **Play Channel** - Switch to specific TV channel (requires channel ID input)
  - **Play Radio Channel** - Switch to specific radio channel (requires channel ID input)
  - **Update Image** - Update device firmware/image
  - **Show Menu** - Display portal menu on device
  - **Cut Off** - Disable service to device
- Send custom text messages to STB devices with real-time delivery
- Recent Actions history panel showing last 10 commands with timestamps
- Event-specific dynamic input fields (channel ID field appears only for play channel events)
- Responsive two-column grid layout for control forms
- Permission-based access (super admin and reseller admin only)
- Device ownership verification (resellers can only control their own devices)
- Real-time success/error feedback with detailed messages
- Comprehensive logging of all STB actions

**Smart MAC Address Input Component**
- Intelligent MAC address input with enforced vendor prefix (00:1A:79:)
- Non-editable prefix that cannot be deleted or modified by user
- Auto-formatting with colons inserted automatically after every 2 hex digits
- Real-time validation as user types
- Visual error feedback with red borders, background highlighting, and inline error messages
- Cursor management to maintain prefix integrity and prevent accidental prefix modification
- Hex-only input validation (accepts 0-9, A-F, case insensitive)
- Automatic uppercase conversion for all hex characters
- Applied universally to all MAC input fields:
  - Add Account form
  - STB Event form
  - STB Message form
  - Edit Account form (future)
- Validates on blur with detailed, actionable error messages
- Pattern validation: `00:1A:79:XX:XX:XX` where X = hex digit
- Prevents form submission if MAC address is invalid
- Reusable JavaScript functions for easy integration

**New API Endpoints**
- `send_stb_event.php` - Send control events to Stalker Portal STB devices
  - Validates user permissions (admin or reseller admin)
  - Verifies device ownership for resellers
  - Validates event type and required parameters
  - Formats data correctly for Stalker API (uses `$` separator for channel events)
  - Returns success/error response with detailed messages
- `send_stb_message.php` - Send text messages to Stalker Portal STB devices
  - Validates user permissions
  - Verifies device ownership
  - URL-encodes message content
  - Logs all message delivery attempts

### Changed

**UI/UX Improvements**
- Added STB Control tab to main navigation (6th tab in dashboard)
- Responsive two-column layout for STB forms that stacks on mobile
- Clean form design with consistent styling matching existing panels
- History list with hover effects and smooth CSS transitions
- Mobile-responsive single-column layout (breakpoint at 768px)
- Monospace font (Courier New) for MAC address inputs for clear character alignment
- Enhanced visual feedback for all form interactions
- Professional card-based layout matching system design
- Gradient headers and modern shadows
- Accessible form labels and helpful placeholders

**Form Validation**
- MAC address validation integrated into all forms before submission
- Pre-submission validation prevents invalid API calls and server errors
- Error messages displayed inline with smooth slide-down animations
- Focus management after validation errors for better UX
- Clear error messages specify exact problem (e.g., "semicolon instead of colon")
- Form submission blocked until all validation passes

**JavaScript Architecture**
- Added reusable MAC validation functions:
  - `validateMacAddress(mac)` - Core validation logic
  - `initMacAddressInput(inputElement)` - Initialize MAC input behavior
  - `showMacError(inputElement, message)` - Display validation errors
  - `hideMacError(inputElement)` - Clear error messages
  - `validateMacInput(inputElement)` - Pre-submission check
  - `initAllMacInputs()` - Auto-detect and initialize all MAC inputs
- Added STB control functions:
  - `sendStbEvent(event)` - Handle event form submission
  - `sendStbMessage(event)` - Handle message form submission
  - `addStbHistory(type, action, mac)` - Update history list
- Event listeners for dynamic form behavior (channel ID field toggle)
- Auto-initialization on page load and modal open events

**CSS Styling**
- Added 208 lines of new CSS for STB Control and MAC input components
- STB control container with responsive grid layout
- Form sections with clean borders and padding
- History list with alternating row highlights
- MAC input error styles with red theme
- Smooth animations for error messages (slideDown keyframe)
- Disabled state styles for submit buttons
- Mobile breakpoints for optimal viewing on all devices

### Fixed

**Input Handling**
- MAC address inputs now prevent invalid characters (only 0-9, A-F, colon allowed)
- Prefix enforcement prevents accidental deletion via backspace or delete keys
- Proper cursor positioning in MAC inputs (always after prefix)
- Auto-uppercase conversion for hex digits ensures consistent format
- Click handlers prevent cursor placement before prefix
- Input sanitization removes spaces, semicolons, and other invalid characters

**API Integration**
- Fixed Stalker Portal API endpoint for sending events (use `send_event` not `stb_event`)
- Fixed parameter format for channel events (use `$` separator not `&`)
  - Correct: `event=play_channel$channel_number=123`
  - Incorrect: `event=play_channel&channel_number=123`
- Fixed parameter name for channel selection (`channel_number` not `channel_id`)
- Improved error handling with try-catch blocks
- Added comprehensive error logging for debugging

**Permission System**
- Fixed permission checks for STB control (allow reseller admin)
- Added device ownership verification for resellers
- Prevented regular users from accessing STB control features
- Clear permission denied messages with helpful guidance

---

## [1.7.1] - 2025-11-22

### Added - Phone Number Support

**Phone Number Integration**
- Added `phone_number` VARCHAR(50) column to `_accounts` table (after email column)
- Phone number field in Add Account form (optional field)
- Phone number field in Edit Account form (optional field)
- Phone number sent to Stalker Portal API during account creation via `&phone=` parameter
- Phone number synced FROM Stalker Portal during account sync (Stalker is single source of truth)
- Phone number displayed in accounts table as new column (shows blank if NULL)
- Phone number included in Excel export reports (XLSX format)
- Phone number included in PDF export reports (with proper formatting)
- New database migration utility: `add_phone_column.php`
  - Checks if column exists before adding
  - Safe to run multiple times (idempotent)
  - Provides user-friendly console output
  - No data loss during migration

**Data Integrity & Sync Logic**
- **Stalker Portal is the single source of truth** for phone numbers
- Local database phone numbers are always overwritten during account sync
- If Stalker Portal has no phone number, local database sets field to NULL
- No fallback to local values - ensures data consistency
- Prevents data inconsistencies between billing panel and Stalker server
- Phone numbers sent TO Stalker when creating/editing accounts
- Phone numbers fetched FROM Stalker when syncing accounts
- Bidirectional sync maintains data integrity across systems

### Fixed

**UI/UX Improvements**
- Fixed pagination not resetting after account deletion
  - Issue: Deleted account remained visible until page navigation
  - Solution: Clear `filteredAccounts` array and reset `currentPage` to 1
- Fixed search term persisting after account deletion
  - Issue: Search filter stayed active showing deleted account
  - Solution: Clear `searchTerm` when loading accounts after deletion
- Cleared filtered accounts state when accounts are deleted
  - Ensures immediate visual feedback when deleting accounts
  - No need to refresh page or navigate to see changes
- Improved colspan values in table rendering (8 → 9 columns)
  - Fixed "No accounts found" message spanning wrong number of columns
  - Ensures proper table formatting with new phone column
- Better user experience when managing accounts
  - Instant feedback on all operations
  - Consistent table display across all states

**Delete Account Bug**
- Root cause: `filteredAccounts` array not cleared after deletion
- Symptom: "Account deleted successfully" message shown but account still visible
- Fix applied in `dashboard.js` lines 476-479:
  ```javascript
  // Clear any active filters/search so deleted items disappear immediately
  accountsPagination.filteredAccounts = [];
  accountsPagination.searchTerm = '';
  accountsPagination.currentPage = 1; // Reset to first page
  ```

### Changed

**Database Schema**
- Updated `add_account.php` to INSERT phone_number field (line 425-426)
  - Captures phone from POST data
  - Sends to Stalker Portal as `&phone=` parameter
  - Stores in local database during INSERT
- Updated `edit_account.php` to UPDATE phone_number field (lines 150, 190-192)
  - Updates phone in local database
  - Sends updated phone to Stalker Portal via API
  - Maintains sync between systems
- Updated `sync_accounts.php` to sync phone numbers from Stalker (line 157)
  - Fetches phone from Stalker Portal response: `$stalker_user->phone`
  - Sets to NULL if Stalker has no phone number
  - No fallback to local values (removed in v1.7.1)
  - Example: `$phone_number = $stalker_user->phone ?? null;`
- Updated `get_accounts.php` query (implicit - SELECT a.* returns all columns)
  - Automatically includes phone_number in results
  - No code changes needed due to SELECT * pattern
- Modified `dashboard.js` to display phone_number in UI and exports
  - Line 833: Added phone column to account table rendering
  - Line 1563: Fixed Edit Account modal to use `phone_number` field
  - Line 2056: Added phone to Excel export (XLSX library)
  - Line 2109: Added phone to PDF export (jsPDF autoTable)
- Updated `dashboard.html` to include phone column in accounts table
  - Line 132: Added `<th>Phone</th>` column header
  - Displays phone number or empty cell if NULL
  - Maintains consistent table layout

**File Changes Summary**
- `add_phone_column.php` - Created (new migration utility)
- `add_account.php` - Modified (send phone to Stalker, save to DB)
- `edit_account.php` - Modified (update phone in Stalker and DB)
- `sync_accounts.php` - Modified (fetch phone from Stalker only)
- `get_accounts.php` - No changes needed (SELECT * includes new column)
- `dashboard.html` - Modified (added phone column header)
- `dashboard.js` - Modified (display and export phone number)
- `dashboard.css` - No changes needed (column inherits existing styles)

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
