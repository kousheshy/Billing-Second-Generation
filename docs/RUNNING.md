# Running the Application

Quick reference guide for running the ShowBox Billing Panel for testing and development.

## Overview

This application runs **natively without Docker or containers** using:
- **MySQL** - Database server (via Homebrew on macOS)
- **PHP Built-in Server** - Development web server
- **No containerization** - Direct native execution

---

## Prerequisites

### Required Software
- PHP 7.4 or higher
- MySQL 5.7 or higher
- macOS with Homebrew (for this setup)

### Verify Installation
```bash
# Check PHP version
php -v

# Check MySQL installation
brew services list | grep mysql
```

---

## Starting the Application

### Step 1: Start MySQL

```bash
# Start MySQL service
brew services start mysql

# Verify MySQL is running
brew services list | grep mysql
# Should show: mysql started
```

### Step 2: Start PHP Server

```bash
# Navigate to project directory
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"

# Start PHP built-in development server
php -S localhost:8000
```

**Server will start and display:**
```
PHP 7.4.x Development Server (http://localhost:8000) started
```

### Step 3: Access the Application

Open your browser and navigate to:
```
http://localhost:8000/index.html
```

**Default Login Credentials:**
- Username: `admin`
- Password: `admin`

---

## Stopping the Application

### Stop PHP Server
```bash
# Press Ctrl+C in the terminal where PHP server is running
```

### Stop MySQL (if needed)
```bash
brew services stop mysql
```

---

## Checking if Application is Running

### Check if PHP Server is Running
```bash
# Check if port 8000 is in use
lsof -i :8000

# Should show something like:
# php     68478 kambiz    7u  IPv6 ... TCP localhost:8000 (LISTEN)
```

### Check if MySQL is Running
```bash
# Check MySQL service status
brew services list | grep mysql

# Or check MySQL processes
ps aux | grep mysql
```

---

## Quick Start Commands

### Start Everything
```bash
# Start MySQL
brew services start mysql

# Start PHP Server (in project directory)
cd "/Users/kambiz/Documents/Visual Studio Projects/Current Billing Shahrokh"
php -S localhost:8000
```

### Access Application
```
http://localhost:8000/index.html
```

---

## Troubleshooting

### Port 8000 Already in Use

**Problem:** `Failed to listen on localhost:8000 (reason: Address already in use)`

**Solution 1:** Server is already running - just access it
```bash
# Check what's running
lsof -i :8000
# If PHP is running, you're good to go
```

**Solution 2:** Use a different port
```bash
php -S localhost:8001
# Then access: http://localhost:8001/index.html
```

**Solution 3:** Kill the existing process
```bash
# Find the process ID
lsof -i :8000

# Kill it (replace PID with actual process ID)
kill -9 <PID>

# Then start server again
php -S localhost:8000
```

### MySQL Not Running

**Problem:** Database connection errors

**Solution:**
```bash
# Start MySQL
brew services start mysql

# If that fails, try restarting
brew services restart mysql

# Check status
brew services list | grep mysql
```

### Can't Access Application

**Problem:** Browser shows "Cannot connect"

**Checklist:**
1. Is PHP server running? Check with `lsof -i :8000`
2. Is MySQL running? Check with `brew services list`
3. Are you using the correct URL? `http://localhost:8000/index.html`
4. Check terminal for PHP server errors

---

## Development vs Production

### Current Setup: Development
- Uses PHP built-in server (`php -S`)
- MySQL on localhost with no password
- Port 8000
- No HTTPS
- Good for: Testing, development, local work

### Production Deployment
For production, you should use:
- Apache or Nginx web server
- Secure MySQL with password
- HTTPS/SSL certificates
- Proper file permissions
- See [INSTALLATION.md](INSTALLATION.md) for production setup

---

## Running on Different Systems

### macOS (Current Setup)
```bash
brew services start mysql
cd "path/to/project"
php -S localhost:8000
```

### Linux (Ubuntu/Debian)
```bash
# Start MySQL
sudo systemctl start mysql

# Start PHP server
cd /path/to/project
php -S localhost:8000
```

### Windows
```cmd
# Start MySQL (if installed via XAMPP/WAMP)
# Or use MySQL service manager

# Start PHP server
cd C:\path\to\project
php -S localhost:8000
```

---

## Docker Alternative

**Note:** This application currently runs natively without Docker.

If you want to containerize it:
- A `Dockerfile` would need to be created
- A `docker-compose.yml` for orchestration
- Network configuration between PHP and MySQL containers

See project maintainer if Docker deployment is required.

---

## Configuration Files

### Database Connection
Edit `config.php` for database settings:
```php
$ub_main_db = "showboxt_panel";
$ub_db_host = "localhost";
$ub_db_username = "root";
$ub_db_password = "";
```

### Server URLs
Configure Stalker Portal API in `config.php`:
```php
$SERVER_1_ADDRESS = "http://your-server.com";
$WEBSERVICE_USERNAME = "api_username";
$WEBSERVICE_PASSWORD = "api_password";
```

---

## Testing Checklist

After starting the application:

- [ ] MySQL service is running
- [ ] PHP server is running on port 8000
- [ ] Can access login page at http://localhost:8000/index.html
- [ ] Can login with admin/admin credentials
- [ ] Dashboard loads successfully
- [ ] Can view accounts, resellers, plans tabs
- [ ] Auto-sync works on login

---

## Quick Reference

| Component | Command | Port | Status Check |
|-----------|---------|------|--------------|
| MySQL | `brew services start mysql` | 3306 | `brew services list` |
| PHP Server | `php -S localhost:8000` | 8000 | `lsof -i :8000` |
| Application | Open browser | - | http://localhost:8000/index.html |

---

## Support

For issues running the application:
- Check [INSTALLATION.md](INSTALLATION.md) for detailed setup
- Check [README.md](README.md) for troubleshooting
- Review terminal output for error messages
- Check browser console for JavaScript errors

---

**Version:** 1.0.0
**Last Updated:** January 2025
**Deployment Method:** Native PHP + MySQL (No Docker)
