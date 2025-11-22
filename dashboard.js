// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('[PWA] Service Worker registered successfully:', registration.scope);

                // Check for updates periodically
                setInterval(() => {
                    registration.update();
                }, 60000); // Check every minute

                // Handle updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available
                            if (confirm('New version available! Reload to update?')) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch(error => {
                console.log('[PWA] Service Worker registration failed:', error);
            });

        // Handle service worker controller change
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });
    });
}

// PWA Install Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    console.log('[PWA] Install prompt available');

    // Show custom install button if you want
    // You can add a custom UI element here
});

// Global variable to store user info
let currentUser = null;

// Pagination state
let accountsPagination = {
    currentPage: 1,
    perPage: 25,
    totalItems: 0,
    allAccounts: [],
    filteredAccounts: [],
    searchTerm: ''
};

// Theme Management
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    const themeIcon = document.getElementById('theme-icon');

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Update icon
    themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
}

// Initialize theme from localStorage
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    const themeIcon = document.getElementById('theme-icon');

    document.documentElement.setAttribute('data-theme', savedTheme);
    if (themeIcon) {
        themeIcon.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
    }
}

// Check if account is expiring soon (within 2 weeks)
function isExpiringSoon(endDate) {
    if (!endDate) return false;

    const now = new Date();
    const expirationDate = new Date(endDate);
    const twoWeeksFromNow = new Date();
    twoWeeksFromNow.setDate(now.getDate() + 14);

    return expirationDate >= now && expirationDate <= twoWeeksFromNow;
}

// Check if account is expired
function isExpired(endDate) {
    if (!endDate) return false;

    const now = new Date();
    const expirationDate = new Date(endDate);

    return expirationDate < now;
}

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch('get_user_info.php');
        const result = await response.json();

        if(result.error == 1) {
            window.location.href = 'index.html';
            return;
        }

        // Store user info globally
        currentUser = result.user;

        // Display user info
        document.getElementById('username-display').textContent = 'Welcome, ' + result.user.name;

        // Check if user is super admin, reseller admin, or observer
        const isSuperAdmin = result.user.super_user == 1;
        const isResellerAdmin = result.user.is_reseller_admin === true || result.user.is_reseller_admin === '1';
        const isObserver = result.user.is_observer == 1;

        // Handle different user types
        if(isObserver) {
            // Observer: Can see everything but cannot modify anything
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Hide Settings tab for observers
            document.querySelectorAll('.tab').forEach(tab => {
                const tabText = tab.textContent.toLowerCase();
                if(tabText.includes('settings')) {
                    tab.style.display = 'none';
                }
            });

            // Add visual indicator that this is an observer account
            document.getElementById('username-display').innerHTML = 'Welcome, ' + result.user.name + ' <span style="color: #fbbf24; font-size: 12px;">(Observer)</span>';
        } else if(isSuperAdmin) {
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Show sync section for super admin only
            document.getElementById('sync-section').style.display = 'block';
        } else if(isResellerAdmin) {
            // Reseller admin: Has same features as super admin
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Show sync section for reseller admins
            document.getElementById('sync-section').style.display = 'block';

            // Show view mode toggle for reseller admins
            document.getElementById('view-mode-toggle').style.display = 'flex';

            // Load saved preference from localStorage (default to false for "My Accounts")
            const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
            document.getElementById('view-all-accounts').checked = viewAllAccounts;
            updateViewModeLabel(viewAllAccounts);

            // Update account count based on initial preference
            updateAccountCount(viewAllAccounts);
        } else {
            // Regular reseller: show balance, hide resellers AND plans
            document.getElementById('balance-display').textContent = getCurrencySymbol(result.user.currency_name) + formatBalance(result.user.balance, result.user.currency_name);

            // Hide admin-only tabs for regular resellers
            document.querySelectorAll('.tab').forEach(tab => {
                const tabText = tab.textContent.toLowerCase();
                if(tabText.includes('reseller') || tabText.includes('plan')) {
                    tab.style.display = 'none';
                }
            });

            // Hide admin-only stat cards for regular resellers
            document.querySelector('.stat-card:nth-child(3)').style.display = 'none'; // Total Resellers
            document.querySelector('.stat-card:nth-child(4)').style.display = 'none'; // Total Plans
        }

        document.getElementById('total-accounts').textContent = result.total_accounts;

        // Auto-sync accounts on login (for both admin and resellers, but not observers)
        if(!isObserver) {
            await autoSyncAccounts();
        }

        // Load initial data based on user type
        loadAccounts();
        loadTransactions();

        // Load plans for all users (including observers who need to see them)
        loadPlans();

        if(isSuperAdmin || isResellerAdmin || isObserver) {
            // Load resellers for super admin, reseller admins, and observers
            loadResellers();
            // Load tariffs for super admin, reseller admins, and observers
            loadTariffs();
            // Load themes for reseller management
            loadThemes();
        }

        // Hide all action buttons for observers
        if(isObserver) {
            hideObserverActions();
        }

        // Hide loading overlay after everything is loaded
        hideLoadingOverlay();

    } catch(error) {
        console.error('Auth check failed:', error);
        window.location.href = 'index.html';
    }
}

// Auto-sync accounts on login
async function autoSyncAccounts() {
    try {
        const response = await fetch('sync_accounts.php', {
            method: 'POST'
        });

        const result = await response.json();

        if(result.error == 0) {
            console.log('Auto-sync completed:', result.synced, 'accounts synced');
        } else {
            console.error('Auto-sync failed:', result.err_msg);
        }
    } catch(error) {
        console.error('Auto-sync error:', error);
    }
}

// Hide loading overlay
function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if(overlay) {
        overlay.classList.add('hidden');
        // Remove from DOM after transition
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 300);
    }
}

// Tab switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });

    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');

    // Refresh dynamic reports when switching to reports tab
    if(tabName === 'reports' && accountsPagination.allAccounts) {
        updateDynamicReports();
    }
}

// Show accounts list from report cards
function showReportAccountsList(reportType) {
    // Switch to accounts tab
    switchTab('accounts');

    // Clear any existing search
    document.getElementById('accounts-search').value = '';

    const now = new Date();

    if (reportType === 'expired-dynamic') {
        // Get the current selected filter days
        const filterSelect = document.getElementById('expired-filter');
        let days = parseInt(filterSelect.value);

        if (filterSelect.value === 'custom') {
            days = parseInt(document.getElementById('expired-custom-days').value) || 30;
        }

        const startDate = new Date();
        startDate.setDate(now.getDate() - days);

        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= startDate && expirationDate < now;
        });
        accountsPagination.searchTerm = `expired-dynamic-${days}d`;

    } else if (reportType === 'expiring-dynamic') {
        // Get the current selected filter days
        const filterSelect = document.getElementById('expiring-filter');
        let days = parseInt(filterSelect.value);

        if (filterSelect.value === 'custom') {
            days = parseInt(document.getElementById('expiring-custom-days').value) || 14;
        }

        const endDate = new Date();
        endDate.setDate(now.getDate() + days);

        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now && expirationDate <= endDate;
        });
        accountsPagination.searchTerm = `expiring-dynamic-${days}d`;

    } else if (reportType === 'expired-all') {
        // All expired accounts
        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate < now;
        });
        accountsPagination.searchTerm = 'expired-all';

    } else if (reportType === 'expiring-soon') {
        // Expiring in next 2 weeks (14 days)
        const twoWeeksFromNow = new Date();
        twoWeeksFromNow.setDate(now.getDate() + 14);

        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now && expirationDate <= twoWeeksFromNow;
        });
        accountsPagination.searchTerm = 'expiring-soon-2weeks';

    } else if (reportType === 'all-accounts') {
        // Show all accounts (no filter)
        accountsPagination.filteredAccounts = [];
        accountsPagination.searchTerm = '';

    } else if (reportType === 'active-accounts') {
        // Show only active accounts (not expired)
        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return true; // Unlimited plans are active
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now;
        });
        accountsPagination.searchTerm = 'active-accounts';

    } else if (reportType === 'unlimited-plans') {
        // Show accounts with no expiration date
        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            return !account.end_date || account.end_date === null || account.end_date === '';
        });
        accountsPagination.searchTerm = 'unlimited-plans';

    } else if (reportType === 'expired-last-month-static') {
        // Expired in last 30 days (static version)
        const oneMonthAgo = new Date();
        oneMonthAgo.setDate(now.getDate() - 30);

        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= oneMonthAgo && expirationDate < now;
        });
        accountsPagination.searchTerm = 'expired-last-month-static';
    }

    // Reset to first page
    accountsPagination.currentPage = 1;

    // Render filtered results
    renderAccountsPage();
}

// Format currency symbol
function getCurrencySymbol(currencyName) {
    // Handle null or undefined currency
    if(!currencyName) return 'IRR ';

    // Normalize IRT to IRR (fix for legacy data)
    if(currencyName === 'IRT') currencyName = 'IRR';

    if(currencyName === 'USD') return '$';
    if(currencyName === 'EUR') return '€';
    if(currencyName === 'IRR') return 'IRR ';
    if(currencyName === 'GBP') return '£';
    return currencyName + ' ';
}

// Format balance with proper thousands separator
function formatBalance(amount, currencyName) {
    // Handle null, undefined, or empty values
    const numAmount = amount || 0;

    // Normalize IRT to IRR (fix for legacy data)
    if(currencyName === 'IRT') currencyName = 'IRR';

    // Default to IRR if currency is not specified
    if(!currencyName || currencyName === 'IRR') {
        // Iranian Rial thousand separator (comma every 3 digits, no decimals)
        return Number(numAmount).toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    // Format all other currencies with proper decimals
    return Number(numAmount).toFixed(2);
}

// Generate random string (13 characters with lowercase letters and numbers)
function generateRandomString() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 13; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

// ===========================
// MAC Address Input Component
// ===========================

const MAC_PREFIX = '00:1A:79:';

/**
 * Validate MAC address format
 * Must be 00:1A:79:XX:XX:XX where X is a hex digit (0-9, A-F)
 */
function validateMacAddress(mac) {
    // Check if it starts with the required prefix
    if (!mac.startsWith(MAC_PREFIX)) {
        return { valid: false, error: 'MAC must start with 00:1A:79:' };
    }

    // Check full format: 00:1A:79:XX:XX:XX
    const macPattern = /^00:1A:79:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/;
    if (!macPattern.test(mac)) {
        return { valid: false, error: 'Invalid MAC format. Expected: 00:1A:79:XX:XX:XX (hex digits only)' };
    }

    return { valid: true, error: null };
}

/**
 * Initialize MAC address input field
 * Makes the prefix non-editable and handles user input
 */
function initMacAddressInput(inputElement) {
    if (!inputElement) return;

    // Set initial value with prefix and placeholder
    inputElement.value = MAC_PREFIX;
    inputElement.setAttribute('data-mac-initialized', 'true');

    // Add visual styling
    inputElement.classList.add('mac-input');
    inputElement.setAttribute('placeholder', '00:1A:79:XX:XX:XX');
    inputElement.setAttribute('maxlength', '17'); // 00:1A:79:XX:XX:XX = 17 chars

    // Create error message element if it doesn't exist
    let errorElement = inputElement.parentElement.querySelector('.mac-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'mac-error';
        errorElement.style.display = 'none';
        inputElement.parentElement.appendChild(errorElement);
    }

    // Handle focus - move cursor after prefix
    inputElement.addEventListener('focus', function(e) {
        if (this.value === MAC_PREFIX || this.value === '') {
            this.value = MAC_PREFIX;
        }
        // Set cursor position after prefix
        setTimeout(() => {
            this.setSelectionRange(MAC_PREFIX.length, MAC_PREFIX.length);
        }, 0);
    });

    // Handle input - ensure prefix is always there
    inputElement.addEventListener('input', function(e) {
        let value = this.value;

        // If user tries to delete the prefix, restore it
        if (!value.startsWith(MAC_PREFIX)) {
            this.value = MAC_PREFIX;
            this.setSelectionRange(MAC_PREFIX.length, MAC_PREFIX.length);
            return;
        }

        // Get the user-entered part (after prefix)
        let userPart = value.substring(MAC_PREFIX.length);

        // Remove any invalid characters (not hex or colon)
        userPart = userPart.replace(/[^0-9A-Fa-f:]/g, '');

        // Auto-format: add colons after every 2 characters
        let formatted = '';
        let cleanUserPart = userPart.replace(/:/g, ''); // Remove existing colons

        for (let i = 0; i < cleanUserPart.length && i < 6; i++) {
            if (i > 0 && i % 2 === 0) {
                formatted += ':';
            }
            formatted += cleanUserPart[i].toUpperCase();
        }

        this.value = MAC_PREFIX + formatted;

        // Hide error on input
        errorElement.style.display = 'none';
    });

    // Handle keydown - prevent deleting/modifying prefix
    inputElement.addEventListener('keydown', function(e) {
        const selectionStart = this.selectionStart;
        const selectionEnd = this.selectionEnd;

        // Prevent backspace/delete if it would affect the prefix
        if ((e.key === 'Backspace' || e.key === 'Delete') && selectionStart <= MAC_PREFIX.length) {
            if (selectionEnd <= MAC_PREFIX.length) {
                e.preventDefault();
                return;
            }
        }

        // Prevent left arrow or home key from going into prefix
        if (e.key === 'ArrowLeft' || e.key === 'Home') {
            if (selectionStart <= MAC_PREFIX.length) {
                e.preventDefault();
                this.setSelectionRange(MAC_PREFIX.length, MAC_PREFIX.length);
            }
        }
    });

    // Handle click - don't let user click into prefix
    inputElement.addEventListener('click', function(e) {
        if (this.selectionStart < MAC_PREFIX.length) {
            this.setSelectionRange(MAC_PREFIX.length, MAC_PREFIX.length);
        }
    });

    // Validate on blur
    inputElement.addEventListener('blur', function(e) {
        const validation = validateMacAddress(this.value);

        if (!validation.valid && this.value !== MAC_PREFIX) {
            showMacError(this, validation.error);
        } else {
            hideMacError(this);
        }
    });
}

/**
 * Show MAC validation error
 */
function showMacError(inputElement, message) {
    const errorElement = inputElement.parentElement.querySelector('.mac-error');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
    inputElement.classList.add('mac-input-error');
}

/**
 * Hide MAC validation error
 */
function hideMacError(inputElement) {
    const errorElement = inputElement.parentElement.querySelector('.mac-error');
    if (errorElement) {
        errorElement.style.display = 'none';
    }
    inputElement.classList.remove('mac-input-error');
}

/**
 * Validate MAC input before form submission
 * Returns true if valid, false if invalid
 */
function validateMacInput(inputElement) {
    if (!inputElement) return false;

    const validation = validateMacAddress(inputElement.value);

    if (!validation.valid) {
        showMacError(inputElement, validation.error);
        inputElement.focus();
        return false;
    }

    hideMacError(inputElement);
    return true;
}

/**
 * Initialize all MAC address inputs on the page
 */
function initAllMacInputs() {
    // Find all MAC address input fields
    const macInputs = document.querySelectorAll('input[name="mac"], input[id*="mac"], input[placeholder*="1A:79"]');

    console.log(`[MAC Init] Found ${macInputs.length} MAC input fields to initialize`);

    macInputs.forEach(input => {
        if (!input.getAttribute('data-mac-initialized')) {
            console.log(`[MAC Init] Initializing MAC input:`, input.name || input.id || 'unnamed');
            initMacAddressInput(input);
        } else {
            console.log(`[MAC Init] Skipping already initialized input:`, input.name || input.id || 'unnamed');
        }
    });

    console.log(`[MAC Init] Initialization complete`);
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');

    // Initialize MAC address inputs in the modal
    setTimeout(() => {
        initAllMacInputs();
    }, 10);

    // Auto-generate username and password when opening add account modal
    if(modalId === 'addAccountModal') {
        document.getElementById('account-username').value = generateRandomString();
        document.getElementById('account-password').value = generateRandomString();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');

    // Reset form when closing add account modal
    if(modalId === 'addAccountModal') {
        document.getElementById('addAccountForm').reset();
    }
}

// Alert function
function showAlert(message, type) {
    const alert = document.getElementById('alert');
    alert.textContent = message;
    alert.className = 'alert ' + type + ' show';

    setTimeout(() => {
        alert.classList.remove('show');
    }, 5000);
}

// Load Accounts
async function loadAccounts() {
    try {
        // Get view mode preference for reseller admins
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const url = `get_accounts.php?viewAllAccounts=${viewAllAccounts}`;
        console.log('[loadAccounts] Fetching with viewAllAccounts:', viewAllAccounts);

        const response = await fetch(url);
        const result = await response.json();

        if(result.error == 0 && result.accounts) {
            // Store all accounts
            console.log('[loadAccounts] Received', result.accounts.length, 'accounts');
            accountsPagination.allAccounts = result.accounts;
            accountsPagination.totalItems = result.accounts.length;

            // Clear any active filters/search so deleted items disappear immediately
            accountsPagination.filteredAccounts = [];
            accountsPagination.searchTerm = '';
            accountsPagination.currentPage = 1; // Reset to first page

            // Calculate expiring soon count
            updateExpiringSoonCount(result.accounts);

            // Calculate expired last month count
            updateExpiredLastMonthCount(result.accounts);

            // Generate comprehensive reports
            generateReports(result.accounts);

            // Render current page
            renderAccountsPage();
        } else {
            const tbody = document.getElementById('accounts-tbody');
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
            document.getElementById('accounts-pagination').innerHTML = '';
            document.getElementById('accounts-pagination-info').textContent = '';
        }
    } catch(error) {
        console.error('Error loading accounts:', error);
        showAlert('Error loading accounts', 'error');
    }
}

// Calculate and display accounts expiring in the next 2 weeks
function updateExpiringSoonCount(accounts) {
    const now = new Date();
    const twoWeeksFromNow = new Date();
    twoWeeksFromNow.setDate(now.getDate() + 14);

    let expiringSoonCount = 0;

    accounts.forEach(account => {
        if(account.end_date) {
            const expirationDate = new Date(account.end_date);

            // Check if expiration date is between now and 2 weeks from now
            if(expirationDate >= now && expirationDate <= twoWeeksFromNow) {
                expiringSoonCount++;
            }
        }
    });

    document.getElementById('expiring-soon').textContent = expiringSoonCount;
}

// Calculate and display accounts expired in the last month
function updateExpiredLastMonthCount(accounts) {
    const now = new Date();
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(now.getMonth() - 1);

    let expiredLastMonthCount = 0;

    accounts.forEach(account => {
        if(account.end_date) {
            const expirationDate = new Date(account.end_date);

            // Check if expired between 1 month ago and now
            if(expirationDate >= oneMonthAgo && expirationDate < now) {
                expiredLastMonthCount++;
            }
        }
    });

    document.getElementById('expired-last-month').textContent = expiredLastMonthCount;
}

// Generate comprehensive reports based on accounts data
function generateReports(accounts) {
    const now = new Date();
    const twoWeeksFromNow = new Date();
    twoWeeksFromNow.setDate(now.getDate() + 14);
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(now.getMonth() - 1);

    let totalAccounts = accounts.length;
    let activeAccounts = 0;
    let expiredAccounts = 0;
    let expiringSoonCount = 0;
    let unlimitedAccounts = 0;
    let expiredLastMonthCount = 0;

    accounts.forEach(account => {
        if(!account.end_date) {
            // No expiration date = unlimited
            unlimitedAccounts++;
            activeAccounts++; // Unlimited is considered active
        } else {
            const expirationDate = new Date(account.end_date);

            if(expirationDate < now) {
                // Already expired
                expiredAccounts++;

                // Check if expired in last month
                if(expirationDate >= oneMonthAgo) {
                    expiredLastMonthCount++;
                }
            } else if(expirationDate >= now && expirationDate <= twoWeeksFromNow) {
                // Expiring soon
                expiringSoonCount++;
                activeAccounts++; // Still active but expiring soon
            } else {
                // Active and not expiring soon
                activeAccounts++;
            }
        }
    });

    // Update report values
    document.getElementById('report-total-accounts').textContent = totalAccounts;
    document.getElementById('report-active-accounts').textContent = activeAccounts;
    document.getElementById('report-expired-accounts').textContent = expiredAccounts;
    document.getElementById('report-expiring-soon').textContent = expiringSoonCount;
    document.getElementById('report-unlimited-accounts').textContent = unlimitedAccounts;
    document.getElementById('report-expired-last-month').textContent = expiredLastMonthCount;

    // Update dynamic reports with default values
    updateDynamicReports();
}

// Handle expired filter change
function handleExpiredFilterChange() {
    const filterValue = document.getElementById('expired-filter').value;
    const customInput = document.getElementById('expired-custom-input');

    if(filterValue === 'custom') {
        customInput.style.display = 'block';
        // Set default value if empty
        if(!document.getElementById('expired-custom-days').value) {
            document.getElementById('expired-custom-days').value = 30;
        }
    } else {
        customInput.style.display = 'none';
    }

    updateDynamicReports();
}

// Handle expiring filter change
function handleExpiringFilterChange() {
    const filterValue = document.getElementById('expiring-filter').value;
    const customInput = document.getElementById('expiring-custom-input');

    if(filterValue === 'custom') {
        customInput.style.display = 'block';
        // Set default value if empty
        if(!document.getElementById('expiring-custom-days').value) {
            document.getElementById('expiring-custom-days').value = 14;
        }
    } else {
        customInput.style.display = 'none';
    }

    updateDynamicReports();
}

// Update dynamic reports based on selected filters
function updateDynamicReports() {
    const accounts = accountsPagination.allAccounts;
    console.log('[updateDynamicReports] Called with', accounts ? accounts.length : 0, 'accounts');
    if(!accounts || accounts.length === 0) return;

    const now = new Date();

    // Get selected periods
    let expiredDays, expiringDays;

    const expiredFilterValue = document.getElementById('expired-filter').value;
    if(expiredFilterValue === 'custom') {
        const customValue = parseInt(document.getElementById('expired-custom-days').value);
        expiredDays = customValue && customValue > 0 ? customValue : 30;
    } else {
        expiredDays = parseInt(expiredFilterValue);
    }

    const expiringFilterValue = document.getElementById('expiring-filter').value;
    if(expiringFilterValue === 'custom') {
        const customValue = parseInt(document.getElementById('expiring-custom-days').value);
        expiringDays = customValue && customValue > 0 ? customValue : 14;
    } else {
        expiringDays = parseInt(expiringFilterValue);
    }

    // Calculate date ranges
    const expiredStartDate = new Date();
    expiredStartDate.setDate(now.getDate() - expiredDays);

    const expiringEndDate = new Date();
    expiringEndDate.setDate(now.getDate() + expiringDays);

    // Count accounts
    let expiredNotRenewedCount = 0;
    let expiringInPeriodCount = 0;

    accounts.forEach(account => {
        if(account.end_date) {
            const expirationDate = new Date(account.end_date);

            // Count expired and NOT renewed (still expired today)
            // An account is "not renewed" if it expired in the period AND is still expired now
            // (meaning the end_date was not updated to a future date)
            if(expirationDate >= expiredStartDate && expirationDate < now) {
                // This account expired in the selected period and is still expired (not renewed)
                expiredNotRenewedCount++;
            }

            // Count expiring in selected period
            if(expirationDate >= now && expirationDate <= expiringEndDate) {
                expiringInPeriodCount++;
            }
        }
    });

    // Update UI
    console.log('[updateDynamicReports] Expired:', expiredNotRenewedCount, 'Expiring:', expiringInPeriodCount);
    document.getElementById('dynamic-expired-count').textContent = expiredNotRenewedCount;
    document.getElementById('dynamic-expiring-count').textContent = expiringInPeriodCount;

    // Update labels
    const expiredLabel = getExpiredLabel(expiredDays, expiredFilterValue === 'custom');
    const expiringLabel = getExpiringLabel(expiringDays, expiringFilterValue === 'custom');

    document.getElementById('dynamic-expired-label').textContent = expiredLabel;
    document.getElementById('dynamic-expiring-label').textContent = expiringLabel;
}

// Helper function to get expired label text
function getExpiredLabel(days, isCustom) {
    if(isCustom) {
        return `Last ${days} day${days !== 1 ? 's' : ''} (custom)`;
    }
    if(days === 7) return 'Last 7 days';
    if(days === 14) return 'Last 14 days';
    if(days === 30) return 'Last 30 days';
    if(days === 60) return 'Last 60 days';
    if(days === 90) return 'Last 90 days';
    if(days === 180) return 'Last 6 months';
    if(days === 365) return 'Last year';
    return `Last ${days} days`;
}

// Helper function to get expiring label text
function getExpiringLabel(days, isCustom) {
    if(isCustom) {
        return `Next ${days} day${days !== 1 ? 's' : ''} (custom)`;
    }
    if(days === 7) return 'Next 7 days';
    if(days === 14) return 'Next 14 days';
    if(days === 30) return 'Next 30 days';
    if(days === 60) return 'Next 60 days';
    if(days === 90) return 'Next 90 days';
    return `Next ${days} days`;
}

function renderAccountsPage() {
    const { currentPage, perPage, searchTerm, allAccounts, filteredAccounts } = accountsPagination;
    const tbody = document.getElementById('accounts-tbody');

    // Use filtered accounts if search is active, otherwise use all accounts
    const accountsToDisplay = searchTerm ? filteredAccounts : allAccounts;
    const totalItems = accountsToDisplay.length;

    // Calculate pagination
    const startIndex = (currentPage - 1) * perPage;
    const endIndex = Math.min(startIndex + perPage, totalItems);
    const pageAccounts = accountsToDisplay.slice(startIndex, endIndex);

    // Render accounts for current page
    if(pageAccounts.length > 0) {
        tbody.innerHTML = '';
        pageAccounts.forEach(account => {
            const tr = document.createElement('tr');

            // Check if user is observer
            const isObserver = currentUser && currentUser.is_observer == 1;
            const isSuperAdmin = currentUser && currentUser.super_user == 1;
            const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');

            // Check if reseller has delete permission (format: can_edit|can_add|is_reseller_admin|can_delete|reserved)
            const permissions = (currentUser && currentUser.permissions || '0|0|0|0|0').split('|');
            const canDelete = permissions[3] === '1';

            // Show delete button - disabled for observers and resellers without permission
            let deleteButton = '';
            if (isObserver) {
                deleteButton = `<button class="btn-sm btn-delete" disabled style="opacity: 0.5; cursor: not-allowed;">Delete</button>`;
            } else if (isSuperAdmin || isResellerAdmin || canDelete) {
                deleteButton = `<button class="btn-sm btn-delete" onclick="deleteAccount('${account.username}')">Delete</button>`;
            } else {
                deleteButton = `<button class="btn-sm btn-delete" disabled style="opacity: 0.5; cursor: not-allowed;">Delete</button>`;
            }

            // Show edit button for everyone, but disabled for observers
            const editButton = isObserver
                ? `<button class="btn-sm btn-edit" disabled style="opacity: 0.5; cursor: not-allowed;">Edit</button>`
                : `<button class="btn-sm btn-edit" onclick="editAccount('${account.username}')">Edit</button>`;

            // Add Assign Reseller button for admin users (using same pattern as edit/delete buttons)
            let assignResellerButton = '';
            if (isSuperAdmin || isResellerAdmin) {
                assignResellerButton = `<button class="btn-sm btn-assign" onclick="assignReseller('${account.username}', '${account.reseller || ''}')">Assign Reseller</button>`;
            }

            // Format expiration date and determine status
            let expirationCell = '';

            if(account.end_date) {
                const expDate = new Date(account.end_date);
                const formattedDate = expDate.toLocaleDateString();

                // Determine status
                if(isExpired(account.end_date)) {
                    expirationCell = `
                        <div class="expiration-cell">
                            <span class="exp-date">${formattedDate}</span>
                            <span class="badge inactive">Expired</span>
                        </div>
                    `;
                } else if(isExpiringSoon(account.end_date)) {
                    expirationCell = `
                        <div class="expiration-cell">
                            <span class="exp-date">${formattedDate}</span>
                            <span class="badge expiring">Expiring Soon</span>
                        </div>
                    `;
                } else {
                    expirationCell = `
                        <div class="expiration-cell">
                            <span class="exp-date">${formattedDate}</span>
                            <span class="badge active">Active</span>
                        </div>
                    `;
                }
            } else {
                expirationCell = `
                    <div class="expiration-cell">
                        <span class="badge active">Unlimited</span>
                    </div>
                `;
            }

            // Format creation date
            let creationDate = '';
            if(account.timestamp) {
                const createDate = new Date(account.timestamp * 1000); // Convert Unix timestamp to milliseconds
                creationDate = createDate.toLocaleDateString();
            }

            // Format reseller display
            const resellerDisplay = account.reseller_name
                ? account.reseller_name
                : '<span style="color: #999; font-style: italic;">Not Assigned</span>';

            tr.innerHTML = `
                <td>${account.username || ''}</td>
                <td>${account.full_name || ''}</td>
                <td>${account.phone_number || ''}</td>
                <td>${account.mac || ''}</td>
                <td>${account.tariff_plan || ''}</td>
                <td>${resellerDisplay}</td>
                <td>${expirationCell}</td>
                <td>${creationDate}</td>
                <td>
                    <div class="action-buttons">
                        ${editButton}
                        ${deleteButton}
                        ${assignResellerButton}
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
    }

    // Update pagination info
    document.getElementById('accounts-pagination-info').textContent =
        `Showing ${startIndex + 1}-${endIndex} of ${totalItems} accounts`;

    // Render pagination buttons
    renderPaginationButtons();
}

function renderPaginationButtons() {
    const { currentPage, perPage, searchTerm, allAccounts, filteredAccounts } = accountsPagination;
    const accountsToDisplay = searchTerm ? filteredAccounts : allAccounts;
    const totalItems = accountsToDisplay.length;
    const totalPages = Math.ceil(totalItems / perPage);
    const paginationDiv = document.getElementById('accounts-pagination');

    if(totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }

    let buttonsHTML = '';

    // Previous button
    buttonsHTML += `<button onclick="goToAccountsPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>`;

    // Page numbers with ellipsis
    const maxButtons = 7;
    let startPage = Math.max(1, currentPage - 3);
    let endPage = Math.min(totalPages, currentPage + 3);

    // Show first page
    if(startPage > 1) {
        buttonsHTML += `<button class="page-btn ${currentPage === 1 ? 'active' : ''}" onclick="goToAccountsPage(1)">1</button>`;
        if(startPage > 2) {
            buttonsHTML += `<button class="ellipsis" disabled>...</button>`;
        }
    }

    // Show page numbers
    for(let i = startPage; i <= endPage; i++) {
        buttonsHTML += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToAccountsPage(${i})">${i}</button>`;
    }

    // Show last page
    if(endPage < totalPages) {
        if(endPage < totalPages - 1) {
            buttonsHTML += `<button class="ellipsis" disabled>...</button>`;
        }
        buttonsHTML += `<button class="page-btn ${currentPage === totalPages ? 'active' : ''}" onclick="goToAccountsPage(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    buttonsHTML += `<button onclick="goToAccountsPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>`;

    paginationDiv.innerHTML = buttonsHTML;
}

function goToAccountsPage(page) {
    const { searchTerm, allAccounts, filteredAccounts, perPage } = accountsPagination;
    const accountsToDisplay = searchTerm ? filteredAccounts : allAccounts;
    const totalPages = Math.ceil(accountsToDisplay.length / perPage);
    if(page < 1 || page > totalPages) return;

    accountsPagination.currentPage = page;
    renderAccountsPage();

    // Scroll to top of table
    document.getElementById('accounts-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changeAccountsPerPage() {
    const select = document.getElementById('accounts-per-page');
    accountsPagination.perPage = parseInt(select.value);
    accountsPagination.currentPage = 1; // Reset to first page
    renderAccountsPage();
}

function searchAccounts() {
    const searchTerm = document.getElementById('accounts-search').value.toLowerCase().trim();

    accountsPagination.searchTerm = searchTerm;

    if(searchTerm === '') {
        // No search - show all accounts
        accountsPagination.filteredAccounts = [];
    } else {
        // Filter accounts
        accountsPagination.filteredAccounts = accountsPagination.allAccounts.filter(account => {
            return (
                (account.username && account.username.toLowerCase().includes(searchTerm)) ||
                (account.full_name && account.full_name.toLowerCase().includes(searchTerm)) ||
                (account.mac && account.mac.toLowerCase().includes(searchTerm)) ||
                (account.tariff_plan && account.tariff_plan.toLowerCase().includes(searchTerm))
            );
        });
    }

    // Reset to first page and re-render
    accountsPagination.currentPage = 1;
    renderAccountsPage();
}

// Load Resellers
async function loadResellers() {
    try {
        const response = await fetch('get_resellers.php');
        const result = await response.json();

        const tbody = document.getElementById('resellers-tbody');

        if(result.error == 0 && result.resellers && result.resellers.length > 0) {
            tbody.innerHTML = '';
            document.getElementById('total-resellers').textContent = result.resellers.length;

            result.resellers.forEach(reseller => {
                const tr = document.createElement('tr');
                const resellerBalance = reseller.balance || 0;
                const resellerCurrency = reseller.currency_name || 'IRR';

                // Check if user is observer
                const isObserver = currentUser && currentUser.is_observer == 1;
                const isSuperAdmin = currentUser && currentUser.super_user == 1;

                // Make buttons disabled for observers
                const editButton = isObserver
                    ? `<button class="btn-sm btn-edit" disabled style="opacity: 0.5; cursor: not-allowed;">Edit</button>`
                    : `<button class="btn-sm btn-edit" onclick="editReseller(${reseller.id})">Edit</button>`;

                const adjustCreditButton = isObserver
                    ? `<button class="btn-sm btn-edit" disabled style="opacity: 0.5; cursor: not-allowed;">Adjust Credit</button>`
                    : `<button class="btn-sm btn-edit" onclick="adjustCredit(${reseller.id}, '${reseller.name}', ${resellerBalance}, '${resellerCurrency}')">Adjust Credit</button>`;

                const assignPlansButton = isObserver
                    ? `<button class="btn-sm btn-edit" disabled style="opacity: 0.5; cursor: not-allowed;">Assign Plans</button>`
                    : `<button class="btn-sm btn-edit" onclick="assignPlans(${reseller.id}, '${reseller.name}', '${reseller.plans || ''}', '${reseller.currency_id}')">Assign Plans</button>`;

                const deleteButton = isSuperAdmin
                    ? `<button class="btn-sm btn-delete" onclick="deleteReseller(${reseller.id})">Delete</button>`
                    : '';

                tr.innerHTML = `
                    <td>${reseller.name || ''}</td>
                    <td>${reseller.username || ''}</td>
                    <td>${reseller.email || ''}</td>
                    <td>${getCurrencySymbol(reseller.currency_name)}${formatBalance(reseller.balance || 0, reseller.currency_name)}</td>
                    <td>${reseller.max_users || 'Unlimited'}</td>
                    <td>
                        <div class="action-buttons">
                            ${editButton}
                            ${adjustCreditButton}
                            ${assignPlansButton}
                            ${deleteButton}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#999">No resellers found</td></tr>';
        }
    } catch(error) {
        console.error('Error loading resellers:', error);
        showAlert('Error loading resellers', 'error');
    }
}

// Load Plans
async function loadPlans() {
    try {
        // Get view mode preference for reseller admins
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const url = `get_plans.php?viewAllAccounts=${viewAllAccounts}`;
        console.log('[loadPlans] Fetching plans with URL:', url);

        const response = await fetch(url);
        const result = await response.json();
        console.log('[loadPlans] Received plans:', result);

        const tbody = document.getElementById('plans-tbody');
        const planSelect = document.getElementById('plan-select');
        const resellerPlansSelect = document.getElementById('reseller-plans-select');
        const assignPlansSelect = document.getElementById('assign-plans-select');

        if(result.error == 0 && result.plans && result.plans.length > 0) {
            console.log('[loadPlans] Loading', result.plans.length, 'plans into dropdowns');
            tbody.innerHTML = '';
            planSelect.innerHTML = '<option value="0">No Plan</option>';
            if(resellerPlansSelect) resellerPlansSelect.innerHTML = '';
            document.getElementById('total-plans').textContent = result.plans.length;

            // Store plans globally for use in assign modal
            availablePlans = result.plans;

            result.plans.forEach(plan => {
                const tr = document.createElement('tr');
                // Normalize currency display (IRT -> IRR)
                const displayCurrency = (plan.currency_id === 'IRT') ? 'IRR' : plan.currency_id;
                const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);

                // Check if user is observer (observers can't delete)
                const isObserver = currentUser && currentUser.is_observer == 1;
                const deleteButton = isObserver
                    ? `<button class="btn-sm btn-delete" disabled style="opacity: 0.5; cursor: not-allowed;">Delete</button>`
                    : `<button class="btn-sm btn-delete" onclick="deletePlan('${plan.external_id}', '${plan.currency_id}')">Delete</button>`;

                tr.innerHTML = `
                    <td>${plan.external_id || ''}</td>
                    <td>${plan.name || ''}</td>
                    <td>${displayCurrency || ''}</td>
                    <td>${formattedPrice}</td>
                    <td>${plan.days || 0}</td>
                    <td>
                        <div class="action-buttons">
                            ${deleteButton}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);

                // Add to plan select for account creation
                // Use planID-currency format to ensure correct plan is selected
                const option = document.createElement('option');
                option.value = `${plan.external_id}-${plan.currency_id}`;
                option.textContent = `${plan.name || plan.external_id} - ${formattedPrice} (${plan.days} days)`;
                planSelect.appendChild(option);

                // Add to reseller plan assignment dropdown with planID-currency format
                if(resellerPlansSelect) {
                    const resellerOption = document.createElement('option');
                    resellerOption.value = `${plan.external_id}-${plan.currency_id}`;
                    resellerOption.textContent = `${plan.name || plan.external_id} - ${formattedPrice} (${plan.days} days)`;
                    resellerPlansSelect.appendChild(resellerOption);
                }
            });
        } else {
            console.log('[loadPlans] No plans to display. Error:', result.error, 'Plans:', result.plans);
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#999">No plans found</td></tr>';
            planSelect.innerHTML = '<option value="0">No Plan</option>';
            document.getElementById('total-plans').textContent = '0';
        }
    } catch(error) {
        console.error('Error loading plans:', error);
        showAlert('Error loading plans', 'error');
    }
}

// Global variables to store tariffs and plans
let availableTariffs = [];
let availablePlans = [];

// Load Tariffs from Stalker Portal Server (auto-fetch on login)
async function loadTariffs() {
    try {
        const response = await fetch('get_tariffs.php');
        const result = await response.json();

        if (result.error === 0 && result.tariffs) {
            availableTariffs = result.tariffs;
            console.log(`Loaded ${result.count} tariffs from server`);

            // Populate tariff dropdown in Add Plan modal
            populateTariffDropdown();
        } else {
            console.error('Failed to load tariffs:', result.message);
        }
    } catch (error) {
        console.error('Error loading tariffs:', error);
    }
}

// Populate tariff dropdown in Add Plan modal
function populateTariffDropdown() {
    const tariffSelect = document.getElementById('tariff-select');
    if (!tariffSelect) return;

    // Clear existing options except the first one
    tariffSelect.innerHTML = '<option value="">-- Select a tariff --</option>';

    availableTariffs.forEach(tariff => {
        const option = document.createElement('option');
        option.value = tariff.id;
        option.textContent = `${tariff.name} (${tariff.days} days)`;
        option.dataset.name = tariff.name;
        option.dataset.days = tariff.days;
        tariffSelect.appendChild(option);
    });
}

// Update plan details when tariff is selected
function updatePlanDetails(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];

    if (selectedOption.value) {
        // Auto-fill name and days from selected tariff
        document.getElementById('plan-name-input').value = selectedOption.dataset.name;
        document.getElementById('plan-days-input').value = selectedOption.dataset.days;
    } else {
        // Clear fields if no tariff selected
        document.getElementById('plan-name-input').value = '';
        document.getElementById('plan-days-input').value = '';
    }
}

// Global variable to store themes
let availableThemes = [];

// Load Themes from server
async function loadThemes() {
    try {
        const response = await fetch('get_themes.php');
        const result = await response.json();

        if (result.error === 0 && result.themes) {
            availableThemes = result.themes;
            console.log(`Loaded ${result.themes.length} themes from server`);

            // Populate theme dropdowns in Add and Edit Reseller modals
            populateThemeDropdowns();
        } else {
            console.error('Failed to load themes:', result.err_msg);
        }
    } catch (error) {
        console.error('Error loading themes:', error);
    }
}

// Populate theme dropdowns in Add and Edit Reseller modals
function populateThemeDropdowns() {
    const addThemeSelect = document.getElementById('add-reseller-theme');
    const editThemeSelect = document.getElementById('edit-reseller-theme');

    if (!addThemeSelect && !editThemeSelect) return;

    // Find default theme
    const defaultTheme = availableThemes.find(theme => theme.is_default) || availableThemes[0];

    // Populate Add Reseller theme dropdown
    if (addThemeSelect) {
        addThemeSelect.innerHTML = '';
        availableThemes.forEach(theme => {
            const option = document.createElement('option');
            option.value = theme.id;
            option.textContent = theme.name;
            if (theme.is_default || theme.id === defaultTheme.id) {
                option.selected = true;
            }
            addThemeSelect.appendChild(option);
        });
    }

    // Populate Edit Reseller theme dropdown
    if (editThemeSelect) {
        editThemeSelect.innerHTML = '';
        availableThemes.forEach(theme => {
            const option = document.createElement('option');
            option.value = theme.id;
            option.textContent = theme.name;
            editThemeSelect.appendChild(option);
        });
    }
}

// Load Transactions
async function loadTransactions() {
    try {
        const response = await fetch('get_transactions.php');
        const result = await response.json();

        const tbody = document.getElementById('transactions-tbody');

        // Check if current user is admin or observer (to show reseller column)
        const isSuperAdmin = currentUser && currentUser.super_user == 1;
        const isObserver = currentUser && currentUser.is_observer == 1;
        const showResellerColumn = isSuperAdmin || isObserver;

        // Show/hide reseller column header based on user type
        const resellerHeader = document.getElementById('reseller-column-header');
        if(resellerHeader) {
            resellerHeader.style.display = showResellerColumn ? '' : 'none';
        }

        if(result.error == 0 && result.transactions && result.transactions.length > 0) {
            tbody.innerHTML = '';

            result.transactions.forEach(tx => {
                const tr = document.createElement('tr');
                const type = tx.type == 1 ? 'Credit' : 'Debit';
                const currencySymbol = getCurrencySymbol(tx.currency);
                const formattedAmount = formatBalance(tx.amount, tx.currency);

                // Build reseller column if needed
                const resellerColumn = showResellerColumn
                    ? `<td>${tx.reseller_name || tx.reseller_username || 'N/A'}</td>`
                    : '';

                tr.innerHTML = `
                    <td>${new Date(tx.timestamp * 1000).toLocaleDateString()}</td>
                    <td>${currencySymbol}${formattedAmount}</td>
                    <td>${tx.currency || ''}</td>
                    <td><span class="badge ${tx.type == 1 ? 'active' : 'inactive'}">${type}</span></td>
                    ${resellerColumn}
                    <td>${tx.details || ''}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            const colspan = showResellerColumn ? '6' : '5';
            tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center;padding:40px;color:#999">No transactions found</td></tr>`;
        }
    } catch(error) {
        console.error('Error loading transactions:', error);
        showAlert('Error loading transactions', 'error');
    }
}

// Add Account
async function addAccount(e) {
    e.preventDefault();

    // Validate MAC address before submission
    const macInput = e.target.querySelector('input[name="mac"]');
    if (!validateMacInput(macInput)) {
        return;
    }

    const formData = new FormData(e.target);

    try {
        const response = await fetch('add_account.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Account created successfully!', 'success');
            closeModal('addAccountModal');
            e.target.reset();
            loadAccounts();
            checkAuth(); // Refresh stats
        } else {
            showAlert(result.err_msg || 'Error creating account', 'error');
        }
    } catch(error) {
        showAlert('Error creating account: ' + error.message, 'error');
    }
}

// Add Reseller
async function addReseller(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    // Get selected plans from multi-select
    const plansSelect = document.getElementById('reseller-plans-select');
    const selectedPlans = Array.from(plansSelect.selectedOptions).map(opt => opt.value);
    const plansString = selectedPlans.join(',');

    formData.set('plans', plansString);
    formData.append('use_ip_ranges', '');

    // Handle permissions checkboxes
    const canEditAccounts = formData.get('can_edit_accounts') === '1' ? '1' : '0';
    const canAddAccounts = formData.get('can_add_accounts') === '1' ? '1' : '0';
    const canDeleteAccounts = formData.get('can_delete_accounts') === '1' ? '1' : '0';
    const isAdmin = formData.get('is_admin') === '1' ? '1' : '0';
    const isObserver = formData.get('is_observer') === '1' ? '1' : '0';

    // Format: can_edit|can_add|is_reseller_admin|can_delete|reserved
    const permissions = `${canEditAccounts}|${canAddAccounts}|${isAdmin}|${canDeleteAccounts}|1`;

    formData.delete('is_admin'); // Remove is_admin as it's now part of permissions
    formData.set('permissions', permissions);
    formData.set('is_observer', isObserver); // Add is_observer to form data

    try {
        const response = await fetch('add_reseller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Reseller created successfully!', 'success');
            closeModal('addResellerModal');
            e.target.reset();
            loadResellers();
        } else {
            showAlert(result.err_msg || 'Error creating reseller', 'error');
        }
    } catch(error) {
        showAlert('Error creating reseller: ' + error.message, 'error');
    }
}

// Add Plan
async function addPlan(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData).toString();

    try {
        const response = await fetch('add_plan.php?' + params, {
            method: 'GET'
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Plan created successfully!', 'success');
            closeModal('addPlanModal');
            e.target.reset();
            loadPlans();
        } else {
            showAlert(result.err_msg || 'Error creating plan', 'error');
        }
    } catch(error) {
        showAlert('Error creating plan: ' + error.message, 'error');
    }
}

// Delete Account
async function deleteAccount(username) {
    if(!confirm('Are you sure you want to delete this account?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', username);

    try {
        const response = await fetch('remove_account.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Account deleted successfully!', 'success');
            loadAccounts();
            checkAuth();
        } else {
            showAlert(result.err_msg || 'Error deleting account', 'error');
        }
    } catch(error) {
        showAlert('Error deleting account: ' + error.message, 'error');
    }
}

// Assign Reseller
async function assignReseller(username, currentResellerId) {
    try {
        // Open modal
        const modal = document.getElementById('assignResellerModal');
        if(!modal) {
            console.error('Modal not found: assignResellerModal');
            showAlert('Error: Modal not found', 'error');
            return;
        }
        modal.classList.add('show');

        // Set account username
        document.getElementById('assign-account-username').value = username;
        document.getElementById('assign-account-display').value = username;

        // Load resellers list
        console.log('Fetching resellers...');
        const response = await fetch('get_resellers.php');
        const result = await response.json();
        console.log('Resellers response:', result);

        if(result.error == 0) {
            const select = document.getElementById('assign-reseller-select');
            select.innerHTML = '<option value="">-- Not Assigned --</option>';

            result.resellers.forEach(reseller => {
                const option = document.createElement('option');
                option.value = reseller.id;
                option.textContent = `${reseller.name} (${reseller.username})`;
                if(reseller.id == currentResellerId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            console.log('Resellers loaded successfully');
        } else {
            showAlert('Error loading resellers: ' + result.message, 'error');
        }
    } catch(error) {
        console.error('Error in assignReseller:', error);
        showAlert('Error loading resellers: ' + error.message, 'error');
    }
}

// Submit Assign Reseller
async function submitAssignReseller(event) {
    event.preventDefault();

    const formData = new FormData(event.target);

    try {
        const response = await fetch('assign_reseller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Reseller assigned successfully!', 'success');
            closeModal('assignResellerModal');
            loadAccounts();
        } else {
            showAlert('Error: ' + result.err_msg, 'error');
        }
    } catch(error) {
        showAlert('Error assigning reseller: ' + error.message, 'error');
    }
}

// Delete Plan
async function deletePlan(planId, currency) {
    if(!confirm('Are you sure you want to delete this plan?')) {
        return;
    }

    try {
        const response = await fetch(`remove_plan.php?plan=${planId}&currency=${currency}`, {
            method: 'GET'
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Plan deleted successfully!', 'success');
            loadPlans();
        } else {
            showAlert(result.err_msg || 'Error deleting plan', 'error');
        }
    } catch(error) {
        showAlert('Error deleting plan: ' + error.message, 'error');
    }
}

// Edit Reseller
async function editReseller(resellerId) {
    try {
        // Ensure themes are loaded before opening modal
        if (availableThemes.length === 0) {
            await loadThemes();
        }

        const response = await fetch('get_resellers.php');
        const result = await response.json();

        if (result.error == 0 && result.resellers) {
            const reseller = result.resellers.find(r => r.id == resellerId);
            if (reseller) {
                // Populate form fields
                document.getElementById('edit-reseller-id').value = reseller.id;
                document.getElementById('edit-reseller-username').value = reseller.username || '';
                document.getElementById('edit-reseller-name').value = reseller.name || '';
                document.getElementById('edit-reseller-email').value = reseller.email || '';
                document.getElementById('edit-reseller-max-users').value = reseller.max_users || 0;
                document.getElementById('edit-reseller-currency').value = reseller.currency_id || 'IRR';

                // Set theme value (use default if reseller has no theme set)
                const defaultTheme = availableThemes.find(t => t.is_default);
                const themeToSet = reseller.theme || (defaultTheme ? defaultTheme.id : 'HenSoft-TV Realistic-Centered SHOWBOX');
                document.getElementById('edit-reseller-theme').value = themeToSet;

                // Parse permissions (format: can_edit|can_add|is_reseller_admin|can_delete|reserved)
                const permissions = (reseller.permissions || '0|0|0|0|0').split('|');
                document.getElementById('edit-can-edit-accounts').checked = permissions[0] === '1';
                document.getElementById('edit-can-add-accounts').checked = permissions[1] === '1';
                document.getElementById('edit-is-admin').checked = permissions[2] === '1';
                document.getElementById('edit-can-delete-accounts').checked = permissions[3] === '1';

                // Set observer checkbox
                document.getElementById('edit-is-observer').checked = reseller.is_observer == 1;

                // Open modal
                openModal('editResellerModal');

                // Setup permission toggles and check initial state
                setupEditResellerPermissions();
            }
        }
    } catch (error) {
        console.error('Error loading reseller:', error);
        showAlert('Error loading reseller data', 'error');
    }
}

// Update Reseller
async function updateReseller(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    // Handle permissions checkboxes
    const canEditAccounts = formData.get('can_edit_accounts') === '1' ? '1' : '0';
    const canAddAccounts = formData.get('can_add_accounts') === '1' ? '1' : '0';
    const canDeleteAccounts = formData.get('can_delete_accounts') === '1' ? '1' : '0';
    const isAdmin = formData.get('is_admin') === '1' ? '1' : '0';
    const isObserver = formData.get('is_observer') === '1' ? '1' : '0';

    // Format: can_edit|can_add|is_reseller_admin|can_delete|reserved
    const permissions = `${canEditAccounts}|${canAddAccounts}|${isAdmin}|${canDeleteAccounts}|1`;

    formData.delete('is_admin'); // Remove is_admin as it's now part of permissions
    formData.set('permissions', permissions);
    formData.set('is_observer', isObserver); // Add is_observer to form data
    formData.append('use_ip_ranges', '');
    // Don't send plans field - let backend preserve existing plans
    // Plans are managed separately via "Assign Plans" button

    try {
        const response = await fetch('update_reseller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.error == 0) {
            // If there's a warning (partial success), show warning message
            if (result.warning) {
                showAlert(result.err_msg || 'Reseller updated with warnings', 'warning');
            } else {
                showAlert(result.err_msg || 'Reseller updated successfully!', 'success');
            }
            closeModal('editResellerModal');
            loadResellers();
        } else {
            showAlert(result.err_msg || 'Error updating reseller', 'error');
        }
    } catch (error) {
        showAlert('Error updating reseller: ' + error.message, 'error');
    }
}

// Delete Reseller
async function deleteReseller(resellerId) {
    if(!confirm('Are you sure you want to delete this reseller?')) {
        return;
    }

    try {
        const response = await fetch(`remove_reseller.php?id=${resellerId}`, {
            method: 'GET'
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Reseller deleted successfully!', 'success');
            loadResellers();
        } else {
            showAlert(result.err_msg || 'Error deleting reseller', 'error');
        }
    } catch(error) {
        showAlert('Error deleting reseller: ' + error.message, 'error');
    }
}

// Edit Account
async function editAccount(username) {
    try {
        // Find account data from the loaded accounts
        const account = accountsPagination.allAccounts.find(acc => acc.username === username);

        if(!account) {
            showAlert('Account not found', 'error');
            return;
        }

        // Populate form fields
        document.getElementById('edit-original-username').value = account.username;
        document.getElementById('edit-username').value = account.username;
        document.getElementById('edit-password').value = ''; // Keep blank
        document.getElementById('edit-name').value = account.full_name || '';
        document.getElementById('edit-email').value = account.email || '';
        document.getElementById('edit-phone').value = account.phone_number || '';
        document.getElementById('edit-comment').value = account.comment || '';

        // Set status (default to 1 if not set)
        const statusSelect = document.getElementById('edit-status');
        statusSelect.value = account.status !== undefined ? account.status : '1';

        // Load plans into dropdown
        await loadPlansForEdit();

        // Open modal
        openModal('editAccountModal');

    } catch(error) {
        console.error('Error loading account for edit:', error);
        showAlert('Error loading account data', 'error');
    }
}

async function loadPlansForEdit() {
    try {
        const response = await fetch('get_plans.php');
        const result = await response.json();

        const planSelect = document.getElementById('edit-plan');

        // Clear existing options except the first one
        planSelect.innerHTML = '<option value="0">SELECT ONE TO UPDATE</option>';

        if(result.error == 0 && result.plans) {
            result.plans.forEach(plan => {
                const option = document.createElement('option');
                option.value = plan.id;
                const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);
                option.textContent = `${plan.name} - ${formattedPrice} (${plan.days} days)`;
                planSelect.appendChild(option);
            });
        }
    } catch(error) {
        console.error('Error loading plans:', error);
    }
}

async function submitEditAccount(e) {
    console.log('submitEditAccount called');
    e.preventDefault();

    const formData = new FormData(e.target);
    const selectedPlan = document.getElementById('edit-plan').value;

    console.log('Form data:', Object.fromEntries(formData));
    console.log('Selected plan:', selectedPlan);

    // Confirm if renewing with a plan
    if(selectedPlan != '0') {
        if(!confirm('This will renew the account with the selected plan. Continue?')) {
            return false;
        }
    }

    try {
        console.log('Sending request to edit_account.php');
        const response = await fetch('edit_account.php', {
            method: 'POST',
            body: formData
        });

        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response result:', result);

        if(result.error == 0) {
            showAlert(result.message || 'Account updated successfully!', 'success');

            // Log debug info to console
            if(result.debug) {
                console.log('Edit Account Debug:', result.debug);
            }

            closeModal('editAccountModal');

            // Reload accounts to reflect changes
            loadAccounts();

            // Reload user info to update balance if reseller renewed
            if(currentUser && currentUser.super_user != 1) {
                checkAuth();
            }
        } else {
            showAlert(result.err_msg || 'Error updating account', 'error');

            // Log error details to console
            if(result.debug) {
                console.error('Edit Account Error Debug:', result.debug);
            }
        }
    } catch(error) {
        console.error('Error updating account:', error);
        showAlert('Error updating account: ' + error.message, 'error');
    }

    return false;
}

// Adjust Credit
function adjustCredit(resellerId, resellerName, currentBalance, currencyName) {
    document.getElementById('adjust-reseller-id').value = resellerId;
    document.getElementById('adjust-reseller-name').value = resellerName;

    // Handle null or undefined balance
    const balance = currentBalance || 0;
    document.getElementById('adjust-current-balance').value = getCurrencySymbol(currencyName) + formatBalance(balance, currencyName);
    document.getElementById('adjust-amount').value = '';
    openModal('adjustCreditModal');
}

async function submitCreditAdjustment(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    try {
        const response = await fetch('adjust_credit.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Credit adjusted successfully!', 'success');
            closeModal('adjustCreditModal');
            loadResellers();
        } else {
            showAlert(result.err_msg || 'Error adjusting credit', 'error');
        }
    } catch(error) {
        showAlert('Error adjusting credit: ' + error.message, 'error');
    }
}

// Assign Plans
function assignPlans(resellerId, resellerName, currentPlans, resellerCurrency) {
    document.getElementById('assign-reseller-id').value = resellerId;
    document.getElementById('assign-reseller-name').value = resellerName;

    // Get currently assigned plans
    const plansArray = currentPlans ? currentPlans.split(',') : [];

    // Populate checkboxes
    const checkboxContainer = document.getElementById('assign-plans-checkboxes');
    checkboxContainer.innerHTML = '';

    // Filter plans to only show those matching reseller's currency
    const matchingPlans = availablePlans.filter(plan => plan.currency_id === resellerCurrency);

    if (availablePlans.length === 0) {
        checkboxContainer.innerHTML = '<p style="color: var(--text-tertiary); text-align: center; padding: 20px;">No plans available. Please create plans first.</p>';
    } else if (matchingPlans.length === 0) {
        const displayCurrency = (resellerCurrency === 'IRT') ? 'IRR' : resellerCurrency;
        checkboxContainer.innerHTML = `<p style="color: var(--warning); text-align: center; padding: 20px;">No plans available for ${displayCurrency} currency. Please create plans with ${displayCurrency} currency first.</p>`;
    } else {
        matchingPlans.forEach(plan => {
            const displayCurrency = (plan.currency_id === 'IRT') ? 'IRR' : plan.currency_id;
            const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);
            const planValue = `${plan.external_id}-${plan.currency_id}`;
            const isChecked = plansArray.includes(planValue);

            const checkboxDiv = document.createElement('div');
            checkboxDiv.className = 'plan-checkbox-item';
            checkboxDiv.innerHTML = `
                <label class="plan-checkbox-label">
                    <input type="checkbox" name="plan_checkbox" value="${planValue}" ${isChecked ? 'checked' : ''}>
                    <div class="plan-info">
                        <span class="plan-name">${plan.name || plan.external_id}</span>
                        <span class="plan-details">${formattedPrice} • ${plan.days}d</span>
                    </div>
                </label>
            `;

            checkboxContainer.appendChild(checkboxDiv);
        });
    }

    openModal('assignPlansModal');
}

async function submitPlanAssignment(e) {
    e.preventDefault();

    const resellerId = document.getElementById('assign-reseller-id').value;
    const checkboxes = document.querySelectorAll('#assign-plans-checkboxes input[type="checkbox"]:checked');
    const selectedPlans = Array.from(checkboxes).map(cb => cb.value);
    const plansString = selectedPlans.join(',');

    const formData = new FormData();
    formData.append('reseller_id', resellerId);
    formData.append('plans', plansString);

    try {
        const response = await fetch('assign_plans.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Plans assigned successfully!', 'success');
            closeModal('assignPlansModal');
            loadResellers();
        } else {
            showAlert(result.err_msg || 'Error assigning plans', 'error');
        }
    } catch(error) {
        showAlert('Error assigning plans: ' + error.message, 'error');
    }
}

// Change Password
async function changePassword(e) {
    e.preventDefault();

    const oldPass = document.getElementById('old-password').value;
    const newPass = document.getElementById('new-password').value;
    const confirmPass = document.getElementById('confirm-password').value;

    if(newPass !== confirmPass) {
        showAlert('New passwords do not match', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('old_pass', oldPass);
    formData.append('new_pass', newPass);
    formData.append('renew_pass', confirmPass);

    try {
        const response = await fetch('update_password.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert('Password updated successfully!', 'success');
            e.target.reset();
            closeModal('changePasswordModal');
        } else {
            showAlert(result.err_msg || 'Error updating password', 'error');
        }
    } catch(error) {
        showAlert('Error updating password: ' + error.message, 'error');
    }
}

// Logout
function logout() {
    fetch('logout.php').then(() => {
        window.location.href = 'index.html';
    });
}

// Sync accounts from Stalker Portal
async function syncAccounts() {
    const syncBtn = document.querySelector('.btn-sync');
    const syncIcon = document.getElementById('sync-icon');
    const syncStatus = document.getElementById('sync-status');

    try {
        // Disable button and show syncing state
        syncBtn.disabled = true;
        syncBtn.classList.add('syncing');
        syncStatus.className = 'sync-status info';
        syncStatus.textContent = 'Syncing accounts from server...';

        const response = await fetch('sync_accounts.php', {
            method: 'POST'
        });

        const result = await response.json();

        if(result.error == 0) {
            syncStatus.className = 'sync-status success';

            let message = `✓ Sync completed! `;
            if(result.synced > 0) {
                message += `${result.synced} account(s) synced from server. `;
            }
            if(result.skipped > 0) {
                message += `${result.skipped} skipped. `;
            }
            message += `Total accounts: ${result.total_accounts}`;

            syncStatus.textContent = message;

            // Reload accounts table
            loadAccounts();

            // Update total accounts count
            document.getElementById('total-accounts').textContent = result.total_accounts;
        } else {
            syncStatus.className = 'sync-status error';
            syncStatus.textContent = `✗ Sync failed: ${result.err_msg}`;
        }

    } catch(error) {
        syncStatus.className = 'sync-status error';
        syncStatus.textContent = `✗ Sync failed: ${error.message}`;
    } finally {
        // Re-enable button and remove syncing state
        syncBtn.disabled = false;
        syncBtn.classList.remove('syncing');

        // Clear status after 5 seconds
        setTimeout(() => {
            syncStatus.className = 'sync-status';
            syncStatus.textContent = '';
        }, 5000);
    }
}

// ===========================
// Report Export Functions
// ===========================

/**
 * Export report data to PDF or Excel
 */
function exportReport(reportType, format) {
    // Get filtered accounts based on report type
    const filteredAccounts = getFilteredAccountsForReport(reportType);

    if (!filteredAccounts || filteredAccounts.length === 0) {
        alert('No data to export for this report.');
        return;
    }

    // Get report title and filename
    const reportInfo = getReportInfo(reportType);

    if (format === 'excel') {
        exportToExcel(filteredAccounts, reportInfo);
    } else if (format === 'pdf') {
        exportToPDF(filteredAccounts, reportInfo);
    }
}

/**
 * Get filtered accounts based on report type
 */
function getFilteredAccountsForReport(reportType) {
    const now = new Date();
    const allAccounts = accountsPagination.allAccounts;

    if (!allAccounts || allAccounts.length === 0) {
        return [];
    }

    if (reportType === 'expired-dynamic') {
        const filterSelect = document.getElementById('expired-filter');
        let days = parseInt(filterSelect.value);

        if (filterSelect.value === 'custom') {
            days = parseInt(document.getElementById('expired-custom-days').value) || 30;
        }

        const startDate = new Date();
        startDate.setDate(now.getDate() - days);

        return allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= startDate && expirationDate < now;
        });

    } else if (reportType === 'expiring-dynamic') {
        const filterSelect = document.getElementById('expiring-filter');
        let days = parseInt(filterSelect.value);

        if (filterSelect.value === 'custom') {
            days = parseInt(document.getElementById('expiring-custom-days').value) || 14;
        }

        const endDate = new Date();
        endDate.setDate(now.getDate() + days);

        return allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now && expirationDate <= endDate;
        });

    } else if (reportType === 'expired-all') {
        return allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate < now;
        });

    } else if (reportType === 'expiring-soon') {
        const twoWeeksFromNow = new Date();
        twoWeeksFromNow.setDate(now.getDate() + 14);

        return allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now && expirationDate <= twoWeeksFromNow;
        });

    } else if (reportType === 'all-accounts') {
        return allAccounts;

    } else if (reportType === 'active-accounts') {
        return allAccounts.filter(account => {
            if (!account.end_date) return true;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= now;
        });

    } else if (reportType === 'unlimited-plans') {
        return allAccounts.filter(account => {
            return !account.end_date || account.end_date === null || account.end_date === '';
        });

    } else if (reportType === 'expired-last-month-static') {
        const oneMonthAgo = new Date();
        oneMonthAgo.setDate(now.getDate() - 30);

        return allAccounts.filter(account => {
            if (!account.end_date) return false;
            const expirationDate = new Date(account.end_date);
            return expirationDate >= oneMonthAgo && expirationDate < now;
        });
    }

    return [];
}

/**
 * Get report information (title and filename)
 */
function getReportInfo(reportType) {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];

    const reportMap = {
        'expired-dynamic': {
            title: 'Expired & Not Renewed Accounts',
            filename: `expired_accounts_${dateStr}`
        },
        'expiring-dynamic': {
            title: 'Expiring Accounts in Selected Period',
            filename: `expiring_accounts_${dateStr}`
        },
        'expired-all': {
            title: 'All Expired Accounts',
            filename: `all_expired_accounts_${dateStr}`
        },
        'expiring-soon': {
            title: 'Accounts Expiring Soon (Next 2 Weeks)',
            filename: `expiring_soon_${dateStr}`
        },
        'all-accounts': {
            title: 'All Accounts',
            filename: `all_accounts_${dateStr}`
        },
        'active-accounts': {
            title: 'Active Accounts',
            filename: `active_accounts_${dateStr}`
        },
        'unlimited-plans': {
            title: 'Accounts with Unlimited Plans',
            filename: `unlimited_plans_${dateStr}`
        },
        'expired-last-month-static': {
            title: 'Accounts Expired Last Month',
            filename: `expired_last_month_${dateStr}`
        }
    };

    return reportMap[reportType] || { title: 'Report', filename: `report_${dateStr}` };
}

/**
 * Export accounts to Excel format
 */
function exportToExcel(accounts, reportInfo) {
    // Prepare data for Excel
    const excelData = accounts.map(account => ({
        'MAC Address': account.mac || '',
        'Full Name': account.full_name || '',
        'Phone': account.phone_number || '',
        'Status': account.status == 1 ? 'Active' : 'Inactive',
        'Expiry Date': account.end_date || 'Unlimited',
        'Reseller': account.reseller_name || 'N/A',
        'Created': account.created || '',
        'Account ID': account.id || ''
    }));

    // Create worksheet
    const worksheet = XLSX.utils.json_to_sheet(excelData);

    // Set column widths
    const colWidths = [
        { wch: 20 }, // MAC Address
        { wch: 25 }, // Full Name
        { wch: 15 }, // Phone
        { wch: 12 }, // Status
        { wch: 15 }, // Expiry Date
        { wch: 20 }, // Reseller
        { wch: 15 }, // Created
        { wch: 10 }  // Account ID
    ];
    worksheet['!cols'] = colWidths;

    // Create workbook
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Accounts');

    // Generate Excel file and download
    XLSX.writeFile(workbook, `${reportInfo.filename}.xlsx`);
}

/**
 * Export accounts to PDF format
 */
function exportToPDF(accounts, reportInfo) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4'); // Landscape orientation

    // Add title
    doc.setFontSize(18);
    doc.text(reportInfo.title, 14, 20);

    // Add export date
    doc.setFontSize(10);
    const now = new Date();
    doc.text(`Generated: ${now.toLocaleString()}`, 14, 28);
    doc.text(`Total Records: ${accounts.length}`, 14, 34);

    // Prepare table data
    const tableData = accounts.map(account => [
        account.mac || '',
        account.full_name || '',
        account.phone_number || '',
        account.status == 1 ? 'Active' : 'Inactive',
        account.end_date || 'Unlimited',
        account.reseller_name || 'N/A'
    ]);

    // Add table
    doc.autoTable({
        startY: 40,
        head: [['MAC Address', 'Full Name', 'Phone', 'Status', 'Expiry Date', 'Reseller']],
        body: tableData,
        theme: 'grid',
        styles: {
            fontSize: 8,
            cellPadding: 2
        },
        headStyles: {
            fillColor: [102, 126, 234],
            textColor: 255,
            fontStyle: 'bold'
        },
        alternateRowStyles: {
            fillColor: [245, 247, 250]
        },
        columnStyles: {
            0: { cellWidth: 40 },  // MAC Address
            1: { cellWidth: 50 },  // Full Name
            2: { cellWidth: 35 },  // Phone
            3: { cellWidth: 25 },  // Status
            4: { cellWidth: 35 },  // Expiry Date
            5: { cellWidth: 40 }   // Reseller
        }
    });

    // Save PDF
    doc.save(`${reportInfo.filename}.pdf`);
}

// ===========================
// STB Control Functions
// ===========================

/**
 * Send event to STB device
 */
async function sendStbEvent(event) {
    event.preventDefault();

    // Validate MAC address before submission
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

        if(result.error == 0) {
            showAlert(result.message, 'success');
            event.target.reset();

            // Add to history
            addStbHistory('Event', formData.get('event'), formData.get('mac'));
        } else {
            showAlert(result.err_msg || 'Error sending event', 'error');
        }
    } catch(error) {
        console.error('Error sending STB event:', error);
        showAlert('Error sending event: ' + error.message, 'error');
    }
}

/**
 * Send message to STB device
 */
async function sendStbMessage(event) {
    event.preventDefault();

    // Validate MAC address before submission
    const macInput = event.target.querySelector('input[name="mac"]');
    if (!validateMacInput(macInput)) {
        return;
    }

    const formData = new FormData(event.target);

    try {
        const response = await fetch('send_stb_message.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert(result.message, 'success');
            event.target.reset();

            // Add to history
            addStbHistory('Message', formData.get('message').substring(0, 50) + '...', formData.get('mac'));
        } else {
            showAlert(result.err_msg || 'Error sending message', 'error');
        }
    } catch(error) {
        console.error('Error sending STB message:', error);
        showAlert('Error sending message: ' + error.message, 'error');
    }
}

/**
 * Handle event type change to show/hide channel field
 */
document.addEventListener('DOMContentLoaded', function() {
    const eventTypeSelect = document.getElementById('event-type');
    const channelField = document.getElementById('channel-field');

    if(eventTypeSelect && channelField) {
        eventTypeSelect.addEventListener('change', function() {
            const eventType = this.value;

            // Show channel field for events that need it
            if(eventType === 'play_channel' || eventType === 'play_radio_channel') {
                channelField.style.display = 'block';
                document.getElementById('channel-id').required = true;
            } else {
                channelField.style.display = 'none';
                document.getElementById('channel-id').required = false;
                document.getElementById('channel-id').value = '';
            }
        });
    }
});

/**
 * Add action to STB history
 */
function addStbHistory(type, action, mac) {
    const historyContainer = document.getElementById('stb-history');

    if(!historyContainer) return;

    // Remove "no actions" message if present
    if(historyContainer.querySelector('p')) {
        historyContainer.innerHTML = '';
    }

    const historyItem = document.createElement('div');
    historyItem.className = 'stb-history-item';

    const now = new Date();
    const timeString = now.toLocaleTimeString();

    historyItem.innerHTML = `
        <div class="stb-history-time">${timeString}</div>
        <div class="stb-history-details">
            <strong>${type}:</strong> ${action} → <code>${mac}</code>
        </div>
    `;

    // Add to top of list
    historyContainer.insertBefore(historyItem, historyContainer.firstChild);

    // Keep only last 10 items
    while(historyContainer.children.length > 10) {
        historyContainer.removeChild(historyContainer.lastChild);
    }
}

// ===========================
// Permission Management Functions
// ===========================

/**
 * Hide all action buttons and controls for observer users
 * Observers can view everything but cannot modify anything
 */
function hideObserverActions() {
    // Hide all buttons with specific classes
    const actionsToHide = [
        '.btn-primary',    // Add buttons
        '.btn-edit',       // Edit buttons
        '.btn-delete',     // Delete buttons
        '.btn-sm',         // Small action buttons
        '.btn-assign',     // Assign buttons
        'button[onclick*="add"]',         // Add action buttons
        'button[onclick*="edit"]',        // Edit action buttons
        'button[onclick*="delete"]',      // Delete action buttons
        'button[onclick*="assign"]',      // Assign action buttons
        'button[onclick*="renew"]',       // Renew action buttons
        'button[onclick*="Reseller"]',    // Reseller-related buttons
        'button[onclick*="Account"]',     // Account-related buttons
        'button[onclick*="Plan"]',        // Plan-related buttons
        '#sync-section',                  // Sync section
        '#view-mode-toggle'               // View mode toggle
    ];

    actionsToHide.forEach(selector => {
        document.querySelectorAll(selector).forEach(element => {
            element.style.display = 'none';
        });
    });

    // Add a read-only notice banner at the top
    const content = document.querySelector('.content');
    if (content) {
        const banner = document.createElement('div');
        banner.style.cssText = 'background: #fbbf24; color: #78350f; padding: 12px 20px; text-align: center; font-weight: 500; margin-bottom: 20px; border-radius: 8px;';
        banner.textContent = '👁️ Observer Mode: You can view all data but cannot make any changes';
        content.insertBefore(banner, content.firstChild);
    }

    console.log('[Observer] All action buttons have been hidden');
}

/**
 * Toggle other permissions based on admin checkbox state
 * Preserves the checkbox states when hiding/showing them
 */
function handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, observerCheckbox) {
    const isAdmin = adminCheckbox ? adminCheckbox.checked : false;
    const isObserver = observerCheckbox ? observerCheckbox.checked : false;

    if (isObserver) {
        // Observer is checked - hide and uncheck Admin and all other permissions
        // Observer and Admin are mutually exclusive
        if (adminCheckbox) {
            adminCheckbox.closest('.permission-item').style.display = 'none';
            adminCheckbox.checked = false;
        }
        canEditCheckbox.closest('.permission-item').style.display = 'none';
        canAddCheckbox.closest('.permission-item').style.display = 'none';
        if (canDeleteCheckbox) {
            canDeleteCheckbox.closest('.permission-item').style.display = 'none';
        }
        // Uncheck all permissions when observer is checked
        canEditCheckbox.checked = false;
        canAddCheckbox.checked = false;
        if (canDeleteCheckbox) canDeleteCheckbox.checked = false;
    } else if (isAdmin) {
        // Admin is checked - hide and uncheck Observer and all other permissions
        // Observer and Admin are mutually exclusive
        if (observerCheckbox) {
            observerCheckbox.closest('.permission-item').style.display = 'none';
            observerCheckbox.checked = false;
        }
        canEditCheckbox.closest('.permission-item').style.display = 'none';
        canAddCheckbox.closest('.permission-item').style.display = 'none';
        if (canDeleteCheckbox) {
            canDeleteCheckbox.closest('.permission-item').style.display = 'none';
        }
    } else {
        // Neither admin nor observer is checked - show all permission items
        if (adminCheckbox) adminCheckbox.closest('.permission-item').style.display = 'flex';
        if (observerCheckbox) observerCheckbox.closest('.permission-item').style.display = 'flex';
        canEditCheckbox.closest('.permission-item').style.display = 'flex';
        canAddCheckbox.closest('.permission-item').style.display = 'flex';
        if (canDeleteCheckbox) {
            canDeleteCheckbox.closest('.permission-item').style.display = 'flex';
        }
    }
}

/**
 * Setup permission toggle event listeners for Add Reseller modal
 */
function setupAddResellerPermissions() {
    const adminCheckbox = document.querySelector('#addResellerModal input[name="is_admin"]');
    const observerCheckbox = document.querySelector('#addResellerModal input[name="is_observer"]');
    const canEditCheckbox = document.querySelector('#addResellerModal input[name="can_edit_accounts"]');
    const canAddCheckbox = document.querySelector('#addResellerModal input[name="can_add_accounts"]');
    const canDeleteCheckbox = document.querySelector('#addResellerModal input[name="can_delete_accounts"]');

    if (adminCheckbox && canEditCheckbox && canAddCheckbox) {
        adminCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(this, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, observerCheckbox);
        });
    }

    if (observerCheckbox) {
        observerCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, this);
        });
    }
}

/**
 * Setup permission toggle event listeners for Edit Reseller modal
 */
function setupEditResellerPermissions() {
    const adminCheckbox = document.getElementById('edit-is-admin');
    const observerCheckbox = document.getElementById('edit-is-observer');
    const canEditCheckbox = document.getElementById('edit-can-edit-accounts');
    const canAddCheckbox = document.getElementById('edit-can-add-accounts');
    const canDeleteCheckbox = document.getElementById('edit-can-delete-accounts');

    if (adminCheckbox && canEditCheckbox && canAddCheckbox) {
        adminCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(this, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, observerCheckbox);
        });

        // Initial state check when modal is opened
        handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, observerCheckbox);
    }

    if (observerCheckbox) {
        observerCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, this);
        });
    }
}

/**
 * Toggle between viewing all accounts and own accounts (for reseller admins)
 */
async function toggleAccountViewMode() {
    const viewAllAccounts = document.getElementById('view-all-accounts').checked;
    console.log('[toggleAccountViewMode] Toggled to:', viewAllAccounts ? 'All Accounts' : 'My Accounts');

    // Save preference to localStorage
    localStorage.setItem('viewAllAccounts', viewAllAccounts);

    // Update label
    updateViewModeLabel(viewAllAccounts);

    // Update total accounts count
    await updateAccountCount(viewAllAccounts);

    // Reload accounts with new filter (await to ensure it completes)
    console.log('[toggleAccountViewMode] Calling loadAccounts()...');
    await loadAccounts();

    // Reload plans with new filter (for reseller admins)
    console.log('[toggleAccountViewMode] Calling loadPlans()...');
    await loadPlans();

    // Explicitly refresh dynamic reports to ensure they update
    console.log('[toggleAccountViewMode] Refreshing dynamic reports...');
    if(accountsPagination.allAccounts && accountsPagination.allAccounts.length > 0) {
        updateDynamicReports();
    }
}

/**
 * Update the view mode label text and show/hide dynamic reports
 */
function updateViewModeLabel(viewAllAccounts) {
    const label = document.getElementById('view-mode-label');
    const dynamicReportsSection = document.getElementById('dynamic-reports-section');
    const dynamicReportsCards = document.getElementById('dynamic-reports-cards');

    if (viewAllAccounts) {
        label.textContent = 'Viewing All Accounts';
        label.style.color = 'var(--primary)';
        // Show dynamic reports when viewing all accounts
        if(dynamicReportsSection) dynamicReportsSection.style.display = 'grid';
        if(dynamicReportsCards) dynamicReportsCards.style.display = 'grid';
    } else {
        label.textContent = 'Viewing My Accounts';
        label.style.color = 'var(--text-secondary)';
        // Hide dynamic reports when viewing only own accounts
        if(dynamicReportsSection) dynamicReportsSection.style.display = 'none';
        if(dynamicReportsCards) dynamicReportsCards.style.display = 'none';
    }
}

/**
 * Update account count based on view mode
 */
async function updateAccountCount(viewAllAccounts) {
    try {
        const url = `get_user_info.php?viewAllAccounts=${viewAllAccounts}`;
        const response = await fetch(url);
        const result = await response.json();

        if (result.error == 0) {
            document.getElementById('total-accounts').textContent = result.total_accounts;
        }
    } catch (error) {
        console.error('Error updating account count:', error);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Dashboard] DOMContentLoaded - Starting initialization');

    initTheme();
    checkAuth();

    // Setup permission toggles
    setupAddResellerPermissions();

    // Initialize MAC address inputs immediately
    initAllMacInputs();

    // Initialize again after a delay to catch any dynamically loaded elements
    setTimeout(() => {
        console.log('[Dashboard] Re-initializing MAC inputs after delay');
        initAllMacInputs();
    }, 500);

    // Initialize again after 2 seconds to ensure everything is loaded
    setTimeout(() => {
        console.log('[Dashboard] Final MAC input initialization');
        initAllMacInputs();
    }, 2000);
});
