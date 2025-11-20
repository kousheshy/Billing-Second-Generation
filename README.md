# ShowBox Billing System

## System Status: ✅ FULLY OPERATIONAL

Your IPTV/STB billing management system is now running with a complete UI integrated with your backend APIs!

## Quick Start

### Access the System
1. **Open your browser** and go to: `http://localhost:8000/index.html`
2. **Login credentials:**
   - Username: `admin`
   - Password: `admin`

### What's Running
- ✅ PHP Server: `http://localhost:8000`
- ✅ MySQL Database: `showboxt_panel`
- ✅ Frontend UI: Full dashboard with all features
- ✅ Backend APIs: All integrated and working

## Features

### 1. Account Management
- **Add new customer accounts** with MAC addresses
- **Update accounts** (plans, status, details)
- **Delete accounts**
- **View all accounts** in a table
- Automatic account number generation
- Email notifications (when configured)

### 2. Reseller Management
- **Create resellers** with credit balances
- **Manage permissions** and limits
- **Track reseller balances**
- **Set max users** per reseller
- Multi-currency support (GBP, USD, EUR)

### 3. Subscription Plans
- **Create plans** with pricing and duration
- **Multi-currency pricing**
- **Set expiry days**
- **Delete plans**
- Plans automatically populate in account creation

### 4. Transaction History
- **View all transactions**
- **Track credits/debits**
- **Filter by user** (reseller-specific view)
- Automatic transaction logging

### 5. Settings
- **Change password**
- **View balance**
- **View account statistics**

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

## Support

For issues or questions:
- Check error_log file for PHP errors
- Check browser console for JavaScript errors
- Review server terminal for request logs

## License

Proprietary - ShowBox IPTV Billing System

---

**Version:** 1.0
**Last Updated:** November 20, 2025
**Status:** Production Ready ✅
