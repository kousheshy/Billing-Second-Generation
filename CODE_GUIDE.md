# ShowBox Billing Panel - Code Guide

Developer guide for understanding and contributing to the ShowBox Billing Panel codebase.

**Version:** 1.7.2
**Last Updated:** November 2025
**Target Audience:** Developers, Contributors

---

## Table of Contents

1. [Project Structure](#project-structure)
2. [Code Patterns](#code-patterns)
3. [Frontend Architecture](#frontend-architecture)
4. [Backend Architecture](#backend-architecture)
5. [Key Components](#key-components)
6. [Adding New Features](#adding-new-features)
7. [Best Practices](#best-practices)
8. [Common Patterns](#common-patterns)

---

## Project Structure

```
Current Billing Shahrokh/
│
├── Frontend Files:
│   ├── index.html              # Login page
│   ├── dashboard.html          # Main dashboard UI
│   ├── dashboard.js            # Frontend logic (2700+ lines)
│   └── dashboard.css           # Styling (1900+ lines)
│
├── Backend API Files:
│   ├── Account Management:
│   │   ├── add_account.php
│   │   ├── edit_account.php
│   │   ├── get_accounts.php
│   │   ├── sync_accounts.php
│   │   ├── remove_account.php
│   │   └── change_status.php
│   │
│   ├── Reseller Management:
│   │   ├── add_reseller.php
│   │   ├── update_reseller.php
│   │   ├── get_resellers.php
│   │   ├── remove_reseller.php
│   │   └── assign_reseller.php
│   │
│   ├── Plan Management:
│   │   ├── add_plan.php
│   │   ├── get_plans.php
│   │   ├── remove_plan.php
│   │   ├── get_tariffs.php
│   │   └── sync_plans_web.php
│   │
│   ├── STB Device Control:
│   │   ├── send_stb_event.php
│   │   ├── send_stb_message.php
│   │   ├── send_event.php (legacy)
│   │   └── send_message.php (legacy)
│   │
│   └── Other:
│       ├── login.php
│       ├── logout.php
│       ├── get_user_info.php
│       ├── get_transactions.php
│       └── adjust_credit.php
│
├── Configuration:
│   ├── config.php              # System configuration
│   └── api.php                 # Stalker Portal API helper
│
├── Database:
│   └── add_phone_column.php    # Migration utility
│
└── Documentation:
    ├── README.md
    ├── CHANGELOG.md
    ├── API_DOCUMENTATION.md
    ├── ARCHITECTURE.md
    ├── DATABASE_SCHEMA.md
    └── CODE_GUIDE.md (this file)
```

---

## Code Patterns

### 1. PHP Backend Pattern

**Standard API Endpoint Structure:**

```php
<?php
session_start();
include('config.php');

// Check authentication
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get current user
$username = $_SESSION['username'];

// Database connection
$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;

$dsn = "mysql:host=$host;dbname=$db;charset=utf8";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get user info and check permissions
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch();

    // Check permissions
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    if($user_info['super_user'] != 1 && !$is_reseller_admin) {
        $response['error'] = 1;
        $response['err_msg'] = 'Permission denied';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Your business logic here

    // Success response
    $response['error'] = 0;
    $response['message'] = 'Operation successful';

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
```

**Key Points:**
- Always start with `session_start()`
- Check authentication before any operations
- Use PDO with prepared statements (prevents SQL injection)
- Always return JSON responses
- Use try-catch for error handling
- Set `Content-Type: application/json` header

---

### 2. Stalker Portal API Integration

**Using the API Helper:**

```php
include('config.php');
include('api.php');

// Define endpoint
$case = 'accounts'; // or 'stb_msg', 'send_event', etc.
$op = "POST"; // or "GET", "PUT", "DELETE"
$mac = "00:1A:79:12:34:56"; // Device MAC address
$data = 'login=user001&password=pass123&stb_mac=00:1A:79:12:34:56';

// Send request
$res = api_send_request(
    $WEBSERVICE_URLs[$case],
    $WEBSERVICE_USERNAME,
    $WEBSERVICE_PASSWORD,
    $case,
    $op,
    $mac,
    $data
);

// Parse response
$decoded = json_decode($res);

if(isset($decoded->status) && $decoded->status == 'OK') {
    // Success
} else {
    // Error
    $error_msg = $decoded->error ?? 'Unknown error';
}
```

**Available Endpoints (`$WEBSERVICE_URLs`):**
- `accounts` - Account management
- `stb` - STB device info
- `stb_msg` - Send messages to devices
- `send_event` - Send control events
- `tariffs` - Get tariff plans
- `reseller` - Reseller management

**Important Notes:**
- Channel events use `$` separator: `event=play_channel$channel_number=123`
- Always URL-encode data values
- Check `$decoded->status == 'OK'` for success

---

### 3. JavaScript Frontend Pattern

**Standard API Call Pattern:**

```javascript
async function apiCall(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(endpoint, options);
        const result = await response.json();

        if (result.error === 0) {
            // Success
            return result;
        } else {
            // Error
            alert(result.err_msg || result.message);
            return null;
        }

    } catch (error) {
        console.error('API Error:', error);
        alert('Network error occurred');
        return null;
    }
}

// Usage
const accounts = await apiCall('get_accounts.php');
if (accounts) {
    renderAccounts(accounts.accounts);
}
```

**Key Patterns:**
- Always use async/await
- Handle errors gracefully
- Show user-friendly error messages
- Return null on error for easy checking

---

## Frontend Architecture

### Main JavaScript File: dashboard.js

**File Structure:**
```javascript
// 1. Global Variables (lines 1-50)
let currentUser = null;
let allAccounts = [];
let allResellers = [];
let allPlans = [];
const MAC_PREFIX = '00:1A:79:';

// 2. Initialization (lines 50-100)
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    initTheme();
    initAllMacInputs();
});

// 3. Authentication (lines 100-200)
async function checkAuth() { ... }
async function logout() { ... }

// 4. Data Loading (lines 200-400)
async function loadAccounts() { ... }
async function loadResellers() { ... }
async function loadPlans() { ... }

// 5. UI Rendering (lines 400-800)
function renderAccountsPage() { ... }
function renderResellersTable() { ... }
function renderPlansTable() { ... }

// 6. Form Handlers (lines 800-1200)
async function addAccount(e) { ... }
async function editAccount(e) { ... }
async function deleteAccount(username) { ... }

// 7. Reports & Analytics (lines 1200-1600)
function generateReports() { ... }
function updateDynamicReports() { ... }
function exportToPDF() { ... }

// 8. MAC Address Validation (lines 1600-1800)
function validateMacAddress(mac) { ... }
function initMacAddressInput(inputElement) { ... }

// 9. STB Control (lines 1800-2000)
async function sendStbEvent(event) { ... }
async function sendStbMessage(event) { ... }

// 10. Utility Functions (lines 2000-2700)
function getCurrencySymbol(currencyName) { ... }
function formatBalance(amount, currencyName) { ... }
function switchTab(tabName) { ... }
```

---

### Key JavaScript Functions

#### 1. MAC Address Validation

```javascript
/**
 * Validate MAC address format
 * @param {string} mac - MAC address to validate
 * @returns {object} - {valid: boolean, error: string}
 */
function validateMacAddress(mac) {
    if (!mac.startsWith(MAC_PREFIX)) {
        return {
            valid: false,
            error: 'MAC must start with 00:1A:79:'
        };
    }

    const macPattern = /^00:1A:79:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/;
    if (!macPattern.test(mac)) {
        return {
            valid: false,
            error: 'Invalid MAC format. Expected: 00:1A:79:XX:XX:XX'
        };
    }

    return { valid: true, error: null };
}

/**
 * Initialize MAC address input with auto-formatting
 * @param {HTMLInputElement} inputElement - Input element to initialize
 */
function initMacAddressInput(inputElement) {
    // Set initial value and attributes
    inputElement.value = MAC_PREFIX;
    inputElement.setAttribute('data-mac-initialized', 'true');
    inputElement.setAttribute('placeholder', '00:1A:79:XX:XX:XX');
    inputElement.setAttribute('maxlength', '17');

    // Create error display element
    let errorElement = inputElement.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains('mac-error')) {
        errorElement = document.createElement('div');
        errorElement.className = 'mac-error';
        inputElement.parentElement.appendChild(errorElement);
    }

    // Focus event - position cursor after prefix
    inputElement.addEventListener('focus', function(e) {
        setTimeout(() => {
            this.setSelectionRange(MAC_PREFIX.length, MAC_PREFIX.length);
        }, 0);
    });

    // Input event - auto-format
    inputElement.addEventListener('input', function(e) {
        let value = this.value;

        // Restore prefix if deleted
        if (!value.startsWith(MAC_PREFIX)) {
            value = MAC_PREFIX;
        }

        // Extract user input after prefix
        let userPart = value.substring(MAC_PREFIX.length);

        // Remove invalid characters
        userPart = userPart.replace(/[^0-9A-Fa-f:]/g, '');

        // Format with colons
        let formatted = '';
        let cleanUserPart = userPart.replace(/:/g, '');

        for (let i = 0; i < cleanUserPart.length && i < 6; i++) {
            if (i > 0 && i % 2 === 0) {
                formatted += ':';
            }
            formatted += cleanUserPart[i].toUpperCase();
        }

        // Set final value
        this.value = MAC_PREFIX + formatted;

        // Clear error on input
        hideMacError(this);
    });

    // Blur event - validate
    inputElement.addEventListener('blur', function(e) {
        const result = validateMacAddress(this.value);
        if (!result.valid) {
            showMacError(this, result.error);
        } else {
            hideMacError(this);
        }
    });
}
```

**Usage:**
```javascript
// Auto-initialize all MAC inputs on page load
document.addEventListener('DOMContentLoaded', function() {
    initAllMacInputs();
});

// Initialize specific input
const macInput = document.getElementById('mac-input');
initMacAddressInput(macInput);
```

---

#### 2. Currency Formatting

```javascript
/**
 * Get currency symbol or code
 * @param {string} currencyName - Currency code (GBP, USD, EUR, IRR)
 * @returns {string} - Symbol or code
 */
function getCurrencySymbol(currencyName) {
    if (!currencyName || currencyName === 'null' || currencyName === 'undefined') {
        return 'IRR ';
    }

    const symbols = {
        'GBP': '£',
        'USD': '$',
        'EUR': '€',
        'IRR': 'IRR '
    };

    return symbols[currencyName] || 'IRR ';
}

/**
 * Format balance with currency
 * @param {number} amount - Amount to format
 * @param {string} currencyName - Currency code
 * @returns {string} - Formatted string
 */
function formatBalance(amount, currencyName) {
    if (amount === null || amount === undefined) {
        amount = 0;
    }

    amount = parseFloat(amount) || 0;

    if (!currencyName || currencyName === 'null' || currencyName === 'undefined') {
        currencyName = 'IRR';
    }

    const symbol = getCurrencySymbol(currencyName);

    if (currencyName === 'IRR') {
        // Format with thousand separators
        const formatted = amount.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return symbol + formatted;
    } else {
        // Format with 2 decimals
        return symbol + amount.toFixed(2);
    }
}
```

**Usage:**
```javascript
const balance = formatBalance(10000, 'GBP'); // "£10000.00"
const balance_irr = formatBalance(6500000, 'IRR'); // "IRR 6,500,000"
```

---

#### 3. Pagination System

```javascript
const accountsPagination = {
    currentPage: 1,
    itemsPerPage: 25,
    filteredAccounts: [],
    searchTerm: ''
};

/**
 * Render accounts with pagination
 */
function renderAccountsPage() {
    const start = (accountsPagination.currentPage - 1) * accountsPagination.itemsPerPage;
    const end = start + accountsPagination.itemsPerPage;

    const accountsToDisplay = accountsPagination.filteredAccounts.length > 0
        ? accountsPagination.filteredAccounts
        : allAccounts;

    const pageAccounts = accountsToDisplay.slice(start, end);

    const tbody = document.getElementById('accounts-tbody');
    tbody.innerHTML = '';

    pageAccounts.forEach(account => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${account.username}</td>
            <td>${account.mac}</td>
            <td>${account.full_name || ''}</td>
            <td>${account.email || ''}</td>
            <td>${account.phone_number || ''}</td>
            <td>${account.tariff_plan || ''}</td>
            <td>${formatDate(account.end_date)}</td>
            <td>${getStatusBadge(account.status)}</td>
            <td>${account.reseller_name || '<span style="color: gray; font-style: italic;">Not Assigned</span>'}</td>
            <td>
                <button onclick="openEditAccountModal('${account.username}')">Edit</button>
                <button onclick="deleteAccount('${account.username}')">Delete</button>
                ${showAssignButton(account)}
            </td>
        `;
        tbody.appendChild(tr);
    });

    renderPaginationControls(accountsToDisplay.length);
}

/**
 * Search accounts
 * @param {string} searchTerm - Search term
 */
function searchAccounts(searchTerm) {
    accountsPagination.searchTerm = searchTerm.toLowerCase();

    if (!searchTerm) {
        accountsPagination.filteredAccounts = [];
    } else {
        accountsPagination.filteredAccounts = allAccounts.filter(account => {
            return account.username.toLowerCase().includes(searchTerm) ||
                   (account.mac && account.mac.toLowerCase().includes(searchTerm)) ||
                   (account.full_name && account.full_name.toLowerCase().includes(searchTerm));
        });
    }

    accountsPagination.currentPage = 1;
    renderAccountsPage();
}
```

---

## Backend Architecture

### Database Connection Pattern

**Standard PDO Connection:**

```php
$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, $user, $pass, $opt);
```

**Key Settings:**
- `ERRMODE_EXCEPTION` - Throw exceptions on errors
- `FETCH_ASSOC` - Return associative arrays
- `EMULATE_PREPARES => false` - Use real prepared statements

---

### Permission Checking Pattern

```php
// Get user info
$stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch();

// Parse permissions
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');

// Permission flags
$can_edit = isset($permissions[0]) && $permissions[0] === '1';
$can_add = isset($permissions[1]) && $permissions[1] === '1';
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';
$can_delete = isset($permissions[3]) && $permissions[3] === '1';

// Check permission
if($user_info['super_user'] != 1 && !$is_reseller_admin) {
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

**Permission Format:**
```
can_edit | can_add | is_reseller_admin | can_delete | reserved
   0     |    0    |         0         |     0      |    0
```

---

## Key Components

### 1. STB Device Control (v1.7.2)

**Files:**
- `send_stb_event.php` - Backend endpoint
- `send_stb_message.php` - Backend endpoint
- `dashboard.js` (lines 2355-2431) - Frontend functions
- `dashboard.html` (lines 217-285) - UI components
- `dashboard.css` (lines 1674-1820) - Styling

**Example - Sending Event:**

```javascript
async function sendStbEvent(event) {
    event.preventDefault();

    // Validate MAC address
    const macInput = event.target.querySelector('input[name="mac"]');
    if (!validateMacInput(macInput)) {
        return;
    }

    const formData = new FormData(event.target);

    try {
        const response = await fetch('send_stb_event.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.error === 0) {
            alert('Success: ' + result.message);
            addStbHistory('Event', formData.get('event'), formData.get('mac'));
            event.target.reset();
        } else {
            alert('Error: ' + result.err_msg);
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Network error occurred');
    }
}
```

---

### 2. Phone Number Support (v1.7.1)

**Files Modified:**
- `add_phone_column.php` - Database migration
- `add_account.php` - Save phone to DB and Stalker
- `edit_account.php` - Update phone in DB and Stalker
- `sync_accounts.php` - Fetch phone from Stalker
- `get_accounts.php` - Return phone in results
- `dashboard.js` - Display and export phone

**Sync Logic:**

```php
// In sync_accounts.php
// Stalker Portal is the single source of truth
$phone_number = $stalker_user->phone ?? null;

// No fallback to local values
$stmt = $pdo->prepare('INSERT INTO _accounts (..., phone_number, ...) VALUES (..., ?, ...)');
$stmt->execute([..., $phone_number, ...]);
```

---

### 3. Reseller Assignment (v1.7.0)

**Files:**
- `assign_reseller.php` - Backend endpoint
- `dashboard.js` (lines 1700-1800) - Frontend functions
- `get_accounts.php` - Include reseller_name via LEFT JOIN

**Backend Logic:**

```php
// Get parameters
$account_username = $_POST['username'];
$reseller_id = $_POST['reseller_id'];

// Convert empty string to NULL for "Not Assigned"
if($reseller_id === '' || $reseller_id === null) {
    $reseller_id = null;
} else {
    $reseller_id = (int)$reseller_id;
}

// Update account
$stmt = $pdo->prepare('UPDATE _accounts SET reseller = ? WHERE username = ?');
$stmt->execute([$reseller_id, $account_username]);
```

**Frontend Logic:**

```javascript
async function assignReseller(username) {
    const resellerId = document.getElementById('assign-reseller-select').value;

    const formData = new FormData();
    formData.append('username', username);
    formData.append('reseller_id', resellerId);

    const response = await fetch('assign_reseller.php', {
        method: 'POST',
        body: formData
    });

    const result = await response.json();

    if (result.error === 0) {
        alert(result.message);
        closeModal('assign-reseller-modal');
        loadAccounts();
    } else {
        alert('Error: ' + result.err_msg);
    }
}
```

---

## Adding New Features

### Step 1: Create Backend API

```php
<?php
// new_feature.php

session_start();
include('config.php');

// Authentication check
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Database connection
$dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $ub_db_username, $ub_db_password, $opt);

    // Your logic here

    $response['error'] = 0;
    $response['message'] = 'Success';

} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
```

### Step 2: Add Frontend Function

```javascript
// In dashboard.js

async function callNewFeature(param1, param2) {
    try {
        const formData = new FormData();
        formData.append('param1', param1);
        formData.append('param2', param2);

        const response = await fetch('new_feature.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.error === 0) {
            alert('Success: ' + result.message);
            // Update UI
        } else {
            alert('Error: ' + result.err_msg);
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Network error occurred');
    }
}
```

### Step 3: Add UI Elements

```html
<!-- In dashboard.html -->

<button onclick="callNewFeature('value1', 'value2')">
    New Feature
</button>
```

### Step 4: Add Styling

```css
/* In dashboard.css */

.new-feature-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.new-feature-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
```

---

## Best Practices

### 1. Security

**Always:**
- Use prepared statements for SQL queries
- Validate user input on both frontend and backend
- Check authentication on every backend endpoint
- Use session-based authentication
- Sanitize output to prevent XSS
- Log sensitive operations

**Never:**
- Concatenate user input into SQL queries
- Trust client-side validation alone
- Expose database credentials in frontend
- Use `eval()` or similar dangerous functions

### 2. Error Handling

**Backend:**
```php
try {
    // Your code
    $response['error'] = 0;
    $response['message'] = 'Success';
} catch(PDOException $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Database error: ' . $e->getMessage();
    error_log('Database error: ' . $e->getMessage());
} catch(Exception $e) {
    $response['error'] = 1;
    $response['err_msg'] = 'Error: ' . $e->getMessage();
    error_log('Error: ' . $e->getMessage());
}
```

**Frontend:**
```javascript
try {
    const result = await apiCall();
    if (!result) {
        throw new Error('API call failed');
    }
    // Success handling
} catch (error) {
    console.error('Error:', error);
    alert('An error occurred. Please try again.');
}
```

### 3. Code Organization

**PHP Files:**
- Start with authentication check
- Define database connection
- Business logic in try-catch
- Always return JSON
- Log errors for debugging

**JavaScript Files:**
- Group related functions
- Use async/await for API calls
- Add comments for complex logic
- Keep functions focused (single responsibility)

### 4. Performance

**Database:**
- Use indexes on frequently queried columns
- Limit SELECT queries (don't use SELECT *)
- Use pagination for large datasets
- Cache results when appropriate

**Frontend:**
- Minimize API calls
- Use client-side filtering when possible
- Debounce search inputs
- Lazy load large datasets

---

## Common Patterns

### 1. Loading Data Pattern

```javascript
async function loadData() {
    try {
        const response = await fetch('get_data.php');
        const result = await response.json();

        if (result.error === 0) {
            allData = result.data;
            renderData();
        } else {
            alert('Error: ' + result.err_msg);
        }
    } catch (error) {
        console.error('Error loading data:', error);
    }
}
```

### 2. Form Submission Pattern

```javascript
async function submitForm(event) {
    event.preventDefault();

    const formData = new FormData(event.target);

    try {
        const response = await fetch('submit_endpoint.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.error === 0) {
            alert('Success!');
            event.target.reset();
            closeModal('modal-id');
            loadData(); // Refresh data
        } else {
            alert('Error: ' + result.err_msg);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Network error');
    }
}
```

### 3. Permission Check Pattern

```php
// Get user permissions
$stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
$stmt->execute([$username]);
$user_info = $stmt->fetch();

// Parse permissions
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

// Check permission
if($user_info['super_user'] == 1 || $is_reseller_admin) {
    // Allow operation
} else {
    // Deny operation
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
```

---

## Support

For developer support:
- **WhatsApp**: +447736932888
- **Instagram**: @ShowBoxAdmin
- **Documentation**: README.md

---

**Document Version:** 1.7.2
**Last Updated:** November 2025
**Maintained by:** ShowBox Development Team
**Developer:** Kambiz Koosheshi
