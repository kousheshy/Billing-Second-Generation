# ShowBox Billing Panel - System Architecture

Technical architecture documentation for the ShowBox Billing Panel.

**Version:** 1.1.0
**Last Updated:** November 21, 2025

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Component Details](#component-details)
4. [Data Flow](#data-flow)
5. [Database Schema](#database-schema)
6. [Security Architecture](#security-architecture)
7. [Integration Points](#integration-points)
8. [Scalability Considerations](#scalability-considerations)

---

## System Overview

The ShowBox Billing Panel is a three-tier web application:

**Presentation Layer (Frontend)**
- HTML5, CSS3, JavaScript (ES6+)
- Responsive design with dark/light themes
- Client-side rendering and filtering
- LocalStorage for user preferences

**Application Layer (Backend)**
- PHP 7.4+ RESTful APIs
- Session-based authentication
- Business logic and validation
- API integration layer

**Data Layer**
- MySQL 5.7+ relational database
- PDO for database abstraction
- Prepared statements for security
- Transaction support

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT BROWSER                        │
│  ┌────────────────┐  ┌─────────────┐  ┌──────────────────┐ │
│  │  dashboard.html │  │dashboard.css│  │  dashboard.js    │ │
│  │  (UI Structure)│  │  (Styling)  │  │  (Logic/API)     │ │
│  └────────┬───────┘  └─────────────┘  └────────┬─────────┘ │
│           │                                      │           │
│           │         HTTP/HTTPS Requests          │           │
└───────────┼──────────────────────────────────────┼───────────┘
            │                                      │
            ▼                                      ▼
┌─────────────────────────────────────────────────────────────┐
│                      WEB SERVER                              │
│                 (Apache/Nginx/PHP)                           │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              PHP APPLICATION LAYER                      │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │ │
│  │  │ Authentication│  │   Business   │  │ API          │ │ │
│  │  │    Layer      │  │    Logic     │  │ Integration  │ │ │
│  │  │               │  │              │  │              │ │ │
│  │  │ - login.php   │  │ - get_*.php  │  │ - api.php    │ │ │
│  │  │ - logout.php  │  │ - add_*.php  │  │ - sync_*.php │ │ │
│  │  │ - get_user_   │  │ - update_*   │  │              │ │ │
│  │  │   info.php    │  │ - remove_*   │  │              │ │ │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘ │ │
│  │         │                  │                  │         │ │
│  │         └──────────────────┼──────────────────┘         │ │
│  │                            │                            │ │
│  │                  ┌─────────▼────────┐                  │ │
│  │                  │   config.php     │                  │ │
│  │                  │  (Configuration) │                  │ │
│  │                  └─────────┬────────┘                  │ │
│  └────────────────────────────┼───────────────────────────┘ │
└───────────────────────────────┼─────────────────────────────┘
                                │
                ┌───────────────┴───────────────┐
                │                               │
                ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────────┐
│   MYSQL DATABASE        │     │   STALKER PORTAL API        │
│                         │     │                             │
│  ┌──────────────────┐   │     │  Endpoints:                 │
│  │   _users         │   │     │  - /accounts/               │
│  │   _accounts      │   │     │  - /stb/                    │
│  │   _plans         │   │     │  - /users/                  │
│  │   _transactions  │   │     │  - /stb_msg/                │
│  │   _currencies    │   │     │  - /send_event/             │
│  └──────────────────┘   │     │                             │
│                         │     │  Authentication:            │
│  Indexes, Constraints   │     │  HTTP Basic Auth            │
│  Transactions, Locks    │     │                             │
└─────────────────────────┘     └─────────────────────────────┘
```

---

## Component Details

### 1. Frontend Components

#### dashboard.html
**Purpose:** Main UI structure
**Key Sections:**
- Navigation bar with user info
- Statistics cards (Total, Active, Plans, Expiring, Expired)
- Tab navigation (Accounts, Resellers, Plans, Transactions, Reports, Settings)
- Account table with search and pagination
- Modal forms for add/edit operations

**Features:**
- Semantic HTML5 markup
- Accessibility features
- Responsive grid layout

#### dashboard.css
**Purpose:** Styling and themes
**Features:**
- CSS Variables for theming
- Dark and light mode support
- Responsive breakpoints
- Gradient backgrounds
- Smooth transitions
- Modern card-based design

**Theme System:**
```css
:root[data-theme="light"] {
  --bg-primary: #ffffff;
  --text-primary: #1a1a1a;
  --primary: #667eea;
}

:root[data-theme="dark"] {
  --bg-primary: #1a1a2e;
  --text-primary: #f0f0f0;
  --primary: #764ba2;
}
```

#### dashboard.js
**Purpose:** Client-side logic and API communication
**Key Functions:**

**Authentication:**
- `checkAuth()` - Validate session and trigger auto-sync
- `autoSyncAccounts()` - Automatic account synchronization on login
- `logout()` - Destroy session

**Data Management:**
- `loadAccounts()` - Fetch and render accounts
- `renderAccountsPage()` - Pagination and display
- `searchAccounts()` - Client-side filtering

**Reports & Analytics:**
- `updateExpiringSoonCount()` - Calculate expiring accounts
- `updateExpiredLastMonthCount()` - Calculate expired accounts
- `generateReports()` - Comprehensive statistics
- `updateDynamicReports()` - Custom date range filtering

**Expiration Logic:**
```javascript
// Core expiration detection
function isExpired(endDate) {
    const now = new Date();
    const expirationDate = new Date(endDate);
    return expirationDate < now;
}

// Expired & Not Renewed logic
// An account is "not renewed" if end_date is still in the past
if (expirationDate >= expiredStartDate && expirationDate < now) {
    expiredNotRenewedCount++;
}
```

**Currency Formatting:**
- `getCurrencySymbol(currencyName)` - Returns currency symbol or code
  - IRR returns "IRR " (with space)
  - USD returns "$", EUR returns "€", GBP returns "£"
  - Null/undefined defaults to "IRR "
- `formatBalance(amount, currencyName)` - Formats numbers with proper separators
  - IRR: Comma thousand separator (6,500,000)
  - Other currencies: Two decimal places (10000.00)
  - Handles null/undefined amounts (defaults to 0)

**Theme Management:**
- `toggleTheme()` - Switch between light/dark
- `initTheme()` - Load saved preference

---

### 2. Backend Components

#### Authentication Layer

**login.php**
```php
// Validate credentials
// Create session
// Return user info
session_start();
$_SESSION['user_id'] = $user['id'];
```

**get_user_info.php**
```php
// Check session
// Fetch user details
// Return JSON response
```

**logout.php**
```php
// Destroy session
// Redirect to login
session_destroy();
```

#### Business Logic Layer

**Account Management:**
- `get_accounts.php` - Fetch accounts (filtered by reseller)
- `add_account.php` - Create account locally and on Stalker
- `update_account.php` - Update account details
- `remove_account.php` - Delete account (admin only)
- `change_status.php` - Enable/disable account

**Reseller Management:**
- `get_resellers.php` - Fetch all resellers
- `add_reseller.php` - Create reseller with balance
- `update_reseller.php` - Update reseller details
- `remove_reseller.php` - Delete reseller

**Plan Management:**
- `get_plans.php` - Fetch subscription plans
- `add_plan.php` - Create new plan
- `remove_plan.php` - Delete plan

**Transaction Management:**
- `get_transactions.php` - Fetch transaction history
- `new_transaction.php` - Log transaction
- `adjust_credit.php` - Modify reseller balance

#### API Integration Layer

**api.php**
```php
function api_send_request($url, $username, $password, $case, $op, $mac, $data) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, $username.":".$password);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $op);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    $result = curl_exec($curl);
    curl_close($curl);
    
    return $result;
}
```

**sync_accounts.php**
```php
// Get current user info (admin or reseller)
// For admin: DELETE all accounts
// For reseller: DELETE only their accounts WHERE reseller = user_id
// Get existing reseller mappings to preserve ownership
// Fetch from Stalker Portal API /accounts/
// Map and normalize data:
//   - login → username
//   - stb_mac → mac
//   - full_name, email, tariff_plan, end_date, status
//   - Handle invalid dates (0000-00-00 → NULL)
// Determine reseller assignment (preserve existing or assign to current user)
// For resellers: Skip accounts not assigned to them
// Insert into local database
// Return sync statistics (synced count, skipped count, total)
```

#### Configuration Layer

**config.php**
```php
// System settings
$PANEL_NAME = "ShowBox";
$admins_only = false;

// Database credentials
$ub_main_db = "showboxt_panel";
$ub_db_host = "localhost";
$ub_db_username = "root";
$ub_db_password = "";

// Stalker Portal API
$WEBSERVICE_USERNAME = "admin";
$WEBSERVICE_PASSWORD = "password";
$WEBSERVICE_BASE_URL = "http://server/stalker_portal/api/";
```

---

### 3. Database Layer

#### Connection Management

**db.php** (inferred from common practice):
```php
try {
    $pdo = new PDO(
        "mysql:host=$ub_db_host;dbname=$ub_main_db",
        $ub_db_username,
        $ub_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed");
}
```

#### Query Execution Pattern

**Using Prepared Statements:**
```php
// SELECT query
$stmt = $pdo->prepare('SELECT * FROM _accounts WHERE username = ?');
$stmt->execute([$username]);
$account = $stmt->fetch();

// INSERT query
$stmt = $pdo->prepare('INSERT INTO _accounts (username, mac, end_date) VALUES (?, ?, ?)');
$stmt->execute([$username, $mac, $end_date]);

// UPDATE query
$stmt = $pdo->prepare('UPDATE _accounts SET end_date = ? WHERE username = ?');
$stmt->execute([$new_end_date, $username]);

// DELETE query
$stmt = $pdo->prepare('DELETE FROM _accounts WHERE username = ?');
$stmt->execute([$username]);
```

---

## Data Flow

### Flow 1: User Login with Auto-Sync

```
1. User enters credentials in index.html
   ↓
2. JavaScript sends POST to login.php
   ↓
3. login.php validates against _users table
   ↓
4. If valid: Create session, return user info
   If invalid: Return error
   ↓
5. JavaScript redirects to dashboard.html
   ↓
6. dashboard.js calls checkAuth()
   ↓
7. Session validated, show loading overlay
   ↓
8. Call autoSyncAccounts() automatically:
   a. POST to sync_accounts.php
   b. For Admin: DELETE all accounts, sync all from Stalker
   c. For Reseller: DELETE only their accounts, sync only theirs
   d. Preserve existing reseller-to-account mappings
   e. No timeout - wait for completion
   ↓
9. After sync completes:
   a. Load accounts from local database
   b. Load transactions
   c. Load plans
   d. For admin: Load resellers
   e. Hide loading overlay
   ↓
10. Dashboard ready for user interaction
```

**Key Innovation:** Users see only "Loading dashboard..." and never know accounts are syncing from Stalker Portal. The experience feels like a normal page load.

### Flow 2: Account Creation

```
1. User fills form in dashboard.html
   ↓
2. JavaScript validates input
   ↓
3. POST request to add_account.php with JSON data
   ↓
4. add_account.php:
   a. Validate session
   b. Validate input data
   c. Check for duplicate username
   d. Insert into _accounts table
   e. Call Stalker Portal API (server 1)
   f. Call Stalker Portal API (server 2)
   g. Return success/error response
   ↓
5. JavaScript displays result
   ↓
6. Reload accounts table
```

### Flow 3: Account Synchronization

**Auto-Sync (on login):**
```
1. User logs in successfully
   ↓
2. dashboard.js checkAuth() calls autoSyncAccounts()
   ↓
3. sync_accounts.php:
   a. Get current user info (admin or reseller)
   b. If admin: DELETE FROM _accounts
      If reseller: DELETE FROM _accounts WHERE reseller = user_id
   c. Get existing reseller mappings to preserve ownership
   d. Call Stalker Portal API /accounts/
   e. Parse JSON response
   f. Loop through accounts:
      - Map fields (login→username, etc.)
      - Handle invalid dates (0000-00-00 → NULL)
      - Preserve existing reseller assignment
      - For resellers: Skip accounts not assigned to them
      - INSERT INTO _accounts
   g. Return sync statistics
   ↓
4. Continue loading dashboard without showing sync result
   ↓
5. Reload accounts table automatically
   ↓
6. Hide loading overlay
```

**Manual Sync (optional):**
```
1. User clicks "Sync Accounts" button in Accounts tab
   ↓
2. JavaScript sends POST to sync_accounts.php
   ↓
3. Same process as auto-sync
   ↓
4. JavaScript displays sync result message
   ↓
5. Reload accounts table
   ↓
6. Recalculate dashboard statistics
```

### Flow 4: Dynamic Report Generation

```
1. User selects date range filter
   ↓
2. JavaScript handleExpiredFilterChange() triggered
   ↓
3. updateDynamicReports() function:
   a. Get all accounts from memory (no API call)
   b. Calculate date ranges
   c. Loop through accounts:
      - Check if expired in range
      - Check if expiring in range
   d. Update counters
   e. Update DOM elements
   ↓
4. Report cards display updated counts
```

**Key Optimization:** No server calls for filtering!

---

## Database Schema

### Entity-Relationship Diagram

```
┌─────────────────┐          ┌─────────────────┐
│    _users       │          │   _accounts     │
├─────────────────┤          ├─────────────────┤
│ id (PK)         │◄─────────│ reseller (FK)   │
│ username        │   1:N    │ id (PK)         │
│ password        │          │ username        │
│ full_name       │          │ mac             │
│ email           │          │ full_name       │
│ currency        │          │ tariff_plan     │
│ super_user      │          │ end_date        │
│ max_users       │          │ status          │
│ timestamp       │          │ timestamp       │
└─────────────────┘          └─────────────────┘
        │                             │
        │ 1:N                         │
        ▼                             │
┌─────────────────┐                  │
│ _transactions   │                  │
├─────────────────┤                  │
│ id (PK)         │                  │
│ user_id (FK)    │                  │
│ amount          │                  │
│ type            │                  │
│ description     │                  │
│ timestamp       │                  │
└─────────────────┘                  │
                                     │
                    ┌────────────────┘
                    │
                    ▼
            ┌─────────────────┐
            │    _plans       │
            ├─────────────────┤
            │ id (PK)         │
            │ plan_id         │
            │ currency        │
            │ price           │
            │ expiry_days     │
            │ timestamp       │
            └─────────────────┘
```

### Table Relationships

**_users → _accounts (1:N)**
- One user (reseller) can have many accounts
- Foreign key: `_accounts.reseller` → `_users.id`

**_users → _transactions (1:N)**
- One user can have many transactions
- Foreign key: `_transactions.user_id` → `_users.id`

**_accounts ↔ _plans (Loose Coupling)**
- Accounts reference plans by `tariff_plan` name
- No strict foreign key constraint
- Allows plan deletion without affecting accounts

---

## Security Architecture

### Authentication & Authorization

**Session-Based Authentication:**
```php
session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 1, 'err_msg' => 'Not authenticated']);
    exit();
}

// Check admin permission
if ($_SESSION['super_user'] != 1) {
    echo json_encode(['error' => 1, 'err_msg' => 'Permission denied']);
    exit();
}
```

**Role-Based Access Control:**
- Admin (`super_user = 1`): Full access
- Reseller (`super_user = 0`): Limited access
  - Cannot delete accounts
  - Can only see own accounts
  - Cannot see other resellers

### Data Security

**SQL Injection Prevention:**
```php
// ✅ SAFE - Using prepared statements
$stmt = $pdo->prepare('SELECT * FROM _accounts WHERE username = ?');
$stmt->execute([$username]);

// ❌ UNSAFE - Direct concatenation (NOT USED)
$query = "SELECT * FROM _accounts WHERE username = '$username'";
```

**XSS Prevention:**
```javascript
// ✅ SAFE - Using textContent (not innerHTML)
element.textContent = userInput;

// ❌ UNSAFE - innerHTML with user data (NOT USED)
element.innerHTML = userInput;
```

**Password Storage:**
```php
// Current: MD5 (⚠️ Weak, upgrade recommended)
$password_hash = md5($password);

// Recommended: Bcrypt
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$valid = password_verify($password, $password_hash);
```

---

## Integration Points

### Stalker Portal API Integration

**Authentication:**
```php
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERPWD, $username.":".$password);
```

**Endpoints Used:**

1. **GET /accounts/**
   - Fetch all accounts
   - Used by: sync_accounts.php

2. **POST /accounts/**
   - Create new account
   - Used by: add_account.php

3. **PUT /accounts/{mac}**
   - Update account
   - Used by: update_account.php

4. **DELETE /accounts/{mac}**
   - Delete account
   - Used by: remove_account.php

5. **POST /stb_msg/**
   - Send message to device
   - Used by: send_message.php

6. **POST /send_event/**
   - Send control event
   - Used by: send_event.php

**Data Mapping:**

| Stalker Field | Local Field | Transformation |
|---------------|-------------|----------------|
| login | username | Direct |
| mac | mac | Direct |
| full_name | full_name | Direct |
| email | email | Direct |
| end_date | end_date | Date validation (0000-00-00 → NULL) |
| status | status | Integer (0 or 1) |

---

## Scalability Considerations

### Current Architecture Limitations

1. **Sync Strategy**: DELETE all + INSERT all
   - Problem: Slow with large datasets
   - Solution: Implement incremental sync

2. **Client-Side Filtering**: All accounts loaded in memory
   - Problem: Browser memory limit
   - Solution: Server-side filtering with pagination

3. **Session Storage**: File-based PHP sessions
   - Problem: Not scalable across multiple servers
   - Solution: Redis or Memcached for sessions

4. **Single Database**: No replication
   - Problem: Single point of failure
   - Solution: Master-slave replication

### Scaling Strategies

**Vertical Scaling (Current):**
- Increase server resources
- Optimize MySQL configuration
- Enable PHP opcache

**Horizontal Scaling (Future):**
- Load balancer (Nginx/HAProxy)
- Multiple PHP application servers
- Redis for session sharing
- MySQL read replicas
- CDN for static assets

**Recommended Architecture for 100K+ Accounts:**

```
                    ┌─────────────┐
                    │ Load Balancer│
                    └──────┬──────┘
                           │
         ┌─────────────────┼─────────────────┐
         │                 │                 │
    ┌────▼────┐       ┌────▼────┐      ┌────▼────┐
    │ PHP App │       │ PHP App │      │ PHP App │
    │ Server 1│       │ Server 2│      │ Server 3│
    └────┬────┘       └────┬────┘      └────┬────┘
         │                 │                 │
         └─────────────────┼─────────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
         ┌────▼────┐  ┌────▼────┐ ┌────▼────┐
         │  Redis  │  │ MySQL   │ │ MySQL   │
         │ Session │  │ Master  │ │ Slave   │
         └─────────┘  └─────────┘ └─────────┘
```

---

## Performance Metrics

### Current Performance

**Dashboard Load Time:**
- Initial load: ~500ms
- Account fetch: ~200ms (1000 accounts)
- Render: ~100ms
- Total: ~800ms

**Sync Performance:**
- 1,000 accounts: ~15 seconds
- 5,000 accounts: ~60 seconds
- 10,000 accounts: ~120 seconds

**Database Queries:**
- Average query time: <10ms
- Accounts fetch: ~50ms (no limit)
- Reports generation: Client-side (0ms server)

### Optimization Targets

**Goal: Load 10,000 accounts in <2 seconds**
- Implement server-side pagination
- Add database indexes
- Enable MySQL query cache
- Use AJAX lazy loading

---

## Deployment Architecture

### Development Environment
```
macOS/Linux/Windows
PHP Built-in Server (port 8000)
MySQL Local Database
No SSL
```

### Production Environment
```
Linux Server (Ubuntu 20.04+)
Apache/Nginx + PHP-FPM
MySQL 5.7+ with replication
SSL/TLS (Let's Encrypt)
Firewall (UFW/iptables)
Fail2ban for security
```

---

## Monitoring & Logging

### Current Logging

**server.log:**
- PHP errors
- API requests
- Sync operations

**MySQL Logs:**
- Slow queries
- Error log

### Recommended Monitoring

1. **Application Monitoring:**
   - New Relic / Datadog
   - Error tracking (Sentry)

2. **Server Monitoring:**
   - CPU, RAM, Disk usage
   - Network traffic
   - Process monitoring

3. **Database Monitoring:**
   - Query performance
   - Connection pool
   - Replication lag

---

## Backup & Recovery

### Current Backup Strategy

Manual database backups recommended

### Recommended Backup Strategy

1. **Daily Full Backups:**
   ```bash
   mysqldump showboxt_panel | gzip > backup_$(date +%Y%m%d).sql.gz
   ```

2. **Hourly Incremental:**
   - MySQL binary logs
   - Point-in-time recovery

3. **Offsite Storage:**
   - AWS S3
   - Google Cloud Storage

4. **Recovery Testing:**
   - Monthly recovery drills
   - Documented procedures

---

## Support

For architecture questions:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin

---

**Document Version:** 1.1.0
**Last Updated:** November 21, 2025
**Maintained by:** ShowBox Development Team
