// ========================================
// ShowBox Dashboard v1.15.1
// ========================================

// ========================================
// Simple Debounce (v1.11.3)
// Prevents accidental double-clicks only
// ========================================

let isAccountsLoading = false;

// Store original dual server mode value for cancel functionality (moved here to avoid TDZ error)
let originalDualServerMode = false;

// Global storage for last call times (shared across all debounced functions)
const lastCallTimes = {};

// Simple time-based debounce - no locks, just time checking
function debounce(fn, key, cooldown = 200) {
    return function(...args) {
        const now = Date.now();
        const lastCall = lastCallTimes[key] || 0;
        const timeSinceLastCall = now - lastCall;

        // If called within cooldown period, ignore
        if (timeSinceLastCall < cooldown) {
            console.log(`[Debounce] Ignoring rapid click on ${key} (${timeSinceLastCall}ms < ${cooldown}ms)`);
            return;
        }

        // Update last call time and execute immediately
        lastCallTimes[key] = now;
        console.log(`[Debounce] Executing ${key}`);
        return fn.apply(this, args);
    };
}

// ========================================
// End of Debounce Mechanism
// ========================================

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
        // Only reload if there was already a controller (actual update, not first load)
        let hasController = !!navigator.serviceWorker.controller;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (hasController) {
                // This is an actual update, reload
                window.location.reload();
            } else {
                // First controller activation after page load, skip reload
                hasController = true;
                console.log('[PWA] Service Worker activated for first time, skipping reload');
            }
        });
    });
}

// Detect if running as installed PWA (v1.10.1)
function detectPWAMode() {
    // Check if running in standalone mode (installed PWA)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                        window.navigator.standalone ||
                        document.referrer.includes('android-app://');

    if (isStandalone) {
        document.body.classList.add('pwa-mode');
        console.log('[PWA] Running as installed PWA - bottom sheet modals enabled');
    } else {
        console.log('[PWA] Running in browser - centered modals enabled');
    }
}

// Run on page load
detectPWAMode();

// Auto-capitalize name input in PWA mode (v1.10.1)
function initNameCapitalization() {
    if (document.body.classList.contains('pwa-mode')) {
        const nameInput = document.getElementById('account-fullname');
        if (nameInput) {
            nameInput.addEventListener('input', function(e) {
                const cursorPosition = this.selectionStart;
                const words = this.value.split(' ');
                const capitalizedWords = words.map(word => {
                    if (word.length > 0) {
                        return word.charAt(0).toUpperCase() + word.slice(1);
                    }
                    return word;
                });
                const newValue = capitalizedWords.join(' ');

                // Only update if value changed to avoid cursor jump
                if (this.value !== newValue) {
                    this.value = newValue;
                    this.setSelectionRange(cursorPosition, cursorPosition);
                }
            });
            console.log('[PWA] Name auto-capitalization enabled');
        }
    }
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

// Push notification variables (v1.11.43)
let pushSubscription = null;
let vapidPublicKey = null;

// Pagination state
let accountsPagination = {
    currentPage: 1,
    perPage: 25,
    totalItems: 0,
    allAccounts: [],
    filteredAccounts: [],
    searchTerm: '',
    sortColumn: null,
    sortDirection: 'asc'
};

// Transactions pagination state
let transactionsPagination = {
    currentPage: 1,
    perPage: 25,
    totalItems: 0,
    allTransactions: [],
    sortColumn: 'timestamp',
    sortDirection: 'desc'  // Default: newest first
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
        const response = await fetch('api/get_user_info.php');
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

            // Hide Plans and Transactions tabs in bottom navigation (PWA) for observer
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const tabName = item.getAttribute('data-tab');
                if(tabName === 'plans' || tabName === 'transactions') {
                    item.style.display = 'none';
                }
            });

            // Add visual indicator that this is an observer account
            document.getElementById('username-display').innerHTML = 'Welcome, ' + result.user.name + ' <span style="color: #fbbf24; font-size: 12px;">(Observer)</span>';
        } else if(isSuperAdmin) {
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Show sync section for super admin only (HIDDEN v1.11.47)
            // document.getElementById('sync-section').style.display = 'block';

            // Show database backup section for super admin
            document.getElementById('database-backup-section').style.display = 'block';

            // Show Stalker Portal settings section for super admin only (NOT reseller admin)
            document.getElementById('stalker-settings-section').style.display = 'block';
            loadStalkerSettings();

            // Show Auto-Logout settings section for super admin only
            document.getElementById('auto-logout-settings-section').style.display = 'block';
            loadAutoLogoutSettings();

            // Show Mobile Auto-Logout settings button for super admin (v1.11.26)
            const mobileAutoLogoutBtn = document.getElementById('mobile-auto-logout-btn');
            if (mobileAutoLogoutBtn) {
                mobileAutoLogoutBtn.style.display = 'flex';
            }

            // Show Mobile Push Notifications button for super admin (v1.11.43)
            const mobilePushBtn = document.getElementById('mobile-push-btn');
            if (mobilePushBtn) {
                mobilePushBtn.style.display = 'flex';
            }

            // Hide Plans and Transactions tabs in bottom navigation (PWA) for super admin
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const tabName = item.getAttribute('data-tab');
                if(tabName === 'plans' || tabName === 'transactions') {
                    item.style.display = 'none';
                }
            });
        } else if(isResellerAdmin) {
            // Reseller admin: Has same features as super admin
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Show sync section for reseller admins (HIDDEN v1.11.47)
            // document.getElementById('sync-section').style.display = 'block';

            // Show database backup section for reseller admin
            document.getElementById('database-backup-section').style.display = 'block';

            // Show view mode toggle for reseller admins
            document.getElementById('view-mode-toggle').style.display = 'flex';

            // Load saved preference from localStorage (default to false for "My Accounts")
            const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
            document.getElementById('view-all-accounts').checked = viewAllAccounts;
            updateViewModeLabel(viewAllAccounts);

            // Update account count based on initial preference
            updateAccountCount(viewAllAccounts);

            // Show Mobile Push Notifications button for reseller admin (v1.11.43)
            const mobilePushBtnRA = document.getElementById('mobile-push-btn');
            if (mobilePushBtnRA) {
                mobilePushBtnRA.style.display = 'flex';
            }

            // Hide Plans and Transactions tabs in bottom navigation (PWA) for reseller admin
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const tabName = item.getAttribute('data-tab');
                if(tabName === 'plans' || tabName === 'transactions') {
                    item.style.display = 'none';
                }
            });
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

            // Check messaging tab permission
            checkMessagingTabPermission();

            // Hide admin-only stat cards for regular resellers
            document.querySelector('.stat-card:nth-child(3)').style.display = 'none'; // Total Resellers
            document.querySelector('.stat-card:nth-child(4)').style.display = 'none'; // Total Plans

            // Hide Plans, Messages and Resellers tabs in bottom navigation (PWA) for regular resellers
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const tabName = item.getAttribute('data-tab');
                if(tabName === 'plans' || tabName === 'messaging' || tabName === 'resellers') {
                    item.style.display = 'none';
                }
            });

            // Hide Add Plan button and Actions column for regular resellers (PWA)
            const addPlanBtn = document.getElementById('add-plan-btn');
            const actionsHeader = document.getElementById('plans-actions-header');
            if(addPlanBtn) addPlanBtn.style.display = 'none';
            if(actionsHeader) actionsHeader.style.display = 'none';

            // Show Mobile Push Notifications button for regular resellers (v1.11.48)
            // Resellers need this to receive expiry notifications for their accounts
            const mobilePushBtnReseller = document.getElementById('mobile-push-btn');
            if (mobilePushBtnReseller) {
                mobilePushBtnReseller.style.display = 'flex';
            }
        }

        // Show/hide reminder section based on permissions (v1.7.8)
        showReminderSection();

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
        const response = await fetch('api/sync_accounts.php', {
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
    // v1.11.63: Fix - Only access event.target if event exists (called from click)
    if (typeof event !== 'undefined' && event && event.target) {
        event.target.classList.add('active');
    }

    // Save current tab to localStorage for persistence across refreshes
    localStorage.setItem('currentTab', tabName);

    // iOS PWA Mobile: Update body class to show/hide stats and tabs (v1.10.1)
    if (window.innerWidth <= 768) {
        // Hide Settings page if it's open (v1.10.1 fix)
        const settingsPage = document.getElementById('mobile-settings-page');
        if (settingsPage && settingsPage.style.display !== 'none') {
            settingsPage.style.display = 'none';

            // Show main content elements
            const navbar = document.querySelector('.navbar');
            const statsGrid = document.querySelector('.stats-grid');
            const tabs = document.querySelector('.tabs');
            const content = document.querySelector('.content');

            if (navbar) navbar.style.display = '';
            if (statsGrid) statsGrid.style.display = '';
            if (tabs) tabs.style.display = '';
            if (content) content.style.display = '';
        }

        // Remove all mobile-tab-* classes
        document.body.classList.remove('mobile-tab-dashboard', 'mobile-tab-accounts', 'mobile-tab-resellers', 'mobile-tab-plans', 'mobile-tab-transactions', 'mobile-tab-stb-control', 'mobile-tab-messaging', 'mobile-tab-reports', 'mobile-tab-settings');

        // Add current tab class
        document.body.classList.add('mobile-tab-' + tabName);

        // For 'accounts', we want to show ONLY the accounts table (hide stats/tabs)
        // For 'dashboard', we want to show stats + tabs
        // The CSS will handle hiding based on body class
    }

    // Refresh dynamic reports when switching to reports tab
    if(tabName === 'reports' && accountsPagination.allAccounts) {
        updateDynamicReports();
    }

    // Load reminder settings when switching to messaging tab
    if(tabName === 'messaging') {
        loadReminderSettings();
    }

    // Load login history and audit log when switching to logs tab (v1.12.0)
    if(tabName === 'logs') {
        if (typeof initLoginHistory === 'function') {
            initLoginHistory();
        }
        // Load audit log for super admin (v1.12.0)
        if (typeof initAuditLog === 'function') {
            initAuditLog();
        }
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

/**
 * Phone Number Validation and Formatting Functions
 */

/**
 * Normalize phone number by removing leading zero
 * @param {string} phoneNumber - The phone number to normalize
 * @returns {string} - Normalized phone number
 */
function normalizePhoneNumber(phoneNumber) {
    if (!phoneNumber) return '';

    // Remove all non-digit characters
    let digits = phoneNumber.replace(/\D/g, '');

    // Remove leading zero if present
    if (digits.startsWith('0')) {
        digits = digits.substring(1);
    }

    return digits;
}

/**
 * Validate phone number format
 * @param {string} phoneNumber - The phone number to validate
 * @param {string} countryCode - The country code (e.g., +98)
 * @returns {object} - {valid: boolean, error: string}
 */
function validatePhoneNumber(phoneNumber, countryCode) {
    if (!phoneNumber) {
        return { valid: true, error: '' }; // Phone is optional
    }

    const normalized = normalizePhoneNumber(phoneNumber);

    // Check if phone number contains only digits after normalization
    if (!/^\d+$/.test(normalized)) {
        return { valid: false, error: 'Phone number must contain only digits' };
    }

    // Iran-specific validation
    if (countryCode === '+98') {
        if (normalized.length !== 10) {
            return { valid: false, error: 'Iranian phone number must be 10 digits (e.g., 9121234567)' };
        }
        if (!normalized.startsWith('9')) {
            return { valid: false, error: 'Iranian mobile number must start with 9' };
        }
    } else {
        // General validation for other countries
        if (normalized.length < 7 || normalized.length > 15) {
            return { valid: false, error: 'Phone number must be between 7 and 15 digits' };
        }
    }

    return { valid: true, error: '' };
}

/**
 * Get full phone number with country code
 * @param {string} countryCodeSelect - Country code selector value
 * @param {string} customCode - Custom country code input value
 * @param {string} phoneNumber - Phone number input value
 * @returns {string} - Full phone number with country code (always starts with +)
 */
function getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber) {
    if (!phoneNumber) return '';

    const normalized = normalizePhoneNumber(phoneNumber);
    if (!normalized) return '';

    let code = countryCodeSelect === 'custom' ? customCode : countryCodeSelect;

    // Ensure code starts with + sign
    if (code && !code.startsWith('+')) {
        code = '+' + code;
    }

    return code + normalized;
}

/**
 * Parse stored phone number into country code and number
 * @param {string} fullPhone - Full phone number with country code (e.g., +989121234567)
 * @returns {object} - {countryCode: string, phoneNumber: string}
 */
function parsePhoneNumber(fullPhone) {
    if (!fullPhone) return { countryCode: '+98', phoneNumber: '' };

    // List of known country codes (from our dropdown + common ones)
    const knownCountryCodes = ['+98', '+1', '+44', '+86', '+91', '+81', '+49', '+33', '+7', '+82', '+39', '+971', '+966', '+90', '+93'];

    // Try to match known country codes first
    for (const code of knownCountryCodes) {
        if (fullPhone.startsWith(code)) {
            return {
                countryCode: code,
                phoneNumber: fullPhone.substring(code.length)
            };
        }
    }

    // If no known code found, try generic pattern (shortest match first)
    // Try 1-digit, then 2-digit, then 3-digit, then 4-digit country codes
    for (let len = 1; len <= 4; len++) {
        const potentialCode = fullPhone.substring(0, len + 1); // +1 for the '+' symbol
        if (potentialCode.startsWith('+') && /^\+\d+$/.test(potentialCode)) {
            const restOfNumber = fullPhone.substring(len + 1);
            // Validate that rest is a reasonable phone number (7-15 digits)
            if (restOfNumber.length >= 7 && restOfNumber.length <= 15 && /^\d+$/.test(restOfNumber)) {
                return {
                    countryCode: potentialCode,
                    phoneNumber: restOfNumber
                };
            }
        }
    }

    // If no country code found, assume Iran
    return {
        countryCode: '+98',
        phoneNumber: fullPhone.replace(/^\+/, '')
    };
}

/**
 * Initialize phone input handlers
 * @param {string} countryCodeId - ID of country code select element
 * @param {string} customCodeId - ID of custom code input element
 * @param {string} phoneNumberId - ID of phone number input element
 */
function initPhoneInput(countryCodeId, customCodeId, phoneNumberId) {
    const countryCodeSelect = document.getElementById(countryCodeId);
    const customCodeInput = document.getElementById(customCodeId);
    const phoneNumberInput = document.getElementById(phoneNumberId);

    if (!countryCodeSelect || !customCodeInput || !phoneNumberInput) return;

    // Handle country code selection change
    countryCodeSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customCodeInput.style.display = 'inline-block';
            customCodeInput.focus();
        } else {
            customCodeInput.style.display = 'none';
            customCodeInput.value = '';
        }
    });

    // Validate phone number on blur
    phoneNumberInput.addEventListener('blur', function() {
        const countryCode = countryCodeSelect.value === 'custom' ? customCodeInput.value : countryCodeSelect.value;
        const validation = validatePhoneNumber(this.value, countryCode);

        if (!validation.valid && this.value) {
            showAlert(validation.error, 'error');
        }
    });

    // Auto-remove leading zero on input
    phoneNumberInput.addEventListener('input', function() {
        if (this.value.startsWith('0')) {
            this.value = this.value.substring(1);
        }
    });

    // Validate custom country code
    customCodeInput.addEventListener('input', function() {
        // Ensure it starts with +
        if (this.value && !this.value.startsWith('+')) {
            this.value = '+' + this.value;
        }
        // Remove any non-digit characters except +
        this.value = this.value.replace(/[^\d+]/g, '');
    });
}

// Modal functions
function openModalCore(modalId) {
    // Check if modal is already open (prevent double-opening)
    const modal = document.getElementById(modalId);
    if(!modal) {
        console.error('[openModal] Modal not found:', modalId);
        return;
    }

    if(modal.classList.contains('show')) {
        console.log('[openModal] Modal already open, ignoring duplicate call');
        return;
    }

    // CRITICAL FIX: Add 'show' class first, but DON'T lock body yet
    modal.classList.add('show');

    // Verify modal is actually visible before locking body
    setTimeout(() => {
        const computedStyle = window.getComputedStyle(modal);
        const isVisible = computedStyle.display !== 'none' && computedStyle.visibility !== 'hidden' && computedStyle.opacity !== '0';

        if (isVisible && modal.classList.contains('show')) {
            // Modal is confirmed visible - now lock body
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            console.log('[openModal] Modal visible, body locked');
        } else {
            // Modal failed to display - remove 'show' class and don't lock
            modal.classList.remove('show');
            console.error('[openModal] Modal failed to display, removed show class');
        }
    }, 50); // Small delay to let CSS transitions apply

    // Initialize MAC address inputs in the modal
    setTimeout(() => {
        initAllMacInputs();
    }, 10);

    // Initialize phone inputs based on modal
    if(modalId === 'addAccountModal') {
        // Auto-generate username and password
        document.getElementById('account-username').value = generateRandomString();
        document.getElementById('account-password').value = generateRandomString();

        // Initialize name capitalization for PWA mode
        initNameCapitalization();

        // Initialize phone input for Add Account modal
        setTimeout(() => {
            initPhoneInput('add-country-code', 'add-custom-code', 'add-phone-number');
        }, 10);

        // Check if user is reseller without admin permission
        // IMPORTANT: Super admin (super_user == 1) ALWAYS sees dropdown, never cards
        const isSuperUser = currentUser ? (currentUser.super_user == 1 || currentUser.super_user === '1') : false;
        const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
        const isResellerWithoutAdmin = !isSuperUser && !isResellerAdmin;

        // Check view mode for reseller admins (only applies to reseller admins, NOT super admins)
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

        // Super admin ALWAYS uses dropdown - never show cards for super admin
        const shouldShowCards = !isSuperUser && (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode);

        // Debug logging
        console.log('[Add Account Modal] User detection:', {
            currentUser: currentUser,
            super_user_raw: currentUser?.super_user,
            isSuperUser: isSuperUser,
            isResellerAdmin: isResellerAdmin,
            isResellerWithoutAdmin: isResellerWithoutAdmin,
            viewAllAccounts: viewAllAccounts,
            isResellerAdminInMyAccountsMode: isResellerAdminInMyAccountsMode,
            shouldShowCards: shouldShowCards,
            willShowDropdown: !shouldShowCards
        });

        if (shouldShowCards) {
            // Disable username and password fields (permanent restriction for resellers)
            // But allow reseller admins to edit username/password
            if (isResellerWithoutAdmin) {
                document.getElementById('account-username').readOnly = true;
                document.getElementById('account-password').readOnly = true;
            } else {
                document.getElementById('account-username').readOnly = false;
                document.getElementById('account-password').readOnly = false;
            }

            // Hide admin plan/status dropdowns
            document.getElementById('add-admin-plan-group').style.display = 'none';
            document.getElementById('add-admin-status-group').style.display = 'none';

            // Show reseller plan card selection
            document.getElementById('add-reseller-plan-section').style.display = 'block';

            // Load new device plans with error handling
            try {
                loadNewDevicePlans().catch(error => {
                    console.error('[openModal] Failed to load plans:', error);
                    // Modal should still be visible even if plans fail to load
                });
            } catch(error) {
                console.error('[openModal] Exception loading plans:', error);
            }
        } else {
            // Enable username and password fields for admin
            document.getElementById('account-username').readOnly = false;
            document.getElementById('account-password').readOnly = false;

            // Show admin plan/status dropdowns
            document.getElementById('add-admin-plan-group').style.display = 'block';
            document.getElementById('add-admin-status-group').style.display = 'block';

            // Hide reseller plan section
            document.getElementById('add-reseller-plan-section').style.display = 'none';
        }
    }

    if(modalId === 'editAccountModal') {
        // Initialize phone input for Edit Account modal
        setTimeout(() => {
            initPhoneInput('edit-country-code', 'edit-custom-code', 'edit-phone');
        }, 10);
    }
}

// Debounced wrapper for openModal (prevents rapid-fire modal openings)
// Use 100ms cooldown for quick synchronous function
const openModal = debounce(openModalCore, 'openModal', 100);

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');

    // Re-enable background scrolling on mobile (v1.10.1)
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';

    console.log('[closeModal] Modal closed successfully');

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
        // Set loading flag
        isAccountsLoading = true;

        // Get view mode preference for reseller admins
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const url = `api/get_accounts.php?viewAllAccounts=${viewAllAccounts}`;
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

            // Initialize sorting event listeners (only needs to be done once)
            initializeAccountsSorting();
        } else {
            const tbody = document.getElementById('accounts-tbody');
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
            document.getElementById('accounts-pagination').innerHTML = '';
            document.getElementById('accounts-pagination-info').textContent = '';
        }
    } catch(error) {
        console.error('Error loading accounts:', error);
        showAlert('Error loading accounts', 'error');
    } finally {
        // Always clear loading flag
        isAccountsLoading = false;
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

            // Show edit/renew button for everyone, but disabled for observers
            const editButton = isObserver
                ? `<button class="btn-sm btn-edit" disabled style="opacity: 0.5; cursor: not-allowed;">Edit/Renew</button>`
                : `<button class="btn-sm btn-edit" onclick="editAccount('${account.username}')">Edit/Renew</button>`;

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

            // Format reseller display
            const resellerDisplay = account.reseller_name
                ? account.reseller_name
                : '<span style="color: #999; font-style: italic;">Not Assigned</span>';

            // Status toggle (default to active=1 if not set)
            const accountStatus = account.status !== undefined ? account.status : 1;
            const isActive = accountStatus == 1;
            const statusToggle = isObserver
                ? `<label class="status-toggle" style="opacity: 0.5; cursor: not-allowed;">
                    <input type="checkbox" ${isActive ? 'checked' : ''} disabled>
                    <span class="toggle-slider"></span>
                   </label>`
                : `<label class="status-toggle" onclick="toggleAccountStatus('${account.username}', ${isActive ? 0 : 1})">
                    <input type="checkbox" ${isActive ? 'checked' : ''}>
                    <span class="toggle-slider"></span>
                   </label>`;

            // iOS PWA: Show reseller name below full name ONLY in PWA mode (v1.10.1)
            // Check if running in PWA mode
            const isPWAMode = document.body.classList.contains('pwa-mode');

            let fullNameDisplay;
            if (isPWAMode) {
                // PWA mode: Show reseller name below customer name
                fullNameDisplay = `
                    <div class="full-name-cell">
                        <div class="name-primary">${account.full_name || ''}</div>
                        <div class="name-secondary">${account.reseller_name || '<span style="font-style: italic;">Unassigned</span>'}</div>
                    </div>
                `;
            } else {
                // Standard browser: Show only customer name
                fullNameDisplay = account.full_name || '';
            }

            tr.innerHTML = `
                <td>${account.username || ''}</td>
                <td>${fullNameDisplay}</td>
                <td>${account.phone_number || ''}</td>
                <td>${account.mac || ''}</td>
                <td>${account.tariff_plan || ''}</td>
                <td>${resellerDisplay}</td>
                <td>${statusToggle}</td>
                <td>${expirationCell}</td>
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

    // Update sort indicators
    updateSortIndicators();
}

// Sort accounts by column
function sortAccounts(column) {
    const { sortColumn, sortDirection } = accountsPagination;

    // Toggle direction if clicking same column, otherwise default to asc
    if (sortColumn === column) {
        accountsPagination.sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        accountsPagination.sortColumn = column;
        accountsPagination.sortDirection = 'asc';
    }

    // Sort both allAccounts and filteredAccounts
    const compareFunction = getCompareFunction(column, accountsPagination.sortDirection);
    accountsPagination.allAccounts.sort(compareFunction);
    if (accountsPagination.searchTerm) {
        accountsPagination.filteredAccounts.sort(compareFunction);
    }

    // Reset to first page and re-render
    accountsPagination.currentPage = 1;
    renderAccountsPage();

    // Show reset button
    const resetBtn = document.getElementById('reset-sort-btn');
    if(resetBtn) {
        resetBtn.style.display = 'inline-flex';
    }
}

// Get comparison function for sorting
function getCompareFunction(column, direction) {
    const multiplier = direction === 'asc' ? 1 : -1;

    return (a, b) => {
        let aVal = a[column];
        let bVal = b[column];

        // Handle null/undefined values
        if (aVal === null || aVal === undefined) aVal = '';
        if (bVal === null || bVal === undefined) bVal = '';

        // Special handling for dates
        if (column === 'end_date') {
            if (!aVal) return 1 * multiplier; // Empty dates go to end
            if (!bVal) return -1 * multiplier;
            return (new Date(aVal) - new Date(bVal)) * multiplier;
        }

        // Special handling for status (numeric)
        if (column === 'status') {
            return (Number(aVal) - Number(bVal)) * multiplier;
        }

        // String comparison (case-insensitive)
        aVal = String(aVal).toLowerCase();
        bVal = String(bVal).toLowerCase();

        if (aVal < bVal) return -1 * multiplier;
        if (aVal > bVal) return 1 * multiplier;
        return 0;
    };
}

// Update visual sort indicators
function updateSortIndicators() {
    const { sortColumn, sortDirection } = accountsPagination;

    // Remove all sort classes
    document.querySelectorAll('#accounts-table th.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });

    // Add sort class to active column
    if (sortColumn) {
        const activeHeader = document.querySelector(`#accounts-table th[data-sort="${sortColumn}"]`);
        if (activeHeader) {
            activeHeader.classList.add(`sort-${sortDirection}`);
        }
    }
}

// Initialize sorting on page load
function initializeAccountsSorting() {
    document.querySelectorAll('#accounts-table th.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortAccounts(column);
        });
    });
}

// Reset sorting to default (original order)
function resetSorting() {
    // Clear sort state
    accountsPagination.sortColumn = null;
    accountsPagination.sortDirection = 'asc';

    // Reload accounts from server to get original order
    loadAccounts();

    // Hide reset button
    const resetBtn = document.getElementById('reset-sort-btn');
    if(resetBtn) {
        resetBtn.style.display = 'none';
    }
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
        const response = await fetch('api/get_resellers.php');
        const result = await response.json();

        const tbody = document.getElementById('resellers-tbody');

        if(result.error == 0 && result.resellers && result.resellers.length > 0) {
            tbody.innerHTML = '';
            document.getElementById('total-resellers').textContent = result.resellers.length;

            result.resellers.forEach(reseller => {
                const tr = document.createElement('tr');
                const resellerBalance = reseller.balance || 0;
                const resellerCurrency = reseller.currency_name || 'IRR';

                // Check if this reseller is an observer
                const isResellerObserver = reseller.is_observer == 1;

                // Check if current user is observer
                const isObserver = currentUser && currentUser.is_observer == 1;
                const isSuperAdmin = currentUser && currentUser.super_user == 1;
                const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');

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

                // Show delete button for super admin and reseller admin
                // Reseller admin cannot delete themselves (checked in backend)
                const canDelete = isSuperAdmin || isResellerAdmin;
                const deleteButton = canDelete
                    ? `<button class="btn-sm btn-delete" onclick="deleteReseller(${reseller.id})">Delete</button>`
                    : '';

                // For observers, show "-" for currency and balance
                const displayCurrency = isResellerObserver ? '-' : resellerCurrency;
                const displayBalance = isResellerObserver ? '-' : formatBalance(reseller.balance || 0, reseller.currency_name);

                tr.innerHTML = `
                    <td>${reseller.name || ''}</td>
                    <td>${reseller.username || ''}</td>
                    <td class="hide-in-pwa">${reseller.email || ''}</td>
                    <td>${displayCurrency}</td>
                    <td style="text-align: left;">${displayBalance}</td>
                    <td class="hide-in-pwa">${reseller.account_count || 0}</td>
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
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">No resellers found</td></tr>';
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
        const url = `api/get_plans.php?viewAllAccounts=${viewAllAccounts}`;
        console.log('[loadPlans] Fetching plans with URL:', url);

        const response = await fetch(url);
        const result = await response.json();
        console.log('[loadPlans] Received plans:', result);

        const tbody = document.getElementById('plans-tbody');
        const planSelect = document.getElementById('add-plan-select');
        const resellerPlansCheckboxes = document.getElementById('reseller-plans-checkboxes');
        const assignPlansSelect = document.getElementById('assign-plans-select');

        if(result.error == 0 && result.plans && result.plans.length > 0) {
            console.log('[loadPlans] Loading', result.plans.length, 'plans into dropdowns');
            tbody.innerHTML = '';
            planSelect.innerHTML = '<option value="0">No Plan</option>';
            if(resellerPlansCheckboxes) resellerPlansCheckboxes.innerHTML = '';
            document.getElementById('total-plans').textContent = result.plans.length;

            // Store plans globally for use in assign modal
            availablePlans = result.plans;

            result.plans.forEach(plan => {
                const tr = document.createElement('tr');
                // Normalize currency display (IRT -> IRR)
                const displayCurrency = (plan.currency_id === 'IRT') ? 'IRR' : plan.currency_id;
                const formattedPrice = formatBalance(plan.price, plan.currency_id);
                const formattedPriceWithSymbol = getCurrencySymbol(plan.currency_id) + formattedPrice;

                // Format category display
                const categoryLabels = {
                    'new_device': 'New Device',
                    'application': 'Application',
                    'renew_device': 'Renew Device'
                };
                const categoryDisplay = plan.category ? (categoryLabels[plan.category] || plan.category) : '-';

                // Check if user is observer or regular reseller (they can't edit or delete)
                const isObserver = currentUser && currentUser.is_observer == 1;
                const isSuperAdmin = currentUser && currentUser.super_user == 1;
                const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
                const isRegularReseller = currentUser && !isSuperAdmin && !isResellerAdmin && !isObserver;

                // Hide buttons for observers and regular resellers
                const shouldHideButtons = isObserver || isRegularReseller;
                const editButton = shouldHideButtons
                    ? '' // Hide button completely
                    : `<button class="btn-sm btn-edit" onclick="editPlan(${plan.id})">Edit</button>`;
                const deleteButton = shouldHideButtons
                    ? '' // Hide button completely
                    : `<button class="btn-sm btn-delete" onclick="deletePlan('${plan.external_id}', '${plan.currency_id}')">Delete</button>`;

                // Hide entire Actions column for regular resellers
                const actionsColumn = shouldHideButtons
                    ? '' // Hide entire column cell
                    : `<td>
                        <div class="action-buttons">
                            ${editButton}
                            ${deleteButton}
                        </div>
                    </td>`;

                tr.innerHTML = `
                    <td>${plan.external_id || ''}</td>
                    <td>${plan.name || ''}</td>
                    <td>${categoryDisplay}</td>
                    <td>${displayCurrency || ''}</td>
                    <td>${formattedPrice}</td>
                    <td>${plan.days || 0}</td>
                    ${actionsColumn}
                `;
                tbody.appendChild(tr);

                // Add to plan select for account creation
                // Use planID-currency format to ensure correct plan is selected
                // Add ALL plans to dropdown (admins see all plans)
                const option = document.createElement('option');
                option.value = `${plan.external_id}-${plan.currency_id}`;
                option.textContent = `${plan.name || plan.external_id} - ${formattedPriceWithSymbol} (${plan.days} days)`;
                planSelect.appendChild(option);

                // Add to reseller plan assignment checkboxes with planID-currency format
                if(resellerPlansCheckboxes) {
                    // Normalize IRT to IRR for filtering
                    const filterCurrency = (plan.currency_id === 'IRT') ? 'IRR' : plan.currency_id;
                    const checkboxItem = document.createElement('div');
                    checkboxItem.className = 'plan-checkbox-item';
                    checkboxItem.dataset.currency = filterCurrency;
                    checkboxItem.innerHTML = `
                        <label class="plan-checkbox-label">
                            <input type="checkbox" name="reseller_plans" value="${plan.external_id}-${plan.currency_id}">
                            <div class="plan-info">
                                <span class="plan-name">${plan.name || plan.external_id}</span>
                                <span class="plan-details">${formattedPriceWithSymbol} (${plan.days} days)</span>
                            </div>
                        </label>
                    `;
                    resellerPlansCheckboxes.appendChild(checkboxItem);
                }
            });
        } else {
            console.log('[loadPlans] No plans to display. Error:', result.error, 'Plans:', result.plans);
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">No plans found</td></tr>';
            planSelect.innerHTML = '<option value="0">No Plan</option>';
            document.getElementById('total-plans').textContent = '0';
        }
    } catch(error) {
        console.error('[loadPlans] Exception caught:', error);
        console.error('[loadPlans] Error stack:', error.stack);
        showAlert('Error loading plans: ' + error.message, 'error');
    }
}

// Global variables to store tariffs and plans
let availableTariffs = [];
let availablePlans = [];

// Load Tariffs from Stalker Portal Server (auto-fetch on login)
async function loadTariffs() {
    try {
        const response = await fetch('api/get_tariffs.php');
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
        const response = await fetch('api/get_themes.php');
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
        // Get view mode preference for reseller admins (affects transaction filtering)
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const response = await fetch(`api/get_transactions.php?viewAllAccounts=${viewAllAccounts}`);
        const result = await response.json();

        if(result.error == 0 && result.transactions) {
            // Store all transactions for pagination
            transactionsPagination.allTransactions = result.transactions;
            transactionsPagination.totalItems = result.transactions.length;

            // Sort transactions by default (newest first)
            sortTransactionsData();

            // Render first page
            renderTransactionsPage();
        } else {
            transactionsPagination.allTransactions = [];
            transactionsPagination.totalItems = 0;
            renderTransactionsPage();
        }
    } catch(error) {
        console.error('Error loading transactions:', error);
        showAlert('Error loading transactions', 'error');
    }
}

// Sort transactions data
function sortTransactionsData() {
    const { sortColumn, sortDirection, allTransactions } = transactionsPagination;

    allTransactions.sort((a, b) => {
        let valueA, valueB;

        if (sortColumn === 'timestamp') {
            valueA = parseInt(a.timestamp) || 0;
            valueB = parseInt(b.timestamp) || 0;
        } else {
            valueA = a[sortColumn] || '';
            valueB = b[sortColumn] || '';
        }

        if (sortDirection === 'asc') {
            return valueA > valueB ? 1 : valueA < valueB ? -1 : 0;
        } else {
            return valueA < valueB ? 1 : valueA > valueB ? -1 : 0;
        }
    });
}

// Sort transactions by column
function sortTransactions(column) {
    // Toggle direction if clicking same column
    if (transactionsPagination.sortColumn === column) {
        transactionsPagination.sortDirection = transactionsPagination.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        transactionsPagination.sortColumn = column;
        transactionsPagination.sortDirection = 'desc'; // Default to newest first for date
    }

    // Update sort icon in header
    updateTransactionsSortIcon();

    // Sort data and re-render
    sortTransactionsData();
    transactionsPagination.currentPage = 1; // Reset to first page
    renderTransactionsPage();
}

// Update sort icon in transactions table header
function updateTransactionsSortIcon() {
    const table = document.getElementById('transactions-table');
    const headers = table.querySelectorAll('th.sortable');

    headers.forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === transactionsPagination.sortColumn) {
            th.classList.add(transactionsPagination.sortDirection === 'asc' ? 'sort-asc' : 'sort-desc');
        }
    });
}

// Render current transactions page
function renderTransactionsPage() {
    const tbody = document.getElementById('transactions-tbody');
    const { currentPage, perPage, allTransactions, totalItems } = transactionsPagination;

    // Check if current user is admin, reseller admin in All Accounts mode, or observer (to show reseller column)
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const isSuperAdmin = currentUser && currentUser.super_user == 1;
    const isObserver = currentUser && currentUser.is_observer == 1;
    const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
    const showResellerColumn = isSuperAdmin || isObserver || (isResellerAdmin && viewAllAccounts);

    // Show/hide reseller column header based on user type
    const resellerHeader = document.getElementById('reseller-column-header');
    if(resellerHeader) {
        resellerHeader.style.display = showResellerColumn ? '' : 'none';
    }

    if(totalItems > 0) {
        tbody.innerHTML = '';

        // Calculate pagination
        const startIndex = (currentPage - 1) * perPage;
        const endIndex = Math.min(startIndex + perPage, totalItems);
        const pageTransactions = allTransactions.slice(startIndex, endIndex);

        pageTransactions.forEach(tx => {
            const tr = document.createElement('tr');
            const currencySymbol = getCurrencySymbol(tx.currency);
            const formattedAmount = formatBalance(tx.amount, tx.currency);

            // Determine transaction type from details
            let details = tx.details || '';
            const detailsLower = details.toLowerCase();
            let type = '';
            let typeClass = '';

            if (detailsLower.includes('account renewal') || detailsLower.includes('renew')) {
                type = 'Renewal';
                typeClass = 'active';
            } else if (detailsLower.includes('plan ') && detailsLower.includes('assigned')) {
                type = 'New Account';
                typeClass = 'inactive';
            } else if (detailsLower.includes('credit adjustment')) {
                type = 'Credit';
                typeClass = 'active';
            } else {
                type = tx.type == 1 ? 'Credit' : 'Debit';
                typeClass = tx.type == 1 ? 'active' : 'inactive';
            }

            // Use MAC address from API response
            const macAddress = tx.mac_address || '-';

            // Clean up details - remove MAC address if present
            const macMatch = details.match(/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/);
            if (macMatch) {
                details = details.replace(macMatch[1], '').replace(/\s*,\s*,/g, ',').replace(/,\s*$/, '').replace(/\s+/g, ' ').trim();
            }

            // Build reseller column if needed
            const resellerColumn = showResellerColumn
                ? `<td>${tx.reseller_name || tx.reseller_username || 'N/A'}</td>`
                : '';

            tr.innerHTML = `
                <td>${new Date(tx.timestamp * 1000).toLocaleDateString()}</td>
                <td>${formattedAmount}</td>
                <td>${tx.currency || currencySymbol.trim() || ''}</td>
                <td><span class="badge ${typeClass}" style="font-size: 10px; padding: 4px 8px;">${type}</span></td>
                <td><code style="font-size: 13px;">${macAddress}</code></td>
                ${resellerColumn}
                <td>${details}</td>
            `;
            tbody.appendChild(tr);
        });

        // Update pagination info
        document.getElementById('transactions-pagination-info').textContent =
            `Showing ${startIndex + 1}-${endIndex} of ${totalItems} transactions`;

        // Render pagination buttons
        renderTransactionsPagination();
    } else {
        const colspan = showResellerColumn ? '7' : '6';
        tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center;padding:40px;color:#999">No transactions found</td></tr>`;
        document.getElementById('transactions-pagination-info').textContent = '';
        document.getElementById('transactions-pagination').innerHTML = '';
    }

    // Update sort icon on initial load
    updateTransactionsSortIcon();
}

// Render transactions pagination buttons
function renderTransactionsPagination() {
    const { currentPage, perPage, totalItems } = transactionsPagination;
    const totalPages = Math.ceil(totalItems / perPage);
    const paginationDiv = document.getElementById('transactions-pagination');

    if (totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }

    let buttonsHTML = '';

    // Previous button
    buttonsHTML += `<button onclick="goToTransactionsPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>« Prev</button>`;

    // Page numbers with ellipsis
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    if (startPage > 1) {
        buttonsHTML += `<button class="page-btn" onclick="goToTransactionsPage(1)">1</button>`;
        if (startPage > 2) {
            buttonsHTML += `<span class="ellipsis">...</span>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        buttonsHTML += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToTransactionsPage(${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            buttonsHTML += `<span class="ellipsis">...</span>`;
        }
        buttonsHTML += `<button class="page-btn" onclick="goToTransactionsPage(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    buttonsHTML += `<button onclick="goToTransactionsPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next »</button>`;

    paginationDiv.innerHTML = buttonsHTML;
}

// Go to specific transactions page
function goToTransactionsPage(page) {
    const totalPages = Math.ceil(transactionsPagination.totalItems / transactionsPagination.perPage);
    if (page >= 1 && page <= totalPages) {
        transactionsPagination.currentPage = page;
        renderTransactionsPage();

        // Scroll to top of transactions table
        document.getElementById('transactions-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Change transactions per page
function changeTransactionsPerPage() {
    const select = document.getElementById('transactions-per-page');
    transactionsPagination.perPage = parseInt(select.value);
    transactionsPagination.currentPage = 1; // Reset to first page
    renderTransactionsPage();
}

// Add Account
async function addAccount(e) {
    e.preventDefault();

    // Validate MAC address before submission
    const macInput = e.target.querySelector('input[name="mac"]');
    if (!validateMacInput(macInput)) {
        return;
    }

    // Validate and format phone number
    const countryCodeSelect = document.getElementById('add-country-code').value;
    const customCode = document.getElementById('add-custom-code').value;
    const phoneNumber = document.getElementById('add-phone-number').value;

    if (phoneNumber) {
        const countryCode = countryCodeSelect === 'custom' ? customCode : countryCodeSelect;

        // Validate country code for custom option
        if (countryCodeSelect === 'custom' && !customCode) {
            showAlert('Please enter a custom country code', 'error');
            return;
        }

        // Validate phone number
        const validation = validatePhoneNumber(phoneNumber, countryCode);
        if (!validation.valid) {
            showAlert(validation.error, 'error');
            return;
        }
    }

    // Check if user is reseller without admin permission
    const isSuperUser = currentUser ? (currentUser.super_user == 1 || currentUser.super_user === '1') : true;
    const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
    const isResellerWithoutAdmin = !isSuperUser && !isResellerAdmin;

    // Check if reseller admin is in "My Accounts" mode (uses cards instead of dropdown)
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

    // Determine if we should use card selection (reseller path) or dropdown (admin path)
    // MUST check !isSuperUser - super admin always uses dropdown, never cards (v1.11.44)
    const useCardSelection = !isSuperUser && (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode);

    console.log('[submitAddAccount] User type check:', { isSuperUser, isResellerAdmin, isResellerWithoutAdmin, viewAllAccounts, isResellerAdminInMyAccountsMode, useCardSelection });

    // Handle plan selection for resellers and reseller admins in My Accounts mode
    if (useCardSelection) {
        // Get selected plan from cards
        const selectedCard = document.querySelector('#add-new-device-plans-container .renewal-plan-card.selected');

        if (!selectedCard) {
            showAlert('Please select a plan for the new device', 'error');
            return;
        }

        const selectedPlan = selectedCard.dataset.planId;

        console.log('[submitAddAccount] Selected card:', selectedCard);
        console.log('[submitAddAccount] Selected plan from card:', selectedPlan);

        // Create formData
        const formData = new FormData(e.target);

        console.log('[submitAddAccount] Plan value BEFORE set:', formData.get('plan'));

        // Add the selected plan from cards
        formData.set('plan', selectedPlan);

        console.log('[submitAddAccount] Plan value AFTER set:', formData.get('plan'));

        // Force status to active (1) for new accounts
        formData.set('status', '1');

        // Replace phone_number with full formatted number
        if (phoneNumber) {
            const fullPhone = getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber);
            formData.set('phone_number', fullPhone);
        }

        // Remove country_code from form data (it's already included in phone_number)
        formData.delete('country_code');

        // Submit the form
        await submitAddAccountForm(formData);
    } else {
        // Admin user - create formData normally
        const formData = new FormData(e.target);

        // Replace phone_number with full formatted number
        if (phoneNumber) {
            const fullPhone = getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber);
            formData.set('phone_number', fullPhone);
        }

        // Remove country_code from form data (it's already included in phone_number)
        formData.delete('country_code');

        // Submit the form
        await submitAddAccountForm(formData);
    }
}

// Helper function to submit add account form
async function submitAddAccountForm(formData) {

    console.log('[submitAddAccountForm] Final formData entries:', Object.fromEntries(formData));
    console.log('[submitAddAccountForm] Plan value being sent:', formData.get('plan'));

    try {
        const response = await fetch('api/add_account.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            console.log('[submitAddAccountForm] SUCCESS - Debug info:', result.debug);
            showAlert('Account created successfully!', 'success');
            closeModal('addAccountModal');
            document.getElementById('addAccountForm').reset();
            loadAccounts();
            checkAuth(); // Refresh stats
        } else {
            console.log('[submitAddAccountForm] ERROR - Response:', result);
            showAlert(result.err_msg || 'Error creating account', 'error');
        }
    } catch(error) {
        showAlert('Error creating account: ' + error.message, 'error');
    }
}

// Filter plans by currency in Add Reseller modal
function filterPlansByCurrency() {
    const currencySelect = document.getElementById('add-reseller-currency');
    const selectedCurrency = currencySelect ? currencySelect.value : null;
    const planItems = document.querySelectorAll('#reseller-plans-checkboxes .plan-checkbox-item');

    let visibleCount = 0;
    planItems.forEach(item => {
        const planCurrency = item.dataset.currency;
        if (!selectedCurrency || planCurrency === selectedCurrency) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
            // Uncheck hidden plans
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        }
    });

    // Show message if no plans match
    const container = document.getElementById('reseller-plans-checkboxes');
    let noPlansMsg = container.querySelector('.no-plans-message');
    if (visibleCount === 0) {
        if (!noPlansMsg) {
            noPlansMsg = document.createElement('div');
            noPlansMsg.className = 'no-plans-message';
            noPlansMsg.style.cssText = 'padding: 20px; text-align: center; color: var(--text-secondary);';
            noPlansMsg.textContent = 'No plans available for this currency';
            container.appendChild(noPlansMsg);
        }
    } else if (noPlansMsg) {
        noPlansMsg.remove();
    }
}

// Add Reseller
async function addReseller(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    // Get selected plans from checkboxes
    const selectedCheckboxes = document.querySelectorAll('#reseller-plans-checkboxes input[type="checkbox"]:checked');
    const selectedPlans = Array.from(selectedCheckboxes).map(cb => cb.value);
    const plansString = selectedPlans.join(',');

    formData.set('plans', plansString);
    formData.append('use_ip_ranges', '');

    // Handle permissions checkboxes
    const canEditAccounts = formData.get('can_edit_accounts') === '1' ? '1' : '0';
    const canAddAccounts = formData.get('can_add_accounts') === '1' ? '1' : '0';
    const canDeleteAccounts = formData.get('can_delete_accounts') === '1' ? '1' : '0';
    const canControlStb = formData.get('can_control_stb') === '1' ? '1' : '0';
    const canToggleStatus = formData.get('can_toggle_status') === '1' ? '1' : '0';
    const canAccessMessaging = formData.get('can_access_messaging') === '1' ? '1' : '0';
    const canEditPhoneName = formData.get('can_edit_phone_name') === '1' ? '1' : '0';
    const isAdmin = formData.get('is_admin') === '1' ? '1' : '0';
    const isObserver = formData.get('is_observer') === '1' ? '1' : '0';

    // If admin is checked, grant all permissions including STB control, status toggle, messaging access, and phone/name editing
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging|can_edit_phone_name
    const finalCanControlStb = isAdmin === '1' ? '1' : canControlStb;
    const finalCanToggleStatus = isAdmin === '1' ? '1' : canToggleStatus;
    const finalCanAccessMessaging = isAdmin === '1' ? '1' : canAccessMessaging;
    const finalCanEditPhoneName = isAdmin === '1' ? '1' : canEditPhoneName;
    const permissions = `${canEditAccounts}|${canAddAccounts}|${isAdmin}|${canDeleteAccounts}|${finalCanControlStb}|${finalCanToggleStatus}|${finalCanAccessMessaging}|${finalCanEditPhoneName}`;

    formData.delete('is_admin'); // Remove is_admin as it's now part of permissions
    formData.set('permissions', permissions);
    formData.set('is_observer', isObserver); // Add is_observer to form data

    try {
        const response = await fetch('api/add_reseller.php', {
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
        const response = await fetch('api/add_plan.php?' + params, {
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

// Edit Plan - Open modal with plan data
async function editPlan(planId) {
    try {
        console.log('[editPlan] Called with planId:', planId);
        console.log('[editPlan] availablePlans:', availablePlans);

        // Find the plan in the availablePlans array
        const plan = availablePlans.find(p => p.id == planId);

        if(!plan) {
            console.error('[editPlan] Plan not found. planId:', planId, 'availablePlans:', availablePlans);
            showAlert('Plan not found. Please refresh the page.', 'error');
            return;
        }

        // Populate the edit form
        document.getElementById('edit-plan-id').value = plan.id;
        document.getElementById('edit-plan-external-id').value = plan.external_id;
        document.getElementById('edit-plan-currency').value = plan.currency_id;
        document.getElementById('edit-plan-id-display').value = plan.external_id;
        document.getElementById('edit-plan-name').value = plan.name;

        // Display currency (normalize IRT -> IRR)
        const displayCurrency = (plan.currency_id === 'IRT') ? 'IRR' : plan.currency_id;
        document.getElementById('edit-plan-currency-display').value = displayCurrency;

        // Format price with thousand separators
        const priceNum = parseFloat(plan.price) || 0;
        document.getElementById('edit-plan-price').value = priceNum.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        document.getElementById('edit-plan-days').value = plan.days;
        document.getElementById('edit-plan-category').value = plan.category || '';

        // Open the edit modal
        openModal('editPlanModal');

    } catch(error) {
        console.error('Error loading plan for edit:', error);
        showAlert('Error loading plan data', 'error');
    }
}

// Submit Edit Plan
async function submitEditPlan(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    // Strip commas from price before submitting
    const priceValue = formData.get('price');
    if (priceValue) {
        formData.set('price', priceValue.replace(/,/g, ''));
    }
    const params = new URLSearchParams(formData).toString();

    try {
        const response = await fetch('api/edit_plan.php?' + params, {
            method: 'GET'
        });

        const result = await response.json();

        if(result.error == 0) {
            showAlert(result.message || 'Plan updated successfully!', 'success');
            closeModal('editPlanModal');
            loadPlans();
        } else {
            showAlert(result.err_msg || 'Error updating plan', 'error');
        }
    } catch(error) {
        showAlert('Error updating plan: ' + error.message, 'error');
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
        const response = await fetch('api/remove_account.php', {
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

// Toggle Account Status
async function toggleAccountStatus(username, newStatus) {
    try {
        const response = await fetch(`api/toggle_account_status.php?username=${encodeURIComponent(username)}&status=${newStatus}`);
        const result = await response.json();

        if(result.error == 0) {
            // Use the message from the server (which contains the full name)
            showAlert(result.message || 'Status updated successfully', 'success');
            // Reload accounts to reflect the change
            loadAccounts();
        } else {
            showAlert(result.err_msg || 'Error toggling account status', 'error');
            // Reload accounts to reset toggle to previous state
            loadAccounts();
        }
    } catch(error) {
        showAlert('Error toggling account status: ' + error.message, 'error');
        // Reload accounts to reset toggle to previous state
        loadAccounts();
    }
}

// Assign Reseller
async function assignResellerCore(username, currentResellerId) {
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
        const response = await fetch('api/get_resellers.php');
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
        const response = await fetch('api/assign_reseller.php', {
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

// Debounced wrapper for assignReseller (200ms cooldown for async function)
const assignReseller = debounce(assignResellerCore, 'assignReseller', 200);

// Delete Plan
async function deletePlan(planId, currency) {
    if(!confirm('Are you sure you want to delete this plan?')) {
        return;
    }

    try {
        const response = await fetch(`api/remove_plan.php?plan=${planId}&currency=${currency}`, {
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

        const response = await fetch('api/get_resellers.php');
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

                // Parse permissions (format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging|can_edit_phone_name)
                const permissions = (reseller.permissions || '0|0|0|0|0|0|0|0').split('|');
                document.getElementById('edit-can-edit-accounts').checked = permissions[0] === '1';
                document.getElementById('edit-can-add-accounts').checked = permissions[1] === '1';
                document.getElementById('edit-is-admin').checked = permissions[2] === '1';
                document.getElementById('edit-can-delete-accounts').checked = permissions[3] === '1';
                document.getElementById('edit-can-control-stb').checked = permissions[4] === '1';
                document.getElementById('edit-can-toggle-status').checked = permissions[5] === '1';
                document.getElementById('edit-can-access-messaging').checked = permissions[6] === '1';
                document.getElementById('edit-can-edit-phone-name').checked = permissions[7] === '1';

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
    const canControlStb = formData.get('can_control_stb') === '1' ? '1' : '0';
    const canToggleStatus = formData.get('can_toggle_status') === '1' ? '1' : '0';
    const canAccessMessaging = formData.get('can_access_messaging') === '1' ? '1' : '0';
    const canEditPhoneName = formData.get('can_edit_phone_name') === '1' ? '1' : '0';
    const isAdmin = formData.get('is_admin') === '1' ? '1' : '0';
    const isObserver = formData.get('is_observer') === '1' ? '1' : '0';

    // If admin is checked, grant all permissions including STB control, status toggle, messaging access, and phone/name editing
    // Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging|can_edit_phone_name
    const finalCanControlStb = isAdmin === '1' ? '1' : canControlStb;
    const finalCanToggleStatus = isAdmin === '1' ? '1' : canToggleStatus;
    const finalCanAccessMessaging = isAdmin === '1' ? '1' : canAccessMessaging;
    const finalCanEditPhoneName = isAdmin === '1' ? '1' : canEditPhoneName;
    const permissions = `${canEditAccounts}|${canAddAccounts}|${isAdmin}|${canDeleteAccounts}|${finalCanControlStb}|${finalCanToggleStatus}|${finalCanAccessMessaging}|${finalCanEditPhoneName}`;

    formData.delete('is_admin'); // Remove is_admin as it's now part of permissions
    formData.set('permissions', permissions);
    formData.set('is_observer', isObserver); // Add is_observer to form data
    formData.append('use_ip_ranges', '');
    // Don't send plans field - let backend preserve existing plans
    // Plans are managed separately via "Assign Plans" button

    try {
        const response = await fetch('api/update_reseller.php', {
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
        const response = await fetch(`api/remove_reseller.php?id=${resellerId}`, {
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
async function editAccountCore(username) {
    try {
        // Safety check: Prevent clicking while accounts are still loading
        if(isAccountsLoading) {
            showAlert('Please wait for accounts to finish loading', 'warning');
            return;
        }

        // Safety check: Ensure accounts are loaded
        if(!accountsPagination || !accountsPagination.allAccounts || accountsPagination.allAccounts.length === 0) {
            showAlert('No accounts loaded. Please refresh the page.', 'warning');
            return;
        }

        // Find account data from the loaded accounts
        const account = accountsPagination.allAccounts.find(acc => acc.username === username);

        if(!account) {
            showAlert('Account not found. Please refresh the page.', 'error');
            return;
        }

        // Check if user is reseller without admin permission
        // IMPORTANT: Super admin (super_user == 1) ALWAYS sees dropdown, never cards
        const isSuperUser = currentUser ? (currentUser.super_user == 1 || currentUser.super_user === '1') : false;
        const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
        const isResellerWithoutAdmin = !isSuperUser && !isResellerAdmin;

        // Populate form fields
        document.getElementById('edit-original-username').value = account.username;
        document.getElementById('edit-username').value = account.username;
        document.getElementById('edit-password').value = ''; // Keep blank
        document.getElementById('edit-name').value = account.full_name || '';
        document.getElementById('edit-email').value = account.email || '';

        // Parse and populate phone number
        const parsedPhone = parsePhoneNumber(account.phone_number || '');
        document.getElementById('edit-country-code').value = parsedPhone.countryCode;
        document.getElementById('edit-phone').value = parsedPhone.phoneNumber;

        document.getElementById('edit-comment').value = account.comment || '';

        // Check if reseller has permission to edit phone, email, and name
        // Parse permissions (format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging|can_edit_phone_name)
        const permissions = (currentUser && currentUser.permissions || '0|0|0|0|0|0|0|0').split('|');
        const canEditPhoneName = permissions[7] === '1';

        // If user is a reseller and doesn't have phone/name/email edit permission, make fields read-only
        if (isResellerWithoutAdmin && !canEditPhoneName) {
            document.getElementById('edit-name').readOnly = true;
            document.getElementById('edit-email').readOnly = true;
            document.getElementById('edit-country-code').disabled = true;
            document.getElementById('edit-custom-code').readOnly = true;
            document.getElementById('edit-phone').readOnly = true;
        } else {
            // Enable fields for users with permission
            document.getElementById('edit-name').readOnly = false;
            document.getElementById('edit-email').readOnly = false;
            document.getElementById('edit-country-code').disabled = false;
            document.getElementById('edit-custom-code').readOnly = false;
            document.getElementById('edit-phone').readOnly = false;
        }

        // Check view mode for reseller admins (only applies to reseller admins, NOT super admins)
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

        // Super admin ALWAYS uses dropdown - never show cards for super admin
        const shouldShowCards = !isSuperUser && (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode);

        // Debug logging
        console.log('[Edit Account Modal] User detection:', {
            currentUser: currentUser,
            super_user_raw: currentUser?.super_user,
            isSuperUser: isSuperUser,
            isResellerAdmin: isResellerAdmin,
            isResellerWithoutAdmin: isResellerWithoutAdmin,
            viewAllAccounts: viewAllAccounts,
            isResellerAdminInMyAccountsMode: isResellerAdminInMyAccountsMode,
            shouldShowCards: shouldShowCards,
            willShowDropdown: !shouldShowCards
        });

        // Handle reseller without admin permission OR reseller admin in "My Accounts" mode
        if (shouldShowCards) {
            // Make username and password fields read-only for regular resellers only
            if (isResellerWithoutAdmin) {
                document.getElementById('edit-username').readOnly = true;
                document.getElementById('edit-password').readOnly = true;
            } else {
                // Reseller admins can edit username/password
                document.getElementById('edit-username').readOnly = false;
                document.getElementById('edit-password').readOnly = false;
            }

            // Hide admin-only fields (Plan and Status)
            document.getElementById('edit-plan-group').style.display = 'none';
            document.getElementById('edit-status-group').style.display = 'none';

            // Show renewal section for resellers
            document.getElementById('reseller-renewal-section').style.display = 'block';

            // Load renewal plans
            await loadRenewalPlans();
        } else {
            // Enable all fields for admin users
            document.getElementById('edit-username').readOnly = false;
            document.getElementById('edit-password').readOnly = false;

            // Show admin-only fields
            document.getElementById('edit-plan-group').style.display = 'block';
            document.getElementById('edit-status-group').style.display = 'block';

            // Hide renewal section
            document.getElementById('reseller-renewal-section').style.display = 'none';

            // Set status (default to 1 if not set)
            const statusSelect = document.getElementById('edit-status');
            statusSelect.value = account.status !== undefined ? account.status : '1';

            // Load plans into dropdown for admin
            await loadPlansForEdit();
        }

        // Open modal
        openModal('editAccountModal');

    } catch(error) {
        console.error('Error loading account for edit:', error);
        showAlert('Error loading account data', 'error');
    }
}

async function loadPlansForEdit() {
    try {
        console.log('[loadPlansForEdit] Starting to load plans...');
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const response = await fetch(`api/get_plans.php?viewAllAccounts=${viewAllAccounts}`);
        const result = await response.json();

        console.log('[loadPlansForEdit] API response:', result);

        const planSelect = document.getElementById('edit-plan');

        // Clear existing options except the first one
        planSelect.innerHTML = '<option value="0">SELECT ONE TO UPDATE</option>';

        if(result.error == 0 && result.plans) {
            console.log('[loadPlansForEdit] Loading ' + result.plans.length + ' plans');
            // Show ALL plans for admins (no filtering by category)
            result.plans.forEach(plan => {
                const option = document.createElement('option');
                option.value = plan.id;
                const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);
                option.textContent = `${plan.name} - ${formattedPrice} (${plan.days} days)`;
                planSelect.appendChild(option);
            });
            console.log('[loadPlansForEdit] Dropdown now has ' + planSelect.options.length + ' options');
        } else {
            console.error('[loadPlansForEdit] No plans returned or error:', result);
        }
    } catch(error) {
        console.error('[loadPlansForEdit] Error loading plans:', error);
    }
}

// Load renewal plans for reseller (as beautiful cards with checkboxes)
async function loadRenewalPlans() {
    try {
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const response = await fetch(`api/get_plans.php?viewAllAccounts=${viewAllAccounts}`);
        const result = await response.json();

        const container = document.getElementById('renewal-plans-container');
        container.innerHTML = ''; // Clear existing

        if(result.error == 0 && result.plans) {
            // Filter to only show renew_device plans for renewal cards
            const renewalPlans = result.plans.filter(plan => plan.category === 'renew_device');

            if(renewalPlans.length > 0) {
                renewalPlans.forEach(plan => {
                    const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);

                    const card = document.createElement('div');
                    card.className = 'renewal-plan-card';
                    card.dataset.planId = plan.id;
                    card.innerHTML = `
                        <div class="renewal-plan-checkbox"></div>
                        <div class="renewal-plan-name">${plan.name}</div>
                        <div class="renewal-plan-duration">${plan.days} days</div>
                        <div class="renewal-plan-price">${formattedPrice}</div>
                    `;

                    // Click handler to select plan
                    card.addEventListener('click', function() {
                        // Deselect all other cards
                        document.querySelectorAll('.renewal-plan-card').forEach(c => {
                            c.classList.remove('selected');
                        });
                        // Select this card
                        this.classList.add('selected');
                    });

                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">No renewal plans available</p>';
            }
        } else {
            container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">No plans available</p>';
        }
    } catch(error) {
        console.error('Error loading renewal plans:', error);
        document.getElementById('renewal-plans-container').innerHTML = '<p style="color: var(--danger); text-align: center;">Error loading plans</p>';
    }
}

// Debounced wrapper for editAccount (200ms cooldown for async function)
const editAccount = debounce(editAccountCore, 'editAccount', 200);

// Load New Device Plans for Add Account Modal (Resellers Only)
async function loadNewDevicePlans() {
    try {
        const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
        const response = await fetch(`api/get_plans.php?viewAllAccounts=${viewAllAccounts}`);
        const result = await response.json();

        const container = document.getElementById('add-new-device-plans-container');
        container.innerHTML = ''; // Clear existing

        if(result.error == 0 && result.plans) {
            // Filter to only show new_device plans for add account cards
            const newDevicePlans = result.plans.filter(plan => plan.category === 'new_device');

            if(newDevicePlans.length > 0) {
                newDevicePlans.forEach(plan => {
                    const formattedPrice = getCurrencySymbol(plan.currency_id) + formatBalance(plan.price, plan.currency_id);

                    const card = document.createElement('div');
                    card.className = 'renewal-plan-card';
                    // Store in format expected by add_account.php: "external_id-currency_id"
                    card.dataset.planId = plan.external_id + '-' + plan.currency_id;
                    card.innerHTML = `
                        <div class="renewal-plan-checkbox"></div>
                        <div class="renewal-plan-name">${plan.name}</div>
                        <div class="renewal-plan-duration">${plan.days} days</div>
                        <div class="renewal-plan-price">${formattedPrice}</div>
                    `;

                    // Click handler to select plan
                    card.addEventListener('click', function() {
                        // Deselect all other cards
                        document.querySelectorAll('#add-new-device-plans-container .renewal-plan-card').forEach(c => {
                            c.classList.remove('selected');
                        });
                        // Select this card
                        this.classList.add('selected');
                    });

                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">No new device plans available</p>';
            }
        } else {
            container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">No plans available</p>';
        }
    } catch(error) {
        console.error('[loadNewDevicePlans] Exception caught:', error);
        console.error('[loadNewDevicePlans] Error stack:', error.stack);
        document.getElementById('add-new-device-plans-container').innerHTML = '<p style="color: var(--danger); text-align: center;">Error loading plans: ' + error.message + '</p>';
    }
}

async function submitEditAccount(e) {
    console.log('submitEditAccount called');
    e.preventDefault();

    // Check if user is reseller without admin permission
    const isSuperUser = currentUser ? (currentUser.super_user == 1 || currentUser.super_user === '1') : true;
    const isResellerAdmin = currentUser && (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1');
    const isResellerWithoutAdmin = !isSuperUser && !isResellerAdmin;

    // Check if reseller admin is in "My Accounts" mode (uses cards instead of dropdown)
    const viewAllAccounts = localStorage.getItem('viewAllAccounts') === 'true';
    const isResellerAdminInMyAccountsMode = isResellerAdmin && !viewAllAccounts;

    // Determine if we should use card selection (reseller path) or dropdown (admin path)
    // MUST check !isSuperUser - super admin always uses dropdown, never cards (v1.11.44)
    const useCardSelection = !isSuperUser && (isResellerWithoutAdmin || isResellerAdminInMyAccountsMode);

    console.log('[submitEditAccount] User type check:', { isSuperUser, isResellerAdmin, isResellerWithoutAdmin, viewAllAccounts, isResellerAdminInMyAccountsMode, useCardSelection });

    // Check if user has permission to edit phone/email/name fields
    const permissions = (currentUser && currentUser.permissions || '0|0|0|0|0|0|0|0').split('|');
    const canEditPhoneName = permissions[7] === '1';

    // Validate and format phone number (ONLY if user has permission to edit it)
    const countryCodeSelect = document.getElementById('edit-country-code').value;
    const customCode = document.getElementById('edit-custom-code').value;
    const phoneNumber = document.getElementById('edit-phone').value;

    // Skip phone validation if reseller doesn't have permission to edit it
    const shouldValidatePhone = !useCardSelection || canEditPhoneName;

    if (phoneNumber && shouldValidatePhone) {
        const countryCode = countryCodeSelect === 'custom' ? customCode : countryCodeSelect;

        // Validate country code for custom option
        if (countryCodeSelect === 'custom' && !customCode) {
            showAlert('Please enter a custom country code', 'error');
            return false;
        }

        // Validate phone number
        const validation = validatePhoneNumber(phoneNumber, countryCode);
        if (!validation.valid) {
            showAlert(validation.error, 'error');
            return false;
        }
    }

    let selectedPlan;
    let formData;

    if (useCardSelection) {
        // Get selected plan from renewal cards (reseller or reseller admin in My Accounts mode)
        const selectedCard = document.querySelector('.renewal-plan-card.selected');

        if (!selectedCard) {
            showAlert('Please select a renewal plan', 'error');
            return false;
        }

        selectedPlan = selectedCard.dataset.planId;

        // Confirm renewal
        if(!confirm('This will renew the account with the selected plan and enable the device. Continue?')) {
            return false;
        }

        // Create formData (readOnly fields are automatically included)
        formData = new FormData(e.target);

        // Add plan to formData
        formData.set('plan', selectedPlan);

        // Force status to enabled (1) for renewals
        formData.set('status', '1');
    } else {
        // Admin user - create formData normally
        formData = new FormData(e.target);
        // Admin user - get plan from dropdown
        selectedPlan = document.getElementById('edit-plan').value;

        console.log('=== EDIT ACCOUNT DEBUG ===');
        console.log('Form data entries:', Object.fromEntries(formData));
        console.log('Selected plan from dropdown:', selectedPlan);
        console.log('Plan value in formData:', formData.get('plan'));
        console.log('Dropdown element:', document.getElementById('edit-plan'));
        console.log('Dropdown selectedIndex:', document.getElementById('edit-plan').selectedIndex);
        console.log('=== END DEBUG ===');

        // Confirm if renewing with a plan
        if(selectedPlan != '0') {
            if(!confirm('This will renew the account with the selected plan. Continue?')) {
                return false;
            }
        }
    }

    // Replace phone field with full formatted number
    if (phoneNumber) {
        const fullPhone = getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber);
        formData.set('phone', fullPhone);
    }

    // Remove country_code from form data (it's already included in phone)
    formData.delete('country_code');

    try {
        console.log('Sending request to edit_account.php');
        const response = await fetch('api/edit_account.php', {
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
        const response = await fetch('api/adjust_credit.php', {
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
    console.log('[assignPlans] Called with:', { resellerId, resellerName, currentPlans, resellerCurrency });
    console.log('[assignPlans] availablePlans:', availablePlans);

    document.getElementById('assign-reseller-id').value = resellerId;
    document.getElementById('assign-reseller-name').value = resellerName;

    // Get currently assigned plans
    const plansArray = currentPlans ? currentPlans.split(',') : [];

    // Populate checkboxes
    const checkboxContainer = document.getElementById('assign-plans-checkboxes');
    checkboxContainer.innerHTML = '';

    // Filter plans to only show those matching reseller's currency
    const matchingPlans = availablePlans.filter(plan => plan.currency_id === resellerCurrency);

    console.log('[assignPlans] Matching plans:', matchingPlans);

    if (availablePlans.length === 0) {
        console.warn('[assignPlans] No plans available');
        checkboxContainer.innerHTML = '<p style="color: var(--text-tertiary); text-align: center; padding: 20px;">No plans available. Please refresh the page and try again.</p>';
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
        const response = await fetch('api/assign_plans.php', {
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
        const response = await fetch('api/update_password.php', {
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
    fetch('api/logout.php').then(() => {
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

        const response = await fetch('api/sync_accounts.php', {
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
// Database Export/Import Functions
// ===========================

// Export Database
async function exportDatabase(buttonElement) {
    const exportIcon = document.getElementById('export-icon');
    const exportStatus = document.getElementById('export-status');
    const exportBtn = buttonElement;

    console.log('[exportDatabase] Starting export...');

    try {
        // Disable button and show exporting state
        if (exportBtn) exportBtn.disabled = true;
        exportIcon.textContent = '⏳';
        exportStatus.style.display = 'block';
        exportStatus.className = 'sync-status info';
        exportStatus.textContent = 'Generating database backup...';

        const response = await fetch('scripts/export_database.php', {
            method: 'POST'
        });

        console.log('[exportDatabase] Response received:', response.status);

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        console.log('[exportDatabase] Result:', result);

        if (result.error == 0) {
            // Download the file
            const downloadLink = document.createElement('a');
            downloadLink.href = result.file_url;
            downloadLink.download = result.filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            exportStatus.className = 'sync-status success';
            exportStatus.textContent = `✓ Database exported successfully! File: ${result.filename}`;
        } else {
            exportStatus.className = 'sync-status error';
            exportStatus.textContent = `✗ Export failed: ${result.message}`;
        }

    } catch(error) {
        console.error('[exportDatabase] Error:', error);
        exportStatus.className = 'sync-status error';
        exportStatus.textContent = `✗ Export failed: ${error.message}`;
    } finally {
        // Re-enable button
        if (exportBtn) exportBtn.disabled = false;
        exportIcon.textContent = '💾';

        // Clear status after 5 seconds
        setTimeout(() => {
            exportStatus.style.display = 'none';
            exportStatus.textContent = '';
        }, 5000);
    }
}

// Handle file selection for import
function handleDBFileSelected() {
    const fileInput = document.getElementById('db-import-file');
    const fileName = document.getElementById('selected-file-name');
    const importBtn = document.getElementById('import-db-btn');

    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        fileName.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        importBtn.style.display = 'block';
    } else {
        fileName.textContent = '';
        importBtn.style.display = 'none';
    }
}

// Import Database
async function importDatabase() {
    const fileInput = document.getElementById('db-import-file');
    const importBtn = document.getElementById('import-db-btn');
    const importIcon = document.getElementById('import-icon');
    const importStatus = document.getElementById('import-status');

    if (fileInput.files.length === 0) {
        alert('Please select an SQL file to import');
        return;
    }

    // Confirm import
    if (!confirm('⚠️ WARNING: This will replace your entire database with the uploaded file. This action cannot be undone. Are you sure you want to continue?')) {
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('sql_file', file);

    try {
        // Disable button and show importing state
        importBtn.disabled = true;
        importIcon.textContent = '⏳';
        importStatus.style.display = 'block';
        importStatus.className = 'sync-status info';
        importStatus.textContent = 'Importing database... This may take a few minutes.';

        const response = await fetch('scripts/import_database.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.error == 0) {
            importStatus.className = 'sync-status success';
            importStatus.textContent = `✓ Database imported successfully! ${result.message}`;

            // Clear file selection
            fileInput.value = '';
            document.getElementById('selected-file-name').textContent = '';
            importBtn.style.display = 'none';

            // Reload page after 3 seconds
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        } else {
            importStatus.className = 'sync-status error';
            importStatus.textContent = `✗ Import failed: ${result.message}`;
        }

    } catch(error) {
        importStatus.className = 'sync-status error';
        importStatus.textContent = `✗ Import failed: ${error.message}`;
    } finally {
        // Re-enable button
        importBtn.disabled = false;
        importIcon.textContent = '📥';

        // Clear status after 10 seconds (longer for import)
        setTimeout(() => {
            if (!importStatus.textContent.includes('successfully')) {
                importStatus.style.display = 'none';
                importStatus.textContent = '';
            }
        }, 10000);
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
        const response = await fetch('api/send_stb_event.php', {
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
        const response = await fetch('api/send_stb_message.php', {
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
function handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, canEditPhoneNameCheckbox, observerCheckbox) {
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
        if (canControlStbCheckbox) {
            canControlStbCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canToggleStatusCheckbox) {
            canToggleStatusCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canAccessMessagingCheckbox) {
            canAccessMessagingCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canEditPhoneNameCheckbox) {
            canEditPhoneNameCheckbox.closest('.permission-item').style.display = 'none';
        }
        // Uncheck all permissions when observer is checked
        canEditCheckbox.checked = false;
        canAddCheckbox.checked = false;
        if (canDeleteCheckbox) canDeleteCheckbox.checked = false;
        if (canControlStbCheckbox) canControlStbCheckbox.checked = false;
        if (canToggleStatusCheckbox) canToggleStatusCheckbox.checked = false;
        if (canAccessMessagingCheckbox) canAccessMessagingCheckbox.checked = false;
        if (canEditPhoneNameCheckbox) canEditPhoneNameCheckbox.checked = false;
    } else if (isAdmin) {
        // Admin is checked - hide and uncheck Observer and all other permissions
        // Observer and Admin are mutually exclusive
        // Admin gets full access, so individual permissions are hidden
        if (observerCheckbox) {
            observerCheckbox.closest('.permission-item').style.display = 'none';
            observerCheckbox.checked = false;
        }
        canEditCheckbox.closest('.permission-item').style.display = 'none';
        canAddCheckbox.closest('.permission-item').style.display = 'none';
        if (canDeleteCheckbox) {
            canDeleteCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canControlStbCheckbox) {
            canControlStbCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canToggleStatusCheckbox) {
            canToggleStatusCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canAccessMessagingCheckbox) {
            canAccessMessagingCheckbox.closest('.permission-item').style.display = 'none';
        }
        if (canEditPhoneNameCheckbox) {
            canEditPhoneNameCheckbox.closest('.permission-item').style.display = 'none';
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
        if (canControlStbCheckbox) {
            canControlStbCheckbox.closest('.permission-item').style.display = 'flex';
        }
        if (canToggleStatusCheckbox) {
            canToggleStatusCheckbox.closest('.permission-item').style.display = 'flex';
        }
        if (canAccessMessagingCheckbox) {
            canAccessMessagingCheckbox.closest('.permission-item').style.display = 'flex';
        }
        if (canEditPhoneNameCheckbox) {
            canEditPhoneNameCheckbox.closest('.permission-item').style.display = 'flex';
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
    const canControlStbCheckbox = document.querySelector('#addResellerModal input[name="can_control_stb"]');
    const canToggleStatusCheckbox = document.querySelector('#addResellerModal input[name="can_toggle_status"]');
    const canAccessMessagingCheckbox = document.querySelector('#addResellerModal input[name="can_access_messaging"]');
    const canEditPhoneNameCheckbox = document.querySelector('#addResellerModal input[name="can_edit_phone_name"]');

    if (adminCheckbox && canEditCheckbox && canAddCheckbox) {
        adminCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(this, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, canEditPhoneNameCheckbox, observerCheckbox);
        });
    }

    if (observerCheckbox) {
        observerCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, canEditPhoneNameCheckbox, this);
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
    const canControlStbCheckbox = document.getElementById('edit-can-control-stb');
    const canToggleStatusCheckbox = document.getElementById('edit-can-toggle-status');
    const canAccessMessagingCheckbox = document.getElementById('edit-can-access-messaging');
    const canEditPhoneNameCheckbox = document.getElementById('edit-can-edit-phone-name');

    if (adminCheckbox && canEditCheckbox && canAddCheckbox) {
        adminCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(this, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, canEditPhoneNameCheckbox, observerCheckbox);
        });

        // Initial state check when modal is opened
        handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, canEditPhoneNameCheckbox, observerCheckbox);
    }

    if (observerCheckbox) {
        observerCheckbox.addEventListener('change', function() {
            handleAdminPermissionToggle(adminCheckbox, canEditCheckbox, canAddCheckbox, canDeleteCheckbox, canControlStbCheckbox, canToggleStatusCheckbox, canAccessMessagingCheckbox, this);
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

    // Reload transactions with new filter (for reseller admins)
    console.log('[toggleAccountViewMode] Calling loadTransactions()...');
    await loadTransactions();

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
        const url = `api/get_user_info.php?viewAllAccounts=${viewAllAccounts}`;
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

    // IMPORTANT: Restore tab FIRST before anything else
    // This must run before any tab initialization
    restoreActiveTab();

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

    // Load reminder settings on dashboard load
    loadReminderSettings();
});

/**
 * ============================================================================
 * EXPIRY REMINDER SYSTEM (v1.7.8)
 * ============================================================================
 * Automated churn-prevention messaging for accounts expiring soon
 */

/**
 * Load reminder settings for current user
 */
async function loadReminderSettings() {
    try {
        const response = await fetch('api/get_reminder_settings.php');
        const result = await response.json();

        if(result.error == 0) {
            const settings = result.settings;

            // Populate form fields
            document.getElementById('reminder-days').value = settings.days_before_expiry;
            document.getElementById('reminder-template').value = settings.message_template;
            document.getElementById('auto-send-enabled').checked = settings.auto_send_enabled == 1;

            // Update last sweep info
            if(settings.last_sweep_at) {
                const lastSweep = new Date(settings.last_sweep_at);
                const lastSweepStr = lastSweep.toLocaleString();
                document.getElementById('last-sweep-info').textContent = `Last automatic sweep: ${lastSweepStr}`;
            } else {
                document.getElementById('last-sweep-info').textContent = 'Automatic reminders not sent yet';
            }

            // Show status based on auto-send
            if(settings.auto_send_enabled == 1) {
                document.getElementById('last-sweep-info').innerHTML += ' <span style="color: var(--success); font-weight: 600;">● ACTIVE</span>';
            }
        }
    } catch(error) {
        console.error('Error loading reminder settings:', error);
    }
}

/**
 * Save reminder settings (toggle, days, and template)
 */
async function saveReminderSettings() {
    const daysBeforeInput = document.getElementById('reminder-days');
    const templateInput = document.getElementById('reminder-template');
    const autoSendCheckbox = document.getElementById('auto-send-enabled');

    const daysBefore = parseInt(daysBeforeInput.value);
    const template = templateInput.value.trim();
    const autoSendEnabled = autoSendCheckbox.checked ? 1 : 0;

    // Validation
    if(daysBefore < 1 || daysBefore > 90) {
        showReminderStatus('error', 'Days before expiry must be between 1 and 90');
        return;
    }

    if(!template) {
        showReminderStatus('error', 'Message template is required');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('days_before_expiry', daysBefore);
        formData.append('message_template', template);
        formData.append('auto_send_enabled', autoSendEnabled);

        const response = await fetch('api/update_reminder_settings.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if(result.error == 0) {
            let message = 'Settings saved successfully';
            if(autoSendEnabled) {
                message += '. Automatic reminders ENABLED.';
            } else {
                message += '. Automatic reminders DISABLED.';
            }
            showReminderStatus('success', message);

            // Reload settings to update status display
            loadReminderSettings();
        } else {
            showReminderStatus('error', result.err_msg || 'Failed to save settings');
        }
    } catch(error) {
        console.error('Error saving reminder settings:', error);
        showReminderStatus('error', 'Network error while saving settings');
    }
}

/**
 * Send expiry reminders - manual sweep
 */
async function sendExpiryReminders() {
    const sendBtn = document.querySelector('.btn-reminder-send');
    const originalContent = sendBtn.innerHTML;

    // Disable button and show loading
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span>⏳</span> Sending...';

    // Hide previous results
    document.getElementById('reminder-results').style.display = 'none';

    try {
        console.log('[Reminder Debug] Attempting to fetch send_expiry_reminders.php...');

        const response = await fetch('api/send_expiry_reminders.php', {
            method: 'POST'
        });

        console.log('[Reminder Debug] Response status:', response.status);
        console.log('[Reminder Debug] Response headers:', [...response.headers.entries()]);

        if(!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        console.log('[Reminder Debug] Raw response:', text);

        const result = JSON.parse(text);

        if(result.error == 0) {
            const { sent, skipped, failed, total, days_before, target_date, results } = result;

            // Show summary status
            let statusClass = 'success';
            let statusMessage = `Sweep complete: ${sent} sent, ${skipped} skipped, ${failed} failed (${total} accounts expiring on ${target_date})`;

            if(failed > 0) {
                statusClass = 'warning';
            } else if(sent === 0 && skipped === 0) {
                statusClass = 'info';
                statusMessage = `No accounts found expiring in ${days_before} days (target date: ${target_date})`;
            }

            showReminderStatus(statusClass, statusMessage);

            // Show detailed results
            displayReminderResults(results);

            // Update last sweep info
            const now = new Date();
            document.getElementById('last-sweep-info').textContent = `Last sweep: ${now.toLocaleString()}`;

            // Send notification via service worker
            if('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'REMINDER_SENT',
                    data: {
                        sent,
                        skipped,
                        failed,
                        total
                    }
                });
            }

        } else {
            showReminderStatus('error', result.err_msg || 'Failed to send reminders');
        }
    } catch(error) {
        console.error('[Reminder Debug] Full error object:', error);
        console.error('[Reminder Debug] Error name:', error.name);
        console.error('[Reminder Debug] Error message:', error.message);
        console.error('[Reminder Debug] Error stack:', error.stack);
        showReminderStatus('error', `Network error: ${error.message}`);
    } finally {
        // Re-enable button
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalContent;
    }
}

/**
 * Display reminder results in detailed view
 */
function displayReminderResults(results) {
    if(!results || results.length === 0) {
        return;
    }

    const resultsContainer = document.getElementById('reminder-results');
    resultsContainer.innerHTML = '';
    resultsContainer.style.display = 'block';

    results.forEach(result => {
        const item = document.createElement('div');
        item.className = `reminder-result-item ${result.status}`;

        let icon = '';
        let message = '';

        if(result.status === 'sent') {
            icon = '✓';
            message = `${result.full_name || result.account} (${result.mac}) - Message sent`;
        } else if(result.status === 'skipped') {
            icon = '⊗';
            message = `${result.full_name || result.account} - ${result.reason}`;
        } else if(result.status === 'failed') {
            icon = '✗';
            message = `${result.full_name || result.account} - ${result.error}`;
        }

        item.innerHTML = `<span class="icon">${icon}</span><span>${message}</span>`;
        resultsContainer.appendChild(item);
    });
}

/**
 * Show reminder status message
 */
function showReminderStatus(type, message) {
    const statusDiv = document.getElementById('reminder-status');
    statusDiv.className = `reminder-status ${type}`;
    statusDiv.textContent = message;
    statusDiv.style.display = 'block';

    // Auto-hide after 5 seconds for non-error messages
    if(type !== 'error') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

/**
 * Check messaging tab permission and hide tab if not allowed (v1.7.9)
 */
function checkMessagingTabPermission() {
    const permissions = currentUser?.permissions?.split('|') || [];
    const isSuperAdmin = currentUser?.super_user == 1;
    const isResellerAdmin = currentUser?.is_reseller_admin === true || currentUser?.is_reseller_admin === '1';
    const canAccessMessaging = permissions[6] === '1';

    // Super admin and reseller admin always have access to messaging
    // Regular resellers need explicit permission
    const hasAccess = isSuperAdmin || isResellerAdmin || canAccessMessaging;

    const messagingTab = document.querySelector('.tab[onclick*="messaging"]');
    if(messagingTab) {
        messagingTab.style.display = hasAccess ? '' : 'none';
    }
}

/**
 * Show/hide reminder section based on permissions
 * Call this in checkAuth() function
 */
function showReminderSection() {
    // Check if user has STB control permission OR messaging permission
    const permissions = currentUser?.permissions?.split('|') || [];
    const isSuperAdmin = currentUser?.super_user == 1;
    const isResellerAdmin = currentUser?.is_reseller_admin === true || currentUser?.is_reseller_admin === '1';
    const canControlStb = permissions[4] === '1';
    const canAccessMessaging = permissions[6] === '1';

    const reminderSection = document.getElementById('reminder-section');
    const historySection = document.getElementById('reminder-history-section');

    // Show reminder section if user has STB control permission (for backward compatibility)
    // Super admin and reseller admin always have access
    if(isSuperAdmin || isResellerAdmin || canControlStb || canAccessMessaging) {
        if(reminderSection) reminderSection.style.display = 'block';
        if(historySection) {
            historySection.style.display = 'block';
            // Set default date to today
            setHistoryToday();
        }
    } else {
        if(reminderSection) reminderSection.style.display = 'none';
        if(historySection) historySection.style.display = 'none';
    }
}

/**
 * ============================================================================
 * REMINDER HISTORY SYSTEM
 * ============================================================================
 */

// Global variables for pagination and filtering
let allReminders = [];
let filteredReminders = [];
let currentHistoryPage = 1;
let historyPageSize = 10;

/**
 * Load reminder history for selected date
 */
async function loadReminderHistory() {
    const dateInput = document.getElementById('history-date');
    const date = dateInput.value;

    if(!date) {
        return;
    }

    try {
        const response = await fetch(`api/get_reminder_history.php?date=${date}`);
        const result = await response.json();

        if(result.error == 0) {
            // Store all reminders globally
            allReminders = result.reminders || [];

            // Update stats
            document.getElementById('history-total-count').textContent = `${result.total} reminder${result.total !== 1 ? 's' : ''}`;
            document.getElementById('history-sent-count').textContent = `${result.sent} sent`;
            document.getElementById('history-failed-count').textContent = `${result.failed} failed`;

            // Reset to first page and apply filters
            currentHistoryPage = 1;
            filterReminderHistory();
        } else {
            console.error('Error loading reminder history:', result.err_msg);
            allReminders = [];
            displayReminderHistoryTable([]);
        }
    } catch(error) {
        console.error('Error loading reminder history:', error);
        allReminders = [];
        displayReminderHistoryTable([]);
    }
}

/**
 * Display reminder history in table
 */
function displayReminderHistoryTable(reminders) {
    const tbody = document.getElementById('history-tbody');
    const pagination = document.getElementById('history-pagination');

    if(!reminders || reminders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                    ${filteredReminders.length === 0 && allReminders.length > 0 ? 'No reminders match your search criteria' : 'No reminders found for this date'}
                </td>
            </tr>
        `;
        pagination.style.display = 'none';
        return;
    }

    tbody.innerHTML = reminders.map(r => {
        const time = new Date(r.sent_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        const statusClass = r.status === 'sent' ? 'status-active' : 'status-inactive';
        const statusText = r.status === 'sent' ? '✓ Sent' : '✗ Failed';
        const message = r.message.length > 80 ? r.message.substring(0, 80) + '...' : r.message;

        return `
            <tr>
                <td>${time}</td>
                <td>${r.username}</td>
                <td>${r.full_name || '-'}</td>
                <td>${r.mac}</td>
                <td>${r.end_date}</td>
                <td>${r.days_before} days</td>
                <td><span class="${statusClass}">${statusText}</span></td>
                <td title="${escapeHtml(r.message)}">${escapeHtml(message)}</td>
            </tr>
        `;
    }).join('');
}

/**
 * Change history date by offset (days)
 */
function changeHistoryDate(offset) {
    const dateInput = document.getElementById('history-date');
    const currentDate = new Date(dateInput.value || new Date());
    currentDate.setDate(currentDate.getDate() + offset);

    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    const day = String(currentDate.getDate()).padStart(2, '0');

    dateInput.value = `${year}-${month}-${day}`;
    loadReminderHistory();
}

/**
 * Set history date to today
 */
function setHistoryToday() {
    const dateInput = document.getElementById('history-date');
    const today = new Date();

    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    dateInput.value = `${year}-${month}-${day}`;
    loadReminderHistory();
}

/**
 * Filter reminder history based on search and status
 */
function filterReminderHistory() {
    const searchInput = document.getElementById('history-search');
    const statusFilter = document.getElementById('history-status-filter');

    const searchTerm = searchInput.value.toLowerCase().trim();
    const statusValue = statusFilter.value;

    // Filter reminders
    filteredReminders = allReminders.filter(reminder => {
        // Search filter
        const matchesSearch = !searchTerm ||
            (reminder.username && reminder.username.toLowerCase().includes(searchTerm)) ||
            (reminder.full_name && reminder.full_name.toLowerCase().includes(searchTerm)) ||
            (reminder.mac && reminder.mac.toLowerCase().includes(searchTerm));

        // Status filter
        const matchesStatus = !statusValue || reminder.status === statusValue;

        return matchesSearch && matchesStatus;
    });

    // Reset to first page when filtering
    currentHistoryPage = 1;

    // Display filtered results with pagination
    displayReminderHistoryWithPagination();
}

/**
 * Display reminder history with pagination
 */
function displayReminderHistoryWithPagination() {
    const totalItems = filteredReminders.length;
    const totalPages = Math.ceil(totalItems / historyPageSize);

    // Calculate start and end indices
    const startIndex = (currentHistoryPage - 1) * historyPageSize;
    const endIndex = Math.min(startIndex + historyPageSize, totalItems);

    // Get current page data
    const pageData = filteredReminders.slice(startIndex, endIndex);

    // Display table
    displayReminderHistoryTable(pageData);

    // Update pagination controls
    updateHistoryPagination(totalItems, totalPages, startIndex, endIndex);
}

/**
 * Update pagination controls
 */
function updateHistoryPagination(totalItems, totalPages, startIndex, endIndex) {
    const pagination = document.getElementById('history-pagination');
    const pageInfo = document.getElementById('history-page-info');
    const prevBtn = document.getElementById('history-prev-btn');
    const nextBtn = document.getElementById('history-next-btn');
    const pageNumbers = document.getElementById('history-page-numbers');

    if(totalItems === 0) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    // Update page info
    pageInfo.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalItems}`;

    // Update prev/next buttons
    prevBtn.disabled = currentHistoryPage === 1;
    nextBtn.disabled = currentHistoryPage === totalPages;

    // Generate page numbers
    pageNumbers.innerHTML = '';
    const maxPageButtons = 5;
    let startPage = Math.max(1, currentHistoryPage - Math.floor(maxPageButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxPageButtons - 1);

    if(endPage - startPage < maxPageButtons - 1) {
        startPage = Math.max(1, endPage - maxPageButtons + 1);
    }

    for(let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('span');
        pageBtn.className = 'page-number' + (i === currentHistoryPage ? ' active' : '');
        pageBtn.textContent = i;
        pageBtn.onclick = () => {
            currentHistoryPage = i;
            displayReminderHistoryWithPagination();
        };
        pageNumbers.appendChild(pageBtn);
    }
}

/**
 * Change history page
 */
function changeHistoryPage(direction) {
    const totalPages = Math.ceil(filteredReminders.length / historyPageSize);

    if(direction === -1 && currentHistoryPage > 1) {
        currentHistoryPage--;
        displayReminderHistoryWithPagination();
    } else if(direction === 1 && currentHistoryPage < totalPages) {
        currentHistoryPage++;
        displayReminderHistoryWithPagination();
    }
}

/**
 * Change history page size
 */
function changeHistoryPageSize() {
    const pageSizeSelect = document.getElementById('history-page-size');
    historyPageSize = parseInt(pageSizeSelect.value);
    currentHistoryPage = 1;
    displayReminderHistoryWithPagination();
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Global keyboard event handler for all modals
 * Close modals when Escape key is pressed
 */
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' || event.keyCode === 27) {
        // Find and close all open modals using closeModal() function
        const allModals = document.querySelectorAll('.modal.show');

        allModals.forEach(modal => {
            const modalId = modal.id;
            console.log('[ESC Key] Closing modal:', modalId);

            // Use the closeModal function to properly close
            closeModal(modalId);

            // Call specific close handlers if they exist
            if (modalId === 'editAccountModal' && typeof closeEditModal === 'function') {
                closeEditModal();
            } else if (modalId === 'addAccountModal' && typeof closeAddModal === 'function') {
                closeAddModal();
            } else if (modalId === 'editResellerModal' && typeof closeEditResellerModal === 'function') {
                closeEditResellerModal();
            }
        });
    }
});

/**
 * Refresh the current page
 */
function refreshPage() {
    location.reload();
}

/**
 * Restore the last active tab after page load/refresh
 */
function restoreActiveTab() {
    console.log('[Tab Restore] Starting...');

    // Check if this is a fresh login (set by login page)
    const isFreshLogin = sessionStorage.getItem('freshLogin');
    console.log('[Tab Restore] Fresh login flag:', isFreshLogin);

    if (isFreshLogin === 'true') {
        console.log('[Tab Restore] Fresh login detected - clearing saved tabs');
        // Clear the flag
        sessionStorage.removeItem('freshLogin');
        // Clear saved tabs to start fresh
        localStorage.removeItem('currentTab');
        localStorage.removeItem('messagingSubTab');
        // Don't restore anything - stay on default (Accounts) tab
        console.log('[Tab Restore] Staying on default Accounts tab');
        return;
    }

    // Get saved main tab
    const savedTab = localStorage.getItem('currentTab');
    console.log('[Tab Restore] Saved tab:', savedTab);

    if (savedTab) {
        console.log('[Tab Restore] Restoring tab:', savedTab);
        // Find the tab button and click it to restore the tab
        const tabButtons = document.querySelectorAll('.tab');
        tabButtons.forEach(btn => {
            if (btn.textContent.toLowerCase().includes(savedTab.toLowerCase()) ||
                btn.getAttribute('onclick')?.includes(savedTab)) {
                console.log('[Tab Restore] Clicking tab button:', btn.textContent);
                btn.click();
            }
        });
    }

    // If we're on the messaging tab, restore the sub-tab (STB/SMS)
    if (savedTab === 'messaging') {
        setTimeout(() => {
            const savedMessagingTab = localStorage.getItem('messagingSubTab');
            console.log('[Tab Restore] Restoring messaging sub-tab:', savedMessagingTab);
            if (savedMessagingTab && typeof switchMessagingTab === 'function') {
                switchMessagingTab(savedMessagingTab);
            }
        }, 100);
    }
}

// ========================================
// iOS PWA Enhancements (v1.10.0)
// ========================================

// ========================================
// 1. Bottom Navigation Sync
// ========================================

function syncBottomNav(activeTab) {
    const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
    bottomNavItems.forEach(item => {
        if (item.dataset.tab === activeTab) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// Wrap existing switchTab function to sync bottom nav
if (typeof switchTab !== 'undefined') {
    const originalSwitchTab = switchTab;
    switchTab = function(tab) {
        originalSwitchTab(tab);
        syncBottomNav(tab);
    };
}

// ========================================
// 2. Pull-to-Refresh Implementation
// ========================================

let pullStartY = 0;
let pulling = false;
let pullDistance = 0;

function initPullToRefresh() {
    // Only enable on mobile (screen width <= 768px)
    if (window.innerWidth > 768) return;

    const content = document.querySelector('.content') || document.querySelector('main');
    const pullIndicator = document.getElementById('pull-to-refresh');

    if (!content || !pullIndicator) {
        console.log('[Pull-to-Refresh] Elements not found, skipping initialization');
        return;
    }

    console.log('[Pull-to-Refresh] Initialized on mobile');

    content.addEventListener('touchstart', (e) => {
        // Only trigger if at VERY top of page (scrollY = 0) - v1.10.1 fix
        if (window.scrollY === 0 && content.scrollTop === 0) {
            pullStartY = e.touches[0].clientY;
            pulling = true;
        }
    }, { passive: true });

    content.addEventListener('touchmove', (e) => {
        if (!pulling) return;

        const touchY = e.touches[0].clientY;
        pullDistance = touchY - pullStartY;

        // Only show if pulling DOWN (positive distance) - v1.10.1 fix
        if (pullDistance > 0) {
            // Show indicator when pulled down 80px
            if (pullDistance > 80) {
                pullIndicator.style.display = 'flex';
                pullIndicator.querySelector('.pull-to-refresh-text').textContent = 'Release to refresh';
            } else if (pullDistance > 40) {
                pullIndicator.style.display = 'flex';
                pullIndicator.querySelector('.pull-to-refresh-text').textContent = 'Pull down to refresh';
            }
        }
    }, { passive: true });

    content.addEventListener('touchend', async (e) => {
        if (!pulling) return;

        if (pullDistance > 80) {
            // Trigger refresh
            pullIndicator.querySelector('.pull-to-refresh-text').textContent = 'Refreshing...';
            pullIndicator.querySelector('.pull-to-refresh-icon').style.animation = 'rotate 1s linear infinite';

            // Get current active tab
            const activeTabElement = document.querySelector('.tab-pane.active');
            const currentTab = activeTabElement ? activeTabElement.id.replace('-content', '') : 'dashboard';

            console.log('[Pull-to-Refresh] Refreshing tab:', currentTab);

            // Reload current tab data
            await refreshTabData(currentTab);

            // Hide indicator after refresh
            setTimeout(() => {
                pullIndicator.style.display = 'none';
                pullIndicator.querySelector('.pull-to-refresh-text').textContent = 'Release to refresh';
                pullIndicator.querySelector('.pull-to-refresh-icon').style.animation = '';
            }, 1000);
        } else {
            pullIndicator.style.display = 'none';
        }

        pulling = false;
        pullDistance = 0;
    }, { passive: true });
}

async function refreshTabData(tabId) {
    try {
        switch(tabId) {
            case 'dashboard':
                if (typeof loadDashboard === 'function') {
                    await loadDashboard();
                }
                break;
            case 'accounts':
                if (typeof loadAccounts === 'function') {
                    await loadAccounts();
                }
                break;
            case 'resellers':
                if (typeof loadResellers === 'function') {
                    await loadResellers();
                }
                break;
            case 'messaging':
                if (typeof loadTemplates === 'function') {
                    await loadTemplates();
                }
                if (typeof loadSMSHistory === 'function') {
                    await loadSMSHistory();
                }
                break;
            case 'reports':
                if (typeof loadReports === 'function') {
                    await loadReports();
                }
                break;
            default:
                console.log('[Pull-to-Refresh] Unknown tab:', tabId);
        }
        console.log('[Pull-to-Refresh] Data refreshed for tab:', tabId);
    } catch (error) {
        console.error('[Pull-to-Refresh] Error refreshing data:', error);
    }
}

// ========================================
// 3. Skeleton Loading Helpers
// ========================================

function showSkeletonLoader(containerId, count = 3) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let skeletonHTML = '<div class="skeleton-loader">';
    for (let i = 0; i < count; i++) {
        skeletonHTML += `
            <div class="skeleton-card">
                <div class="skeleton-header"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line short"></div>
            </div>
        `;
    }
    skeletonHTML += '</div>';

    container.innerHTML = skeletonHTML;
    console.log('[Skeleton Loader] Displayed for:', containerId);
}

function hideSkeletonLoader(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const skeleton = container.querySelector('.skeleton-loader');
    if (skeleton) {
        skeleton.remove();
        console.log('[Skeleton Loader] Removed from:', containerId);
    }
}

// ========================================
// 4. iOS Viewport Height Fix
// ========================================

// Fix for iOS viewport height (accounts for address bar)
function setIOSViewportHeight() {
    if (window.innerWidth <= 768) {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
}

// Update on resize and orientation change
window.addEventListener('resize', setIOSViewportHeight);
window.addEventListener('orientationchange', setIOSViewportHeight);

// ========================================
// 5. iOS Haptic Feedback (Optional)
// ========================================

function triggerHaptic(type = 'light') {
    // Check if iOS and if Haptic Feedback API is available
    if (navigator.vibrate) {
        switch(type) {
            case 'light':
                navigator.vibrate(10);
                break;
            case 'medium':
                navigator.vibrate(20);
                break;
            case 'heavy':
                navigator.vibrate(30);
                break;
            case 'success':
                navigator.vibrate([10, 50, 10]);
                break;
            case 'error':
                navigator.vibrate([50, 50, 50]);
                break;
        }
    }
}

// Add haptic feedback to bottom nav items
document.addEventListener('DOMContentLoaded', () => {
    const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
    bottomNavItems.forEach(item => {
        item.addEventListener('click', () => {
            triggerHaptic('light');
        });
    });
});

// ========================================
// 6. Initialize All iOS Features
// ========================================

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('[iOS PWA] Initializing enhancements...');

    // Initialize pull-to-refresh on mobile
    initPullToRefresh();

    // Set iOS viewport height
    setIOSViewportHeight();

    // Sync bottom nav with current tab
    const activeTab = document.querySelector('.tab-pane.active');
    if (activeTab) {
        const tabId = activeTab.id.replace('-content', '');
        syncBottomNav(tabId);
    }

    console.log('[iOS PWA] Enhancements initialized successfully');
});

// Reinitialize on window resize (in case orientation changes)
window.addEventListener('resize', () => {
    if (window.innerWidth <= 768 && !pulling) {
        // Reinit pull-to-refresh if needed
        const content = document.querySelector('.content') || document.querySelector('main');
        if (content && content._pullToRefreshInit !== true) {
            initPullToRefresh();
            content._pullToRefreshInit = true;
        }
    }
});

// ========================================
// iOS PWA Navbar Hide/Show on Scroll (v1.10.1)
// ========================================

let lastScrollY = 0;
let ticking = false;

function handleNavbarScroll() {
    // Only on mobile
    if (window.innerWidth > 768) return;

    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    const currentScrollY = window.scrollY;

    if (currentScrollY > 80) {
        // Scrolled down past threshold - hide navbar
        navbar.classList.add('navbar-hidden');
    } else {
        // At top of page (< 80px) - show navbar
        navbar.classList.remove('navbar-hidden');
    }

    lastScrollY = currentScrollY;
    ticking = false;
}

// Use requestAnimationFrame for smooth performance
function onScroll() {
    if (!ticking) {
        window.requestAnimationFrame(handleNavbarScroll);
        ticking = true;
    }
}

// Initialize navbar hide/show
if (window.innerWidth <= 768) {
    window.addEventListener('scroll', onScroll, { passive: true });
    console.log('[iOS PWA] Navbar hide/show initialized');
}

// Mobile Dashboard View (shows stats + tabs) - v1.10.1 fixed dashboard navigation
function showMobileDashboard() {
    if (window.innerWidth > 768) return; // Only on mobile

    // Remove all mobile-tab-* classes
    document.body.classList.remove('mobile-tab-dashboard', 'mobile-tab-accounts', 'mobile-tab-resellers', 'mobile-tab-plans', 'mobile-tab-transactions', 'mobile-tab-stb-control', 'mobile-tab-messaging', 'mobile-tab-reports');

    // Add dashboard class to show stats and tabs
    document.body.classList.add('mobile-tab-dashboard');

    // Update bottom nav active state
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        if (item.dataset.tab === 'dashboard') {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Update top tab bar active state (highlight first tab)
    document.querySelectorAll('.tab').forEach((tab, index) => {
        if (index === 0) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    // Show first tab content (accounts)
    document.querySelectorAll('.tab-content').forEach((content, index) => {
        if (index === 0) {
            content.classList.add('active');
        } else {
            content.classList.remove('active');
        }
    });

    // Scroll to top smoothly
    window.scrollTo({ top: 0, behavior: 'smooth' });

    console.log('[iOS PWA] Mobile dashboard view activated');
}

// Mobile Settings View (v1.10.1 - fixed overlay issue)
function showMobileSettings() {
    if (window.innerWidth > 768) return; // Only on mobile

    const settingsPage = document.getElementById('mobile-settings-page');
    if (!settingsPage) return;

    // Hide navbar, stats, tabs, and main content
    const navbar = document.querySelector('.navbar');
    const statsGrid = document.querySelector('.stats-grid');
    const tabs = document.querySelector('.tabs');
    const content = document.querySelector('.content');

    if (navbar) navbar.style.display = 'none';
    if (statsGrid) statsGrid.style.display = 'none';
    if (tabs) tabs.style.display = 'none';
    if (content) content.style.display = 'none';

    // Show settings page
    settingsPage.style.display = 'block';

    // Remove all mobile-tab-* classes
    document.body.classList.remove('mobile-tab-dashboard', 'mobile-tab-accounts', 'mobile-tab-resellers', 'mobile-tab-plans', 'mobile-tab-transactions', 'mobile-tab-stb-control', 'mobile-tab-messaging', 'mobile-tab-reports');

    // Add settings class
    document.body.classList.add('mobile-tab-settings');

    // Update bottom nav active state
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        if (item.dataset.tab === 'settings') {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Populate user info - use global currentUser variable (not window.currentUser)
    console.log('[Mobile Settings] currentUser:', currentUser);
    const username = currentUser ? currentUser.name : (localStorage.getItem('currentUser') || sessionStorage.getItem('username') || 'User');
    const isSuperAdmin = currentUser ? currentUser.super_user == 1 : false;
    const isResellerAdmin = currentUser ? (currentUser.is_reseller_admin === true || currentUser.is_reseller_admin === '1') : false;
    const isObserver = currentUser ? currentUser.is_observer == 1 : false;
    const isRegularReseller = currentUser && !isSuperAdmin && !isResellerAdmin && !isObserver;

    console.log('[Mobile Settings] User type:', {
        isSuperAdmin,
        isResellerAdmin,
        isObserver,
        isRegularReseller,
        super_user: currentUser?.super_user,
        is_reseller_admin: currentUser?.is_reseller_admin,
        is_observer: currentUser?.is_observer
    });

    // Set username
    document.getElementById('settings-username').textContent = username;

    // Set avatar initial (first letter of username)
    const avatarInitial = username.charAt(0).toUpperCase();
    document.getElementById('settings-avatar-initial').textContent = avatarInitial;

    // Set role - proper detection (check super admin FIRST)
    let role = 'Super Admin';
    if (isObserver) {
        role = 'Observer';
    } else if (isSuperAdmin) {
        role = 'Super Admin';
    } else if (isResellerAdmin) {
        role = 'Reseller Admin';
    } else if (isRegularReseller) {
        role = 'Reseller';
    }
    document.getElementById('settings-role').textContent = role;

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    console.log('[iOS PWA] Mobile settings view activated');
}

function closeMobileSettings() {
    const settingsPage = document.getElementById('mobile-settings-page');
    if (!settingsPage) return;

    // Show navbar, stats, tabs, and main content
    const navbar = document.querySelector('.navbar');
    const statsGrid = document.querySelector('.stats-grid');
    const tabs = document.querySelector('.tabs');
    const content = document.querySelector('.content');

    if (navbar) navbar.style.display = '';
    if (statsGrid) statsGrid.style.display = '';
    if (tabs) tabs.style.display = '';
    if (content) content.style.display = '';

    // Hide settings page
    settingsPage.style.display = 'none';

    // Remove settings class
    document.body.classList.remove('mobile-tab-settings');

    // Switch back to accounts tab
    switchTab('accounts');

    console.log('[iOS PWA] Mobile settings view closed');
}

// Change Password Functions (v1.10.1)
function showChangePassword() {
    const modal = document.getElementById('change-password-modal');
    if (!modal) return;

    // Clear previous values
    document.getElementById('current-password').value = '';
    document.getElementById('new-password').value = '';
    document.getElementById('confirm-password').value = '';
    document.getElementById('password-error').style.display = 'none';

    // Show modal
    modal.style.display = 'flex';

    console.log('[iOS PWA] Change password modal opened');
}

function closeChangePassword() {
    const modal = document.getElementById('change-password-modal');
    if (!modal) return;

    // Hide modal
    modal.style.display = 'none';

    console.log('[iOS PWA] Change password modal closed');
}

function saveNewPassword() {
    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const errorDiv = document.getElementById('password-error');

    // Validation
    if (!currentPassword || !newPassword || !confirmPassword) {
        errorDiv.textContent = 'All fields are required';
        errorDiv.style.display = 'block';
        return;
    }

    if (newPassword.length < 6) {
        errorDiv.textContent = 'New password must be at least 6 characters';
        errorDiv.style.display = 'block';
        return;
    }

    if (newPassword !== confirmPassword) {
        errorDiv.textContent = 'New passwords do not match';
        errorDiv.style.display = 'block';
        return;
    }

    // Call change password API
    const username = localStorage.getItem('currentUser') || sessionStorage.getItem('username');

    fetch('api/change_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `username=${encodeURIComponent(username)}&current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error === 0) {
            // Success
            alert('Password changed successfully!');
            closeChangePassword();
        } else {
            // Error
            errorDiv.textContent = data.message || 'Failed to change password';
            errorDiv.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Change password error:', error);
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.style.display = 'block';
    });

    console.log('[iOS PWA] Attempting to change password');
}

// Initialize mobile tab body class on page load
if (window.innerWidth <= 768) {
    // Default to accounts tab on mobile (v1.10.1 - no dashboard button)
    switchTab('accounts');
    console.log('[iOS PWA] Default mobile tab: accounts');
}

// ========================================
// End of iOS PWA Enhancements (v1.10.1)
// ========================================

// ========================================
// Modal State Safety Mechanism (v1.11.3)
// Prevents page from getting stuck/locked
// ========================================

// Safety check: Ensure no modals are blocking interaction
function resetBodyScrollState() {
    // Check if any modals are actually open
    const modals = document.querySelectorAll('.modal.show');

    if (modals.length === 0) {
        // No modals are open, ensure body is not locked
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }
}

// Run safety check periodically (every 2 seconds)
setInterval(resetBodyScrollState, 2000);

// Also reset on any click event (in case modal state is stuck)
document.addEventListener('click', function() {
    // Small delay to allow modal operations to complete first
    setTimeout(resetBodyScrollState, 100);
}, true);

// Escape key should always close modals and unlock page
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close all open modals
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            closeModal(modal.id);
        });

        // Force reset body state
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }
});

// ========================================
// End of Modal Safety Mechanism
// ========================================

// ========================================
// Stalker Portal Settings (v1.11.14)
// Super Admin Only
// ========================================

/**
 * Load Stalker Portal settings from server
 */
async function loadStalkerSettings() {
    try {
        const response = await fetch('api/get_stalker_settings.php');
        const data = await response.json();

        if (data.error === 0) {
            const settings = data.settings;

            // Populate form fields
            document.getElementById('stalker-server-address').value = settings.server_address || '';
            document.getElementById('stalker-server-2-address').value = settings.server_2_address || '';
            document.getElementById('stalker-api-username').value = settings.api_username || '';
            document.getElementById('stalker-api-password').value = settings.api_password || '';

            // Auto-generate base URLs from server addresses
            const primaryServer = settings.server_address || '';
            const secondaryServer = settings.server_2_address || '';
            document.getElementById('stalker-api-base-url').value = primaryServer ? primaryServer.replace(/\/+$/, '') + '/stalker_portal/api/' : '';
            document.getElementById('stalker-api-2-base-url').value = (secondaryServer || primaryServer) ? (secondaryServer || primaryServer).replace(/\/+$/, '') + '/stalker_portal/api/' : '';

            // Set dual server mode checkbox and update visibility
            const dualServerCheckbox = document.getElementById('stalker-dual-server-mode');
            const dualModeEnabled = settings.dual_server_mode_enabled || false;
            if (dualServerCheckbox) {
                dualServerCheckbox.checked = dualModeEnabled;
            }

            // Store the original value for cancel functionality
            originalDualServerMode = dualModeEnabled;

            // Show/hide secondary server fields based on dual mode
            updateSecondaryServerVisibility(dualModeEnabled);

            // Show/hide warning based on whether servers are different
            updateDualServerWarning(primaryServer, secondaryServer);

            if (data.from_config) {
                showStalkerStatus('Settings loaded from config file. Save to enable database storage.', 'info');
            }
        } else {
            showStalkerStatus(data.message || 'Failed to load settings', 'error');
        }
    } catch (error) {
        console.error('Error loading Stalker settings:', error);
        showStalkerStatus('Failed to load Stalker settings', 'error');
    }
}

/**
 * Save Stalker Portal settings
 */
async function saveStalkerSettings() {
    const serverAddress = document.getElementById('stalker-server-address').value.trim();
    const server2Address = document.getElementById('stalker-server-2-address').value.trim();
    const apiUsername = document.getElementById('stalker-api-username').value.trim();
    const apiPassword = document.getElementById('stalker-api-password').value;
    const testConnection = document.getElementById('stalker-test-connection').checked;
    const dualServerModeEnabled = document.getElementById('stalker-dual-server-mode').checked;

    // Auto-generate base URLs from server addresses
    const apiBaseUrl = serverAddress ? serverAddress.replace(/\/+$/, '') + '/stalker_portal/api/' : '';
    const api2BaseUrl = (server2Address || serverAddress) ? (server2Address || serverAddress).replace(/\/+$/, '') + '/stalker_portal/api/' : '';

    // Validation
    if (!serverAddress) {
        showStalkerStatus('Primary server address is required', 'error');
        return;
    }

    if (!apiUsername) {
        showStalkerStatus('API username is required', 'error');
        return;
    }

    showStalkerStatus('Saving settings...', 'info');

    try {
        const formData = new FormData();
        formData.append('server_address', serverAddress);
        formData.append('server_2_address', server2Address);
        formData.append('api_username', apiUsername);
        formData.append('api_password', apiPassword);
        formData.append('api_base_url', apiBaseUrl);
        formData.append('api_2_base_url', api2BaseUrl);
        formData.append('dual_server_mode_enabled', dualServerModeEnabled ? '1' : '0');
        formData.append('test_connection', testConnection ? '1' : '0');

        const response = await fetch('api/update_stalker_settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showStalkerStatus(data.message, 'success');
            // Reload settings to get updated values
            loadStalkerSettings();
            // Exit edit mode after successful save
            document.querySelectorAll('.stalker-field').forEach(field => {
                if (field.type === 'checkbox') {
                    field.setAttribute('disabled', 'disabled');
                } else {
                    field.setAttribute('readonly', 'readonly');
                }
            });
            // Remove event listeners from server address fields
            document.getElementById('stalker-server-address').removeEventListener('input', updateStalkerBaseUrls);
            document.getElementById('stalker-server-2-address').removeEventListener('input', updateStalkerBaseUrls);
            document.getElementById('stalker-save-btn').style.display = 'none';
            document.getElementById('stalker-cancel-btn').style.display = 'none';
            document.getElementById('stalker-edit-btn').style.display = 'inline-flex';
            document.getElementById('stalker-test-checkbox-group').style.display = 'none';
        } else {
            showStalkerStatus(data.message || 'Failed to save settings', 'error');
        }
    } catch (error) {
        console.error('Error saving Stalker settings:', error);
        showStalkerStatus('Failed to save settings. Check console for details.', 'error');
    }
}

/**
 * Test Stalker Portal connection
 */
async function testStalkerConnection() {
    const serverAddress = document.getElementById('stalker-server-address').value.trim();
    const apiUsername = document.getElementById('stalker-api-username').value.trim();
    const apiPassword = document.getElementById('stalker-api-password').value;

    if (!serverAddress) {
        showStalkerStatus('Please enter server address first', 'error');
        return;
    }

    if (!apiUsername) {
        showStalkerStatus('Please enter API username first', 'error');
        return;
    }

    // Auto-generate base URL from server address
    const apiBaseUrl = serverAddress.replace(/\/+$/, '') + '/stalker_portal/api/';

    showStalkerStatus('Testing connection...', 'info');

    try {
        const formData = new FormData();
        formData.append('server_address', serverAddress);
        formData.append('server_2_address', serverAddress);
        formData.append('api_username', apiUsername);
        formData.append('api_password', apiPassword);
        formData.append('api_base_url', apiBaseUrl);
        formData.append('api_2_base_url', apiBaseUrl);
        formData.append('test_connection', '1');

        const response = await fetch('api/update_stalker_settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showStalkerStatus('Connection successful! Stalker Portal is reachable.', 'success');
        } else {
            showStalkerStatus(data.message || 'Connection failed', 'error');
        }
    } catch (error) {
        console.error('Error testing connection:', error);
        showStalkerStatus('Connection test failed. Check console for details.', 'error');
    }
}

/**
 * Show status message for Stalker settings
 */
function showStalkerStatus(message, type) {
    const statusDiv = document.getElementById('stalker-settings-status');
    statusDiv.style.display = 'block';
    statusDiv.className = '';

    let bgColor, textColor, borderColor;
    switch(type) {
        case 'success':
            bgColor = 'rgba(34, 197, 94, 0.1)';
            textColor = '#22c55e';
            borderColor = '#22c55e';
            break;
        case 'error':
            bgColor = 'rgba(239, 68, 68, 0.1)';
            textColor = '#ef4444';
            borderColor = '#ef4444';
            break;
        case 'info':
        default:
            bgColor = 'rgba(59, 130, 246, 0.1)';
            textColor = '#3b82f6';
            borderColor = '#3b82f6';
    }

    statusDiv.style.cssText = `
        display: block;
        padding: 12px 16px;
        border-radius: 6px;
        background: ${bgColor};
        color: ${textColor};
        border-left: 4px solid ${borderColor};
        font-size: 14px;
    `;
    statusDiv.textContent = message;

    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

/**
 * Generate API Base URL from server address
 */
function generateStalkerBaseUrl(serverAddress) {
    if (!serverAddress) return '';
    // Remove trailing slash and add /stalker_portal/api/
    return serverAddress.replace(/\/+$/, '') + '/stalker_portal/api/';
}

/**
 * Update base URLs when server addresses change
 */
function updateStalkerBaseUrls() {
    const primaryServer = document.getElementById('stalker-server-address').value.trim();
    const secondaryServer = document.getElementById('stalker-server-2-address').value.trim();

    // Update primary base URL
    document.getElementById('stalker-api-base-url').value = generateStalkerBaseUrl(primaryServer);

    // Update secondary base URL (use secondary server if set, otherwise use primary)
    document.getElementById('stalker-api-2-base-url').value = generateStalkerBaseUrl(secondaryServer || primaryServer);
}

/**
 * Enable edit mode for Stalker Portal settings
 */
function enableStalkerEdit() {
    // Remove readonly from all stalker fields (except base URLs which stay disabled)
    document.querySelectorAll('.stalker-field').forEach(field => {
        if (field.type === 'checkbox') {
            field.removeAttribute('disabled');
        } else {
            field.removeAttribute('readonly');
        }
        field.style.backgroundColor = ''; // Reset to default editable color
    });

    // Add event listeners to auto-update base URLs when server addresses change
    document.getElementById('stalker-server-address').addEventListener('input', updateStalkerBaseUrls);
    document.getElementById('stalker-server-2-address').addEventListener('input', updateStalkerBaseUrls);

    // Add event listener to update warning when server addresses change
    document.getElementById('stalker-server-address').addEventListener('input', () => {
        updateDualServerWarning(
            document.getElementById('stalker-server-address').value,
            document.getElementById('stalker-server-2-address').value
        );
    });
    document.getElementById('stalker-server-2-address').addEventListener('input', () => {
        updateDualServerWarning(
            document.getElementById('stalker-server-address').value,
            document.getElementById('stalker-server-2-address').value
        );
    });

    // Show Save and Cancel buttons, hide Edit button
    document.getElementById('stalker-save-btn').style.display = 'inline-flex';
    document.getElementById('stalker-cancel-btn').style.display = 'inline-flex';
    document.getElementById('stalker-edit-btn').style.display = 'none';

    // Show test connection checkbox
    document.getElementById('stalker-test-checkbox-group').style.display = 'block';

    // Clear any previous status message
    document.getElementById('stalker-settings-status').style.display = 'none';
}

/**
 * Cancel edit mode and restore original values
 */
function cancelStalkerEdit() {
    // Add readonly/disabled back to all stalker fields
    document.querySelectorAll('.stalker-field').forEach(field => {
        if (field.type === 'checkbox') {
            field.setAttribute('disabled', 'disabled');
        } else {
            field.setAttribute('readonly', 'readonly');
        }
    });

    // Remove event listeners from server address fields
    document.getElementById('stalker-server-address').removeEventListener('input', updateStalkerBaseUrls);
    document.getElementById('stalker-server-2-address').removeEventListener('input', updateStalkerBaseUrls);

    // Hide Save and Cancel buttons, show Edit button
    document.getElementById('stalker-save-btn').style.display = 'none';
    document.getElementById('stalker-cancel-btn').style.display = 'none';
    document.getElementById('stalker-edit-btn').style.display = 'inline-flex';

    // Hide test connection checkbox
    document.getElementById('stalker-test-checkbox-group').style.display = 'none';

    // Reload original values from server
    loadStalkerSettings();

    // Show cancel message
    showStalkerStatus('Edit cancelled. Original values restored.', 'info');
}

/**
 * Update dual server warning visibility based on server addresses
 * Only shows warning when dual mode is enabled AND servers are same
 */
function updateDualServerWarning(primaryServer, secondaryServer) {
    const warningElement = document.getElementById('dual-server-warning');
    const dualModeEnabled = document.getElementById('stalker-dual-server-mode')?.checked || false;
    if (!warningElement) return;

    // Normalize addresses for comparison (remove trailing slashes, lowercase)
    const normalizeUrl = (url) => (url || '').toLowerCase().replace(/\/+$/, '').trim();
    const primary = normalizeUrl(primaryServer);
    const secondary = normalizeUrl(secondaryServer);

    // Show warning if dual mode is enabled AND servers are the same
    const serversAreSame = !secondary || primary === secondary;

    if (dualModeEnabled && serversAreSame) {
        warningElement.style.display = 'block';
    } else {
        warningElement.style.display = 'none';
    }
}

/**
 * Show/hide secondary server fields based on dual server mode
 */
function updateSecondaryServerVisibility(dualModeEnabled) {
    const secondaryServerGroup = document.getElementById('secondary-server-group');
    const secondaryApiUrlGroup = document.getElementById('secondary-api-url-group');

    if (secondaryServerGroup) {
        secondaryServerGroup.style.display = dualModeEnabled ? 'block' : 'none';
    }
    if (secondaryApiUrlGroup) {
        secondaryApiUrlGroup.style.display = dualModeEnabled ? 'block' : 'none';
    }
}

/**
 * Enable edit mode for dual server mode toggle
 */
function enableDualServerModeEdit() {
    const checkbox = document.getElementById('stalker-dual-server-mode');
    const editBtn = document.getElementById('dual-mode-edit-btn');
    const saveBtn = document.getElementById('dual-mode-save-btn');
    const cancelBtn = document.getElementById('dual-mode-cancel-btn');

    // Store original value
    originalDualServerMode = checkbox.checked;

    // Enable checkbox
    checkbox.removeAttribute('disabled');

    // Show save/cancel, hide edit
    editBtn.style.display = 'none';
    saveBtn.style.display = 'inline-flex';
    cancelBtn.style.display = 'inline-flex';
}

/**
 * Cancel dual server mode edit
 */
function cancelDualServerModeEdit() {
    const checkbox = document.getElementById('stalker-dual-server-mode');
    const editBtn = document.getElementById('dual-mode-edit-btn');
    const saveBtn = document.getElementById('dual-mode-save-btn');
    const cancelBtn = document.getElementById('dual-mode-cancel-btn');
    const statusElement = document.getElementById('dual-server-status');

    // Restore original value
    checkbox.checked = originalDualServerMode;

    // Update visibility based on original value
    updateSecondaryServerVisibility(originalDualServerMode);
    updateDualServerWarning(
        document.getElementById('stalker-server-address')?.value || '',
        document.getElementById('stalker-server-2-address')?.value || ''
    );

    // Disable checkbox
    checkbox.setAttribute('disabled', 'disabled');

    // Hide save/cancel, show edit
    editBtn.style.display = 'inline-flex';
    saveBtn.style.display = 'none';
    cancelBtn.style.display = 'none';

    // Show cancel message
    if (statusElement) {
        statusElement.innerHTML = '<span style="color: var(--text-secondary);">Edit cancelled</span>';
        setTimeout(() => { statusElement.innerHTML = ''; }, 2000);
    }
}

/**
 * Save dual server mode setting
 */
async function saveDualServerMode() {
    const checkbox = document.getElementById('stalker-dual-server-mode');
    const editBtn = document.getElementById('dual-mode-edit-btn');
    const saveBtn = document.getElementById('dual-mode-save-btn');
    const cancelBtn = document.getElementById('dual-mode-cancel-btn');
    const statusElement = document.getElementById('dual-server-status');
    const enabled = checkbox.checked;

    // Show saving status
    if (statusElement) {
        statusElement.innerHTML = '<span style="color: var(--primary-color);">Saving...</span>';
    }

    try {
        const formData = new FormData();
        formData.append('server_address', document.getElementById('stalker-server-address').value);
        formData.append('server_2_address', document.getElementById('stalker-server-2-address').value);
        formData.append('api_username', document.getElementById('stalker-api-username').value);
        formData.append('api_password', document.getElementById('stalker-api-password').value);
        formData.append('api_base_url', document.getElementById('stalker-api-base-url').value);
        formData.append('api_2_base_url', document.getElementById('stalker-api-2-base-url').value);
        formData.append('dual_server_mode_enabled', enabled ? '1' : '0');
        formData.append('test_connection', '0'); // Don't test connection for toggle

        const response = await fetch('api/update_stalker_settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            // Update original value to new saved value
            originalDualServerMode = enabled;

            // Disable checkbox
            checkbox.setAttribute('disabled', 'disabled');

            // Hide save/cancel, show edit
            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';

            if (statusElement) {
                statusElement.innerHTML = '<span style="color: #28a745;">✓ ' + (enabled ? 'Dual server mode enabled' : 'Dual server mode disabled') + '</span>';
                setTimeout(() => { statusElement.innerHTML = ''; }, 3000);
            }
        } else {
            if (statusElement) {
                statusElement.innerHTML = '<span style="color: #dc3545;">✗ Failed to save: ' + (data.message || 'Unknown error') + '</span>';
            }
        }
    } catch (error) {
        console.error('Error saving dual server mode:', error);
        if (statusElement) {
            statusElement.innerHTML = '<span style="color: #dc3545;">✗ Failed to save setting</span>';
        }
    }
}

/**
 * Toggle dual server mode - called when checkbox changes during edit mode
 */
function toggleDualServerMode(enabled) {
    // Show/hide secondary server fields immediately (preview)
    updateSecondaryServerVisibility(enabled);

    // Update warning visibility
    updateDualServerWarning(
        document.getElementById('stalker-server-address')?.value || '',
        document.getElementById('stalker-server-2-address')?.value || ''
    );
}

// ========================================
// End of Stalker Portal Settings
// ========================================

// ========================================
// WebAuthn / Biometric Authentication
// ========================================

/**
 * Check if WebAuthn is supported by the browser
 */
function isWebAuthnSupported() {
    return window.PublicKeyCredential !== undefined &&
           typeof window.PublicKeyCredential === 'function';
}

/**
 * Check if platform authenticator (Face ID/Touch ID) is available
 */
async function isPlatformAuthenticatorAvailable() {
    if (!isWebAuthnSupported()) return false;
    try {
        return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch (e) {
        console.error('[WebAuthn] Error checking platform authenticator:', e);
        return false;
    }
}

/**
 * Base64URL decode helper
 */
function base64UrlDecode(base64url) {
    let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    while (base64.length % 4) {
        base64 += '=';
    }
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

/**
 * Base64URL encode helper
 */
function base64UrlEncode(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const base64 = btoa(binary);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/**
 * Initialize biometric settings section
 */
async function initBiometricSettings() {
    const biometricSection = document.getElementById('biometric-settings-section');
    const biometricLoading = document.getElementById('biometric-loading');
    const biometricNotSupported = document.getElementById('biometric-not-supported');
    const biometricContent = document.getElementById('biometric-content');

    if (!biometricSection) return;

    // Check if platform authenticator is available
    const hasAuthenticator = await isPlatformAuthenticatorAvailable();

    // Hide loading state
    if (biometricLoading) {
        biometricLoading.style.display = 'none';
    }

    if (hasAuthenticator) {
        biometricContent.style.display = 'block';
        biometricNotSupported.style.display = 'none';
        // Load existing credentials
        await loadBiometricCredentials();
    } else {
        biometricContent.style.display = 'none';
        biometricNotSupported.style.display = 'block';
    }

    console.log('[WebAuthn] Biometric settings initialized. Authenticator available:', hasAuthenticator);
}

/**
 * Load and display biometric credentials
 */
async function loadBiometricCredentials() {
    try {
        const response = await fetch('api/webauthn_manage.php');
        const data = await response.json();

        const noRegistered = document.getElementById('no-biometric-registered');
        const registered = document.getElementById('biometric-registered');
        const credentialsList = document.getElementById('biometric-credentials-list');

        if (data.error === 0 && data.count > 0) {
            noRegistered.style.display = 'none';
            registered.style.display = 'block';

            // Render credentials list
            let listHtml = '<div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">Registered devices:</div>';
            data.credentials.forEach(cred => {
                const createdDate = new Date(cred.created_at).toLocaleDateString();
                const lastUsed = cred.last_used ? new Date(cred.last_used).toLocaleDateString() : 'Never';
                listHtml += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--bg-tertiary); border-radius: 6px; margin-bottom: 8px;">
                        <div>
                            <strong>${cred.device_name || 'Unknown Device'}</strong>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                Added: ${createdDate} | Last used: ${lastUsed}
                            </div>
                        </div>
                        <button onclick="removeBiometricCredential(${cred.id})" class="btn-danger" style="padding: 6px 12px; font-size: 12px;">
                            Remove
                        </button>
                    </div>
                `;
            });
            credentialsList.innerHTML = listHtml;

            // Save username for biometric login
            if (typeof currentUser !== 'undefined' && currentUser && currentUser.username) {
                localStorage.setItem('biometric_username', currentUser.username);
            }
        } else {
            noRegistered.style.display = 'block';
            registered.style.display = 'none';
            credentialsList.innerHTML = '';
        }
    } catch (error) {
        console.error('[WebAuthn] Error loading credentials:', error);
    }
}

/**
 * Register a new biometric credential
 */
async function registerBiometric() {
    const registerBtn = document.getElementById('register-biometric-btn');
    if (registerBtn) {
        registerBtn.disabled = true;
        registerBtn.textContent = 'Registering...';
    }

    try {
        // Get registration options from server
        const optionsResponse = await fetch('api/webauthn_register.php');
        const options = await optionsResponse.json();

        if (options.error !== 0) {
            throw new Error(options.message || 'Failed to get registration options');
        }

        // Prepare credential creation options
        const publicKeyCredentialCreationOptions = {
            challenge: base64UrlDecode(options.challenge),
            rp: options.rp,
            user: {
                id: base64UrlDecode(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName
            },
            pubKeyCredParams: options.pubKeyCredParams,
            authenticatorSelection: options.authenticatorSelection,
            timeout: options.timeout,
            attestation: options.attestation
        };

        // Create credential
        const credential = await navigator.credentials.create({
            publicKey: publicKeyCredentialCreationOptions
        });

        // Determine device name
        let deviceName = 'Unknown Device';
        const ua = navigator.userAgent;
        if (/iPhone/.test(ua)) deviceName = 'iPhone';
        else if (/iPad/.test(ua)) deviceName = 'iPad';
        else if (/Mac/.test(ua)) deviceName = 'Mac';
        else if (/Android/.test(ua)) deviceName = 'Android';
        else if (/Windows/.test(ua)) deviceName = 'Windows';

        // Send credential to server
        const registerResponse = await fetch('api/webauthn_register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                credential_id: base64UrlEncode(credential.rawId),
                public_key: base64UrlEncode(credential.response.getPublicKey ? credential.response.getPublicKey() : credential.response.attestationObject),
                attestation_object: base64UrlEncode(credential.response.attestationObject),
                client_data_json: base64UrlEncode(credential.response.clientDataJSON),
                device_name: deviceName
            })
        });

        const result = await registerResponse.json();

        if (result.error === 0) {
            showAlert('Biometric login enabled successfully!', 'success');
            // Reload credentials list
            await loadBiometricCredentials();

            // Save username for biometric login
            if (typeof currentUser !== 'undefined' && currentUser && currentUser.username) {
                localStorage.setItem('biometric_username', currentUser.username);
            }
        } else {
            throw new Error(result.message || 'Registration failed');
        }

    } catch (error) {
        console.error('[WebAuthn] Registration error:', error);
        if (error.name === 'NotAllowedError') {
            showAlert('Biometric registration was cancelled', 'error');
        } else if (error.name === 'InvalidStateError') {
            showAlert('This device is already registered', 'error');
        } else {
            showAlert('Biometric registration failed: ' + error.message, 'error');
        }
    } finally {
        if (registerBtn) {
            registerBtn.disabled = false;
            registerBtn.textContent = '🔐 Enable Face ID / Touch ID';
        }
    }
}

/**
 * Remove a biometric credential
 */
async function removeBiometricCredential(credentialId) {
    if (!confirm('Are you sure you want to remove this biometric credential?')) {
        return;
    }

    try {
        const response = await fetch('api/webauthn_manage.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                credential_id: credentialId
            })
        });

        const result = await response.json();

        if (result.error === 0) {
            showAlert('Biometric credential removed', 'success');
            await loadBiometricCredentials();
        } else {
            showAlert(result.message || 'Failed to remove credential', 'error');
        }
    } catch (error) {
        console.error('[WebAuthn] Error removing credential:', error);
        showAlert('Failed to remove credential', 'error');
    }
}

// Initialize biometric settings when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Delay initialization to ensure currentUser is loaded
    setTimeout(initBiometricSettings, 1000);
    // Also initialize mobile biometric button
    setTimeout(initMobileBiometricButton, 1000);
});

/**
 * Initialize mobile biometric button visibility
 */
async function initMobileBiometricButton() {
    const mobileBiometricBtn = document.getElementById('mobile-biometric-btn');
    if (!mobileBiometricBtn) return;

    const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                  window.navigator.standalone === true ||
                  document.body.classList.contains('pwa-mode');

    const hasAuthenticator = await isPlatformAuthenticatorAvailable();

    // Show button in PWA mode or when authenticator is available
    if (isPWA || hasAuthenticator) {
        mobileBiometricBtn.style.display = 'flex';
    }

    console.log('[WebAuthn] Mobile biometric button initialized. PWA:', isPWA, 'Authenticator:', hasAuthenticator);
}

/**
 * Show mobile biometric settings modal
 */
async function showMobileBiometricSettings() {
    const modal = document.getElementById('mobile-biometric-modal');
    if (!modal) return;

    modal.style.display = 'flex';

    const hasAuthenticator = await isPlatformAuthenticatorAvailable();
    const notSupported = document.getElementById('mobile-biometric-not-supported');
    const content = document.getElementById('mobile-biometric-content');

    if (hasAuthenticator) {
        notSupported.style.display = 'none';
        content.style.display = 'block';
        await loadMobileBiometricCredentials();
    } else {
        notSupported.style.display = 'block';
        content.style.display = 'none';
    }
}

/**
 * Close mobile biometric settings modal
 */
function closeMobileBiometricSettings() {
    const modal = document.getElementById('mobile-biometric-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Show mobile auto-logout info modal (Super Admin only) (v1.11.32 - Read-only)
 */
async function showMobileAutoLogoutSettings() {
    const modal = document.getElementById('mobile-auto-logout-modal');
    const display = document.getElementById('mobile-auto-logout-display');
    if (!modal) return;

    // Show loading
    if (display) display.textContent = 'Loading...';
    modal.style.display = 'flex';

    // Load current setting (simple GET request)
    try {
        const response = await fetch('api/auto_logout_settings.php');
        const result = await response.json();

        if (result.error === 0 && display) {
            const timeout = result.auto_logout_timeout;
            if (timeout === 0) {
                display.textContent = 'Disabled';
                display.style.color = 'var(--text-secondary)';
            } else {
                display.textContent = timeout + (timeout === 1 ? ' minute' : ' minutes');
                display.style.color = 'var(--primary)';
            }
        }
    } catch (e) {
        console.error('[AutoLogout] Error loading settings:', e);
        if (display) {
            display.textContent = 'Error loading';
            display.style.color = 'var(--danger)';
        }
    }
}

/**
 * Close mobile auto-logout settings modal
 */
function closeMobileAutoLogoutSettings() {
    const modal = document.getElementById('mobile-auto-logout-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Show mobile push notification settings modal (v1.11.43)
 */
async function showMobilePushSettings() {
    const modal = document.getElementById('mobile-push-modal');
    if (!modal) return;

    modal.style.display = 'flex';

    const statusIcon = document.getElementById('mobile-push-status-icon');
    const statusText = document.getElementById('mobile-push-status-text');
    const enableBtn = document.getElementById('mobile-push-enable-btn');
    const disableBtn = document.getElementById('mobile-push-disable-btn');

    // Check if push notifications are supported
    if (!('PushManager' in window)) {
        statusIcon.textContent = '❌';
        statusText.textContent = 'Push notifications not supported in this browser';
        return;
    }

    if (!('serviceWorker' in navigator)) {
        statusIcon.textContent = '❌';
        statusText.textContent = 'Service Worker not supported';
        return;
    }

    try {
        // Get VAPID public key if not already loaded
        if (!vapidPublicKey) {
            const keyResponse = await fetch('api/get_vapid_key.php');
            const keyData = await keyResponse.json();
            vapidPublicKey = keyData.publicKey;
        }

        // Check current subscription status
        const registration = await navigator.serviceWorker.ready;
        pushSubscription = await registration.pushManager.getSubscription();

        if (pushSubscription) {
            statusIcon.textContent = '✅';
            statusText.textContent = 'Notifications enabled';
            enableBtn.style.display = 'none';
            disableBtn.style.display = 'block';
        } else {
            const permission = Notification.permission;
            if (permission === 'denied') {
                statusIcon.textContent = '🚫';
                statusText.textContent = 'Notifications blocked - check browser settings';
                enableBtn.style.display = 'none';
                disableBtn.style.display = 'none';
            } else {
                statusIcon.textContent = '🔔';
                statusText.textContent = 'Notifications not enabled';
                enableBtn.style.display = 'block';
                disableBtn.style.display = 'none';
            }
        }
    } catch (e) {
        console.error('[Push] Mobile init error:', e);
        statusIcon.textContent = '❌';
        // Show actual error message for debugging
        statusText.textContent = e.message || 'Error checking notification status';
    }
}

/**
 * Close mobile push notification settings modal (v1.11.43)
 */
function closeMobilePushSettings() {
    const modal = document.getElementById('mobile-push-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Save mobile auto-logout settings (v1.11.26)
 */
async function saveMobileAutoLogoutSettings() {
    const dropdown = document.getElementById('mobile-auto-logout-timeout');
    const statusDiv = document.getElementById('mobile-auto-logout-status');

    if (!dropdown) return;

    const timeoutValue = parseInt(dropdown.value);

    // Show loading state
    statusDiv.innerHTML = '<span style="color: var(--text-secondary);">Saving...</span>';
    statusDiv.style.display = 'block';
    statusDiv.style.background = 'transparent';

    try {
        const response = await fetch(window.location.origin + '/api/auto_logout_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ timeout: timeoutValue })
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const result = await response.json();

        if (result.error === 0) {
            // Update desktop dropdown if exists
            const desktopDropdown = document.getElementById('auto-logout-timeout');
            if (desktopDropdown) {
                desktopDropdown.value = timeoutValue;
            }

            // Show success message
            statusDiv.innerHTML = '<span style="color: #10b981;">✓ Settings saved successfully!</span>';
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(16, 185, 129, 0.1)';

            // Update the global timeout and restart the timer
            autoLogoutTimeoutMinutes = timeoutValue;
            initAutoLogout();

            // Close modal after a brief delay
            setTimeout(() => {
                closeMobileAutoLogoutSettings();
            }, 1500);
        } else {
            statusDiv.innerHTML = '<span style="color: #ef4444;">✗ ' + (result.message || 'Failed to save') + '</span>';
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
        }
    } catch (e) {
        console.error('[AutoLogout] Error saving settings:', e);
        statusDiv.innerHTML = '<span style="color: #ef4444;">✗ Network error</span>';
        statusDiv.style.display = 'block';
        statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
    }
}

/**
 * Load biometric credentials for mobile modal
 */
async function loadMobileBiometricCredentials() {
    try {
        const response = await fetch('api/webauthn_manage.php');
        const data = await response.json();

        const noRegistered = document.getElementById('mobile-no-biometric-registered');
        const registered = document.getElementById('mobile-biometric-registered');
        const credentialsList = document.getElementById('mobile-biometric-credentials-list');

        if (data.error === 0 && data.count > 0) {
            noRegistered.style.display = 'none';
            registered.style.display = 'block';

            // Render credentials list
            let listHtml = '';
            data.credentials.forEach(cred => {
                const createdDate = new Date(cred.created_at).toLocaleDateString();
                listHtml += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 8px;">
                        <div>
                            <strong>${cred.device_name || 'Unknown Device'}</strong>
                            <div style="font-size: 12px; color: var(--text-secondary);">Added: ${createdDate}</div>
                        </div>
                        <button onclick="removeBiometricCredentialMobile(${cred.id})" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 6px; font-size: 12px;">
                            Remove
                        </button>
                    </div>
                `;
            });
            credentialsList.innerHTML = listHtml;

            // Save username for biometric login
            if (typeof currentUser !== 'undefined' && currentUser && currentUser.username) {
                localStorage.setItem('biometric_username', currentUser.username);
            }
        } else {
            noRegistered.style.display = 'block';
            registered.style.display = 'none';
            credentialsList.innerHTML = '';
        }
    } catch (error) {
        console.error('[WebAuthn] Error loading mobile credentials:', error);
    }
}

/**
 * Remove biometric credential from mobile modal
 */
async function removeBiometricCredentialMobile(credentialId) {
    if (!confirm('Are you sure you want to remove this biometric credential?')) {
        return;
    }

    try {
        const response = await fetch('api/webauthn_manage.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                credential_id: credentialId
            })
        });

        const result = await response.json();

        if (result.error === 0) {
            showAlert('Biometric credential removed', 'success');
            await loadMobileBiometricCredentials();
            // Also reload desktop credentials if visible
            if (document.getElementById('biometric-credentials-list')) {
                await loadBiometricCredentials();
            }
        } else {
            showAlert(result.message || 'Failed to remove credential', 'error');
        }
    } catch (error) {
        console.error('[WebAuthn] Error removing credential:', error);
        showAlert('Failed to remove credential', 'error');
    }
}

// ========================================
// End of WebAuthn / Biometric Authentication
// ========================================


// ========================================
// Auto-Logout / Session Timeout
// ========================================

// IMMEDIATE deadline check on script load (v1.11.36)
// This runs synchronously before any async operations or event handlers
// Critical for iOS PWA where app may have been terminated
(function immediateDeadlineCheck() {
    try {
        const deadline = localStorage.getItem('autoLogoutDeadline');
        if (deadline && Date.now() >= parseInt(deadline, 10)) {
            console.log('[AutoLogout] IMMEDIATE: Deadline exceeded on script load');
            localStorage.removeItem('autoLogoutDeadline');
            window.location.href = 'index.html?expired=1';
        }
    } catch (e) {}
})();

let autoLogoutCheckInterval = null; // Interval that checks timeout every 10 seconds
let autoLogoutTimeoutMinutes = 5; // Default 5 minutes
let heartbeatInterval = null;
let lastServerPing = 0;

// Load auto-logout settings
async function loadAutoLogoutSettings() {
    try {
        const response = await fetch('api/auto_logout_settings.php');
        const result = await response.json();

        if (result.error === 0) {
            autoLogoutTimeoutMinutes = result.auto_logout_timeout;

            // Update dropdown if it exists (for super admin)
            const dropdown = document.getElementById('auto-logout-timeout');
            if (dropdown) {
                dropdown.value = autoLogoutTimeoutMinutes;
            }

            console.log('[AutoLogout] Loaded settings: timeout =', autoLogoutTimeoutMinutes, 'minutes');
        }
    } catch (e) {
        console.error('[AutoLogout] Error loading settings:', e);
    }
}

// Save auto-logout settings (super admin only)
async function saveAutoLogoutSettings() {
    const dropdown = document.getElementById('auto-logout-timeout');
    const btn = document.getElementById('save-auto-logout-btn');
    const statusDiv = document.getElementById('auto-logout-status');

    if (!dropdown) return;

    const timeout = parseInt(dropdown.value);

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const response = await fetch('api/auto_logout_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ timeout: timeout })
        });

        const result = await response.json();

        if (result.error === 0) {
            autoLogoutTimeoutMinutes = timeout;

            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(16, 185, 129, 0.1)';
            statusDiv.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            statusDiv.style.color = '#10b981';
            statusDiv.innerHTML = '✓ ' + result.message;

            // Restart the auto-logout system with new settings
            initAutoLogout();

            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        } else {
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            statusDiv.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            statusDiv.style.color = '#ef4444';
            statusDiv.innerHTML = '✗ ' + (result.message || 'Failed to save settings');
        }
    } catch (e) {
        console.error('[AutoLogout] Error saving settings:', e);
        statusDiv.style.display = 'block';
        statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
        statusDiv.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        statusDiv.style.color = '#ef4444';
        statusDiv.innerHTML = '✗ Error: ' + e.message;
    }

    btn.disabled = false;
    btn.textContent = '💾 Save';
}

// Send heartbeat to server (updates last_activity in session)
async function sendHeartbeat() {
    try {
        const response = await fetch('api/session_heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();

        if (result.expired) {
            // Session expired on server side
            console.log('[AutoLogout] Server reports session expired');
            performAutoLogout();
        }
    } catch (e) {
        console.error('[AutoLogout] Heartbeat error:', e);
    }
}

// ========================================
// iOS PWA Auto-Logout System (v1.11.40)
// ========================================
// PROBLEM: localStorage is UNRELIABLE on iOS PWA - it gets cleared!
// https://developer.apple.com/forums/thread/125041
//
// SOLUTION: Use SERVER-SIDE session verification only.
// The PHP session (via cookies) is reliable. We just need to check
// with the server when the app wakes up.

/**
 * Check session with server (v1.11.40)
 * This is the ONLY reliable way on iOS PWA
 */
async function checkServerSession() {
    if (autoLogoutTimeoutMinutes <= 0) return;

    console.log('[AutoLogout] Checking session with server...');

    try {
        const response = await fetch('api/session_heartbeat.php', {
            method: 'GET',
            credentials: 'same-origin' // Important: send cookies
        });

        const result = await response.json();
        console.log('[AutoLogout] Server response:', result);

        if (result.expired === true) {
            console.log('[AutoLogout] SERVER says session expired - logging out');
            performAutoLogout();
            return true;
        }

        if (result.error === 1) {
            console.log('[AutoLogout] Not logged in - redirecting');
            performAutoLogout();
            return true;
        }

        console.log('[AutoLogout] Session valid, time remaining:', result.time_remaining_seconds, 's');
        return false;

    } catch (e) {
        console.error('[AutoLogout] Error checking session:', e);
        return false;
    }
}

/**
 * Ping server to update activity (v1.11.40)
 */
async function pingServerActivity() {
    try {
        await fetch('api/session_heartbeat.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        });
    } catch (e) {
        console.error('[AutoLogout] Heartbeat error:', e);
    }
}

/**
 * Perform logout
 */
function performAutoLogout() {
    console.log('[AutoLogout] Redirecting to login...');
    sessionStorage.removeItem('freshLogin');
    window.location.href = 'index.html?expired=1';
}

/**
 * Initialize auto-logout (v1.11.40)
 */
async function initAutoLogout() {
    await loadAutoLogoutSettings();

    if (autoLogoutTimeoutMinutes <= 0) {
        console.log('[AutoLogout] Disabled');
        return;
    }

    console.log('[AutoLogout] v1.11.40 - Timeout:', autoLogoutTimeoutMinutes, 'min (SERVER-SIDE)');

    // Check session with server on init
    await checkServerSession();

    // Activity events - ping server on user interaction (throttled)
    const activityEvents = ['mousedown', 'keydown', 'touchstart', 'click'];

    let lastPing = Date.now();
    const pingInterval = 30000; // Ping server every 30 seconds of activity

    const onActivity = () => {
        const now = Date.now();
        if (now - lastPing > pingInterval) {
            lastPing = now;
            pingServerActivity();
        }
    };

    activityEvents.forEach(evt => {
        document.addEventListener(evt, onActivity, { passive: true });
    });

    console.log('[AutoLogout] v1.11.40 initialized - using SERVER-SIDE session only');
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(initAutoLogout, 500);
});

// ========================================
// iOS PWA Wake Detection (v1.11.40)
// When app returns from background, CHECK WITH SERVER
// ========================================

// pageshow - most reliable on iOS
window.addEventListener('pageshow', function(e) {
    console.log('[AutoLogout] >>> PAGESHOW (persisted:', e.persisted, ')');
    if (autoLogoutTimeoutMinutes > 0) {
        checkServerSession();
    }
}, false);

// visibilitychange - backup
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        console.log('[AutoLogout] >>> VISIBLE');
        if (autoLogoutTimeoutMinutes > 0) {
            checkServerSession();
        }
    }
}, false);

// focus - backup
window.addEventListener('focus', function() {
    console.log('[AutoLogout] >>> FOCUS');
    if (autoLogoutTimeoutMinutes > 0) {
        checkServerSession();
    }
}, false);

// ========================================
// End of Auto-Logout (v1.11.40)
// ========================================

// ========================================
// Push Notifications (v1.11.41)
// ========================================

/**
 * Initialize push notification UI
 */
async function initPushNotifications() {
    const section = document.getElementById('push-notification-section');
    // Section visibility is controlled by PHP - if not present, user is not admin
    if (!section) {
        console.log('[Push] Push notification section not found (user not admin)');
        return;
    }

    console.log('[Push] Initializing push notifications...');

    const statusIcon = document.getElementById('push-status-icon');
    const statusText = document.getElementById('push-status-text');
    const subscribeBtn = document.getElementById('push-subscribe-btn');

    // Check if push notifications are supported
    if (!('PushManager' in window)) {
        statusIcon.textContent = '❌';
        statusText.textContent = 'Push notifications not supported in this browser';
        return;
    }

    // Check if service worker is registered
    if (!('serviceWorker' in navigator)) {
        statusIcon.textContent = '❌';
        statusText.textContent = 'Service Worker not supported';
        return;
    }

    try {
        // Get VAPID public key
        const keyResponse = await fetch('api/get_vapid_key.php');
        const keyData = await keyResponse.json();
        vapidPublicKey = keyData.publicKey;

        // Check current subscription status
        const registration = await navigator.serviceWorker.ready;
        pushSubscription = await registration.pushManager.getSubscription();

        if (pushSubscription) {
            // v1.11.65: Sync subscription with current user on every login
            // This fixes the issue where admin logs out and reseller logs in
            // but still receives admin's notifications
            console.log('[Push] Syncing existing subscription with current user...');
            try {
                await fetch('api/push_subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pushSubscription.toJSON())
                });
                console.log('[Push] Subscription synced with current user');
            } catch (syncError) {
                console.warn('[Push] Failed to sync subscription:', syncError);
            }

            statusIcon.textContent = '✅';
            statusText.textContent = 'Notifications enabled';
            subscribeBtn.textContent = '🔕 Disable Notifications';
            subscribeBtn.style.background = 'var(--danger)';
            subscribeBtn.style.display = 'inline-block';
        } else {
            // Check permission status
            const permission = Notification.permission;

            if (permission === 'denied') {
                statusIcon.textContent = '🚫';
                statusText.textContent = 'Notifications blocked - check browser settings';
            } else {
                statusIcon.textContent = '🔔';
                statusText.textContent = 'Notifications not enabled';
                subscribeBtn.textContent = '🔔 Enable Notifications';
                subscribeBtn.style.background = '';
                subscribeBtn.style.display = 'inline-block';
            }
        }

    } catch (e) {
        console.error('[Push] Init error:', e);
        statusIcon.textContent = '❌';
        statusText.textContent = 'Error checking notification status';
    }
}

/**
 * Toggle push notifications
 */
async function togglePushNotifications() {
    const statusDiv = document.getElementById('push-notification-status');

    try {
        if (pushSubscription) {
            // Unsubscribe
            await unsubscribePush();
        } else {
            // Subscribe
            await subscribePush();
        }
    } catch (e) {
        console.error('[Push] Toggle error:', e);
        statusDiv.style.display = 'block';
        statusDiv.style.background = 'var(--danger-bg)';
        statusDiv.style.color = 'var(--danger)';
        statusDiv.textContent = 'Error: ' + e.message;
    }
}

/**
 * Subscribe to push notifications
 */
async function subscribePush() {
    const statusDiv = document.getElementById('push-notification-status');

    console.log('[Push] Starting subscription process...');

    // Request permission first (this MUST happen from user interaction on iOS)
    const permission = await Notification.requestPermission();
    console.log('[Push] Permission result:', permission);

    if (permission !== 'granted') {
        if (statusDiv) {
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'var(--warning-bg)';
            statusDiv.style.color = 'var(--warning)';
            statusDiv.textContent = 'Permission denied. Please enable notifications in browser settings.';
        }
        throw new Error('Permission denied');
    }

    // Get service worker registration
    console.log('[Push] Getting service worker registration...');
    const registration = await navigator.serviceWorker.ready;
    console.log('[Push] Service worker ready');

    // Convert VAPID key to Uint8Array
    const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

    // Subscribe
    console.log('[Push] Subscribing to push manager...');
    pushSubscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey
    });
    console.log('[Push] Subscription successful:', pushSubscription.endpoint.substring(0, 50) + '...');

    // Send subscription to server
    console.log('[Push] Sending subscription to server...');
    const response = await fetch('api/push_subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pushSubscription.toJSON())
    });

    const result = await response.json();
    console.log('[Push] Server response:', result);

    if (result.error) {
        throw new Error(result.message);
    }

    // Update UI (statusDiv may not exist if called from prompt modal)
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = 'var(--success-bg)';
        statusDiv.style.color = 'var(--success)';
        statusDiv.textContent = 'Notifications enabled successfully!';
    }

    console.log('[Push] Subscription complete!');

    // Refresh UI
    initPushNotifications();
}

/**
 * Unsubscribe from push notifications
 */
async function unsubscribePush() {
    const statusDiv = document.getElementById('push-notification-status');

    // Unsubscribe from browser
    await pushSubscription.unsubscribe();

    // Remove from server
    await fetch('api/push_subscribe.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: pushSubscription.endpoint })
    });

    pushSubscription = null;

    // Update UI
    statusDiv.style.display = 'block';
    statusDiv.style.background = 'var(--success-bg)';
    statusDiv.style.color = 'var(--success)';
    statusDiv.textContent = 'Notifications disabled successfully.';

    // Refresh UI
    initPushNotifications();
}

/**
 * Convert base64 URL to Uint8Array (for VAPID key)
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Initialize push notifications when dashboard loads
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(initPushNotifications, 1500);
});

// Push notification auto-prompt - v1.11.64
(function initPushPrompt() {
    let promptShown = false;

    function showPromptIfNeeded() {
        if (promptShown) return;
        promptShown = true;
        console.log('[Push Prompt] Checking push notification status...');
        checkPushNotificationPrompt();
    }

    // Run after DOM is ready + small delay for app initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('[Push Prompt] DOMContentLoaded fired');
            setTimeout(showPromptIfNeeded, 2500);
        });
    } else {
        // DOM already loaded
        console.log('[Push Prompt] DOM already ready');
        setTimeout(showPromptIfNeeded, 2500);
    }

    // Fallback: also try on window load
    window.addEventListener('load', () => {
        console.log('[Push Prompt] Window load fired');
        setTimeout(showPromptIfNeeded, 3000);
    });
})();

/**
 * Check if we should show the push notification prompt (v1.11.57)
 * SIMPLIFIED VERSION - Shows modal first, handles errors on button click
 *
 * Key changes for iOS PWA:
 * - Don't check PushManager/Notification at modal display time
 * - iOS doesn't expose these until after user interaction
 * - Just show the modal and let subscribePush() handle errors
 */
async function checkPushNotificationPrompt() {
    console.log('[Push Prompt] === Starting check ===');

    // Detect iOS (includes iPad with iPadOS 13+)
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Detect standalone/PWA mode
    const isStandalone = window.navigator.standalone === true ||  // iOS specific
                         window.matchMedia('(display-mode: standalone)').matches;

    console.log('[Push Prompt] Platform info:');
    console.log('  - iOS:', isIOS);
    console.log('  - Standalone:', isStandalone);
    console.log('  - navigator.standalone:', window.navigator.standalone);
    console.log('  - serviceWorker supported:', 'serviceWorker' in navigator);

    // Basic requirement: service worker support
    if (!('serviceWorker' in navigator)) {
        console.log('[Push Prompt] No service worker support - cannot show prompt');
        return;
    }

    // For non-iOS browsers: Check if push is supported before showing modal
    // For iOS: Skip these checks (APIs not available until user interaction)
    if (!isIOS) {
        if (!('PushManager' in window)) {
            console.log('[Push Prompt] PushManager not available (non-iOS)');
            return;
        }
        if (!('Notification' in window)) {
            console.log('[Push Prompt] Notification API not available (non-iOS)');
            return;
        }
        if (Notification.permission === 'denied') {
            console.log('[Push Prompt] Notification permission denied');
            return;
        }
    }

    // Check localStorage for "Maybe Later" state
    try {
        const promptState = localStorage.getItem('push_notification_prompt');
        if (promptState) {
            const state = JSON.parse(promptState);
            console.log('[Push Prompt] localStorage state:', state);

            // If already enabled, verify subscription exists
            if (state.enabled) {
                // Try to verify, but don't wait too long
                try {
                    const reg = await Promise.race([
                        navigator.serviceWorker.ready,
                        new Promise((_, r) => setTimeout(() => r('timeout'), 2000))
                    ]);
                    if (reg && reg.pushManager) {
                        const sub = await reg.pushManager.getSubscription();
                        if (sub) {
                            console.log('[Push Prompt] Already subscribed - not showing prompt');
                            return;
                        }
                    }
                } catch (e) {
                    console.log('[Push Prompt] Could not verify subscription:', e);
                }
                // Subscription not found, clear stale state
                console.log('[Push Prompt] Clearing stale enabled state');
                localStorage.removeItem('push_notification_prompt');
            }

            // If user clicked "Maybe Later" less than 7 days ago
            if (state.later) {
                const daysSince = (Date.now() - state.timestamp) / (1000 * 60 * 60 * 24);
                if (daysSince < 7) {
                    console.log('[Push Prompt] User clicked Later', daysSince.toFixed(1), 'days ago');
                    return;
                }
            }
        }
    } catch (e) {
        console.log('[Push Prompt] localStorage error:', e);
        // Continue anyway
    }

    // Show the modal
    console.log('[Push Prompt] >>> SHOWING MODAL <<<');
    showPushPromptModal();
}

/**
 * Show the push notification prompt modal
 */
function showPushPromptModal() {
    const modal = document.getElementById('push-prompt-modal');
    if (!modal) {
        console.log('[Push Prompt] Modal element not found');
        return;
    }

    modal.style.display = 'flex';

    // Handle "Enable" button
    const enableBtn = document.getElementById('push-prompt-enable');
    enableBtn.onclick = async () => {
        modal.style.display = 'none';

        // Try to subscribe FIRST, only mark as enabled if successful
        try {
            await subscribePush();
            // Only mark as enabled if subscription succeeded
            localStorage.setItem('push_notification_prompt', JSON.stringify({
                enabled: true,
                timestamp: Date.now()
            }));
            showAlert('Notifications enabled successfully!', 'success');
        } catch (e) {
            console.error('[Push Prompt] Subscribe error:', e);
            // Don't mark as enabled - allow prompt to show again
            showAlert('Could not enable notifications. You can try again in Settings.', 'warning');
        }
    };

    // Handle "Maybe Later" button
    const laterBtn = document.getElementById('push-prompt-later');
    laterBtn.onclick = () => {
        modal.style.display = 'none';

        // Store "later" state
        localStorage.setItem('push_notification_prompt', JSON.stringify({
            later: true,
            timestamp: Date.now()
        }));

        console.log('[Push Prompt] User clicked "Maybe Later"');
    };

    // Close modal when clicking outside
    modal.onclick = (e) => {
        if (e.target === modal) {
            laterBtn.click();
        }
    };
}

// ========================================
// End of Push Notifications (v1.11.50)
// ========================================

// ========================================
// Login History (v1.12.0)
// ========================================

let loginHistoryCurrentPage = 1;
let loginHistoryTotalPages = 1;
let adminLoginHistoryCurrentPage = 1;
let adminLoginHistoryTotalPages = 1;
let adminSelectedUserId = null;

/**
 * Parse user agent string to get a friendly device name
 */
function parseUserAgent(userAgent) {
    if (!userAgent) return 'Unknown Device';

    // Check for mobile devices first
    if (/iPhone/.test(userAgent)) {
        return 'iPhone';
    } else if (/iPad/.test(userAgent)) {
        return 'iPad';
    } else if (/Android/.test(userAgent)) {
        if (/Mobile/.test(userAgent)) {
            return 'Android Phone';
        }
        return 'Android Tablet';
    }

    // Check for desktop browsers
    let browser = 'Browser';
    let os = '';

    if (/Chrome/.test(userAgent) && !/Edg/.test(userAgent)) {
        browser = 'Chrome';
    } else if (/Safari/.test(userAgent) && !/Chrome/.test(userAgent)) {
        browser = 'Safari';
    } else if (/Firefox/.test(userAgent)) {
        browser = 'Firefox';
    } else if (/Edg/.test(userAgent)) {
        browser = 'Edge';
    }

    if (/Windows/.test(userAgent)) {
        os = 'Windows';
    } else if (/Macintosh/.test(userAgent)) {
        os = 'Mac';
    } else if (/Linux/.test(userAgent)) {
        os = 'Linux';
    }

    return os ? `${browser} on ${os}` : browser;
}

/**
 * Format login method for display
 */
function formatLoginMethod(method) {
    switch (method) {
        case 'password':
            return 'Password';
        case 'biometric':
            return 'Face ID / Touch ID';
        default:
            return method || 'Unknown';
    }
}

/**
 * Format date for display
 */
function formatLoginDate(dateStr) {
    if (!dateStr) return 'Unknown';
    const date = new Date(dateStr);
    return date.toLocaleString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });
}

/**
 * Load user's own login history
 */
async function loadLoginHistory(page = 1) {
    loginHistoryCurrentPage = page;

    const loading = document.getElementById('login-history-loading');
    const tableContainer = document.getElementById('login-history-table-container');
    const emptyState = document.getElementById('login-history-empty');
    const pagination = document.getElementById('login-history-pagination');
    const tbody = document.getElementById('login-history-body');

    // Show loading
    loading.style.display = 'block';
    tableContainer.style.display = 'none';
    emptyState.style.display = 'none';
    pagination.style.display = 'none';

    try {
        const response = await fetch(`api/get_login_history.php?page=${page}&per_page=10`);
        const data = await response.json();

        if (data.error) {
            throw new Error(data.message);
        }

        loading.style.display = 'none';

        if (!data.data || data.data.length === 0) {
            emptyState.style.display = 'block';
            return;
        }

        // Render table
        tbody.innerHTML = data.data.map(entry => `
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatLoginDate(entry.login_time)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; ${entry.status === 'success' ? 'background: rgba(16, 185, 129, 0.2); color: #10b981;' : 'background: rgba(239, 68, 68, 0.2); color: #ef4444;'}">
                        ${entry.status === 'success' ? 'Success' : 'Failed'}
                    </span>
                    ${entry.failure_reason ? `<div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">${entry.failure_reason}</div>` : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatLoginMethod(entry.login_method)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); font-family: monospace; font-size: 12px;">${entry.ip_address || 'Unknown'}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);" title="${entry.user_agent || ''}">${parseUserAgent(entry.user_agent)}</td>
            </tr>
        `).join('');

        tableContainer.style.display = 'block';

        // Pagination
        loginHistoryTotalPages = data.pagination.total_pages;

        if (loginHistoryTotalPages > 1) {
            pagination.style.display = 'flex';
            document.getElementById('login-history-page-info').textContent =
                `Page ${data.pagination.current_page} of ${loginHistoryTotalPages} (${data.pagination.total_records} total)`;

            document.getElementById('login-history-prev').disabled = page <= 1;
            document.getElementById('login-history-next').disabled = page >= loginHistoryTotalPages;
        }

    } catch (error) {
        console.error('Error loading login history:', error);
        loading.style.display = 'none';
        emptyState.textContent = 'Error loading login history: ' + error.message;
        emptyState.style.display = 'block';
    }
}

/**
 * Load users list for admin dropdown
 */
async function loadUsersForAdminDropdown() {
    const select = document.getElementById('admin-user-select');
    if (!select) return;

    try {
        const response = await fetch('api/get_resellers.php');
        const data = await response.json();

        if (data.error) {
            console.error('Error loading users:', data.message);
            return;
        }

        // Keep "All Users" as first option
        select.innerHTML = '<option value="all">All Users</option>';

        // Add all individual users
        if (data.resellers && Array.isArray(data.resellers)) {
            data.resellers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name ? `${user.name} (${user.username})` : user.username;
                if (user.super_user == 1) {
                    option.textContent += ' [Admin]';
                }
                select.appendChild(option);
            });
        }

    } catch (error) {
        console.error('Error loading users for dropdown:', error);
    }
}

/**
 * Load login history for a specific user or all users (Admin only)
 */
async function loadAdminLoginHistory(page = 1) {
    const select = document.getElementById('admin-user-select');
    const userId = select ? select.value : 'all';

    adminSelectedUserId = userId;
    adminLoginHistoryCurrentPage = page;

    const container = document.getElementById('admin-login-history-container');
    const userInfo = document.getElementById('admin-login-history-user-info');
    const loading = document.getElementById('admin-login-history-loading');
    const tableContainer = document.getElementById('admin-login-history-table-container');
    const emptyState = document.getElementById('admin-login-history-empty');
    const pagination = document.getElementById('admin-login-history-pagination');
    const tbody = document.getElementById('admin-login-history-body');
    const userCol = document.getElementById('admin-history-user-col');

    // Show container and loading
    container.style.display = 'block';
    loading.style.display = 'block';
    tableContainer.style.display = 'none';
    emptyState.style.display = 'none';
    pagination.style.display = 'none';

    try {
        const response = await fetch(`api/get_login_history.php?user_id=${userId}&page=${page}&per_page=10`);
        const data = await response.json();

        if (data.error) {
            throw new Error(data.message);
        }

        loading.style.display = 'none';

        const isViewingAll = data.view_all_users;

        // Show/hide user column based on view mode
        if (userCol) {
            userCol.style.display = isViewingAll ? '' : 'none';
        }

        // Show user info header
        if (isViewingAll) {
            userInfo.innerHTML = `<strong>Viewing:</strong> All Users Login History`;
            userInfo.style.display = 'block';
        } else if (data.target_user) {
            userInfo.innerHTML = `<strong>Viewing history for:</strong> ${data.target_user.name || data.target_user.username} (${data.target_user.username})`;
            userInfo.style.display = 'block';
        } else {
            userInfo.style.display = 'none';
        }

        if (!data.data || data.data.length === 0) {
            emptyState.style.display = 'block';
            return;
        }

        // Render table with User column for "All Users" view
        tbody.innerHTML = data.data.map(entry => `
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); ${isViewingAll ? '' : 'display: none;'}">
                    <strong>${entry.user_name || entry.username}</strong>
                    <div style="font-size: 11px; color: var(--text-secondary);">${entry.username}</div>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatLoginDate(entry.login_time)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; ${entry.status === 'success' ? 'background: rgba(16, 185, 129, 0.2); color: #10b981;' : 'background: rgba(239, 68, 68, 0.2); color: #ef4444;'}">
                        ${entry.status === 'success' ? 'Success' : 'Failed'}
                    </span>
                    ${entry.failure_reason ? `<div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">${entry.failure_reason}</div>` : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatLoginMethod(entry.login_method)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); font-family: monospace; font-size: 12px;">${entry.ip_address || 'Unknown'}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);" title="${entry.user_agent || ''}">${parseUserAgent(entry.user_agent)}</td>
            </tr>
        `).join('');

        tableContainer.style.display = 'block';

        // Pagination
        adminLoginHistoryTotalPages = data.pagination.total_pages;

        if (adminLoginHistoryTotalPages > 1) {
            pagination.style.display = 'flex';
            document.getElementById('admin-login-history-page-info').textContent =
                `Page ${data.pagination.current_page} of ${adminLoginHistoryTotalPages} (${data.pagination.total_records} total)`;

            document.getElementById('admin-login-history-prev').disabled = page <= 1;
            document.getElementById('admin-login-history-next').disabled = page >= adminLoginHistoryTotalPages;
        }

    } catch (error) {
        console.error('Error loading admin login history:', error);
        loading.style.display = 'none';
        emptyState.textContent = 'Error loading login history: ' + error.message;
        emptyState.style.display = 'block';
    }
}

/**
 * Initialize login history when settings tab is opened
 */
function initLoginHistory() {
    // Load own login history
    loadLoginHistory(1);

    // If super admin, show the admin section and load users
    const isSuperAdmin = currentUser && currentUser.super_user == 1;
    if (isSuperAdmin) {
        const adminSection = document.getElementById('admin-login-history-section');
        if (adminSection) {
            adminSection.style.display = 'block';
            loadUsersForAdminDropdown();
        }
    }
}

// ========================================
// End of Login History (v1.12.0)
// ========================================

// ========================================
// Audit Log (v1.12.0)
// Permanent audit trail - no delete capability
// ========================================

// Audit log pagination state
let auditLogCurrentPage = 1;
let auditLogTotalPages = 1;

/**
 * Format action type for display
 */
function formatAuditAction(action) {
    const actionLabels = {
        'create': '<span style="color: #22c55e; font-weight: 500;">Create</span>',
        'update': '<span style="color: #3b82f6; font-weight: 500;">Update</span>',
        'delete': '<span style="color: #ef4444; font-weight: 500;">Delete</span>',
        'login': '<span style="color: #8b5cf6; font-weight: 500;">Login</span>',
        'logout': '<span style="color: #6b7280; font-weight: 500;">Logout</span>',
        'send': '<span style="color: #f59e0b; font-weight: 500;">Send</span>',
        'export': '<span style="color: #06b6d4; font-weight: 500;">Export</span>',
        'view': '<span style="color: #64748b; font-weight: 500;">View</span>'
    };
    return actionLabels[action] || `<span style="font-weight: 500;">${action}</span>`;
}

/**
 * Format target type for display
 */
function formatAuditTarget(type, name, id) {
    const typeLabels = {
        'account': 'Account',
        'user': 'User',
        'reseller': 'Reseller',
        'settings': 'Settings',
        'permissions': 'Permissions',
        'sms': 'SMS',
        'stb_message': 'STB Message',
        'plan': 'Plan'
    };
    const label = typeLabels[type] || type;
    if (name) {
        return `<strong>${label}:</strong> ${name}`;
    }
    if (id) {
        return `<strong>${label}:</strong> #${id}`;
    }
    return `<strong>${label}</strong>`;
}

/**
 * Format audit date
 */
function formatAuditDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

/**
 * Load audit log with filters
 */
async function loadAuditLog(page = 1) {
    const container = document.getElementById('audit-log-container');
    const loading = document.getElementById('audit-log-loading');
    const tableContainer = document.getElementById('audit-log-table-container');
    const emptyMsg = document.getElementById('audit-log-empty');
    const pagination = document.getElementById('audit-log-pagination');
    const tbody = document.getElementById('audit-log-body');

    if (!container) return;

    // Show loading
    loading.style.display = 'block';
    tableContainer.style.display = 'none';
    emptyMsg.style.display = 'none';
    pagination.style.display = 'none';

    // Build query params
    const params = new URLSearchParams();
    params.append('page', page);
    params.append('per_page', 10);

    const userFilter = document.getElementById('audit-user-filter')?.value;
    const actionFilter = document.getElementById('audit-action-filter')?.value;
    const targetFilter = document.getElementById('audit-target-filter')?.value;
    const dateFrom = document.getElementById('audit-date-from')?.value;
    const dateTo = document.getElementById('audit-date-to')?.value;
    const search = document.getElementById('audit-search')?.value;

    if (userFilter && userFilter !== 'all') params.append('user_id', userFilter);
    if (actionFilter) params.append('action', actionFilter);
    if (targetFilter) params.append('target_type', targetFilter);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (search) params.append('search', search);

    try {
        const response = await fetch(`api/get_audit_log.php?${params.toString()}`);
        const result = await response.json();

        loading.style.display = 'none';

        if (result.error) {
            emptyMsg.textContent = result.message || 'Error loading audit log';
            emptyMsg.style.display = 'block';
            return;
        }

        // Populate filter dropdowns (first load only)
        if (result.filter_options) {
            populateAuditFilters(result.filter_options);
        }

        if (!result.data || result.data.length === 0) {
            emptyMsg.style.display = 'block';
            return;
        }

        // Build table rows
        tbody.innerHTML = '';
        result.data.forEach(log => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); white-space: nowrap;">${formatAuditDate(log.timestamp)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                    <div>${log.user_display_name || log.username}</div>
                    <div style="font-size: 11px; color: var(--text-secondary);">${log.user_type || ''}</div>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatAuditAction(log.action)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">${formatAuditTarget(log.target_type, log.target_name, log.target_id)}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="${log.details || ''}">${log.details || '-'}</td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); font-family: monospace; font-size: 12px;">${log.ip_address || '-'}</td>
            `;
            tbody.appendChild(row);
        });

        tableContainer.style.display = 'block';

        // Update pagination
        auditLogCurrentPage = result.pagination.current_page;
        auditLogTotalPages = result.pagination.total_pages;

        if (result.pagination.total_records > 0) {
            pagination.style.display = 'flex';
            document.getElementById('audit-log-page-info').textContent =
                `Page ${auditLogCurrentPage} of ${auditLogTotalPages} (${result.pagination.total_records} entries)`;
            document.getElementById('audit-log-prev').disabled = auditLogCurrentPage <= 1;
            document.getElementById('audit-log-next').disabled = auditLogCurrentPage >= auditLogTotalPages;
        }

    } catch (error) {
        console.error('Error loading audit log:', error);
        loading.style.display = 'none';
        emptyMsg.textContent = 'Error loading audit log: ' + error.message;
        emptyMsg.style.display = 'block';
    }
}

/**
 * Populate filter dropdowns with available options
 */
function populateAuditFilters(options) {
    // Populate users
    const userSelect = document.getElementById('audit-user-filter');
    if (userSelect && options.users && userSelect.options.length <= 1) {
        options.users.forEach(user => {
            const opt = document.createElement('option');
            opt.value = user.user_id;
            opt.textContent = user.name || user.username;
            userSelect.appendChild(opt);
        });
    }

    // Populate actions
    const actionSelect = document.getElementById('audit-action-filter');
    if (actionSelect && options.actions && actionSelect.options.length <= 1) {
        options.actions.forEach(action => {
            const opt = document.createElement('option');
            opt.value = action;
            opt.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            actionSelect.appendChild(opt);
        });
    }

    // Populate target types
    const targetSelect = document.getElementById('audit-target-filter');
    if (targetSelect && options.target_types && targetSelect.options.length <= 1) {
        options.target_types.forEach(type => {
            const opt = document.createElement('option');
            opt.value = type;
            opt.textContent = type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' ');
            targetSelect.appendChild(opt);
        });
    }
}

/**
 * Clear all audit log filters
 */
function clearAuditFilters() {
    document.getElementById('audit-user-filter').value = 'all';
    document.getElementById('audit-action-filter').value = '';
    document.getElementById('audit-target-filter').value = '';
    document.getElementById('audit-date-from').value = '';
    document.getElementById('audit-date-to').value = '';
    document.getElementById('audit-search').value = '';
    loadAuditLog(1);
}

/**
 * Initialize audit log section (super admin only)
 */
function initAuditLog() {
    const isSuperAdmin = currentUser && currentUser.super_user == 1;
    const auditSection = document.getElementById('audit-log-section');

    if (isSuperAdmin && auditSection) {
        auditSection.style.display = 'block';
        loadAuditLog(1);
    }
}

// ========================================
// End of Audit Log (v1.12.0)
// ========================================

// ========================================
// Accounting & Monthly Invoices (v1.15.0)
// ========================================

// Global variable to store current invoice data
let currentInvoiceData = null;

// Shamsi month names
const shamsiMonths = [
    { value: 1, name: 'فروردین', nameEn: 'Farvardin' },
    { value: 2, name: 'اردیبهشت', nameEn: 'Ordibehesht' },
    { value: 3, name: 'خرداد', nameEn: 'Khordad' },
    { value: 4, name: 'تیر', nameEn: 'Tir' },
    { value: 5, name: 'مرداد', nameEn: 'Mordad' },
    { value: 6, name: 'شهریور', nameEn: 'Shahrivar' },
    { value: 7, name: 'مهر', nameEn: 'Mehr' },
    { value: 8, name: 'آبان', nameEn: 'Aban' },
    { value: 9, name: 'آذر', nameEn: 'Azar' },
    { value: 10, name: 'دی', nameEn: 'Dey' },
    { value: 11, name: 'بهمن', nameEn: 'Bahman' },
    { value: 12, name: 'اسفند', nameEn: 'Esfand' }
];

// Gregorian month names
const gregorianMonths = [
    { value: 1, name: 'January' },
    { value: 2, name: 'February' },
    { value: 3, name: 'March' },
    { value: 4, name: 'April' },
    { value: 5, name: 'May' },
    { value: 6, name: 'June' },
    { value: 7, name: 'July' },
    { value: 8, name: 'August' },
    { value: 9, name: 'September' },
    { value: 10, name: 'October' },
    { value: 11, name: 'November' },
    { value: 12, name: 'December' }
];

/**
 * Gregorian to Jalali conversion
 */
function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    const gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    jy += Math.floor((days - 1) / 365);
    if (days > 365) days = (days - 1) % 365;
    const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    return [jy, jm, jd];
}

/**
 * Get current Shamsi date
 */
function getCurrentShamsiDate() {
    const now = new Date();
    return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

/**
 * Initialize accounting tab
 */
function initAccountingTab() {
    console.log('[Accounting] Initializing accounting tab');
    populateAccountingResellers();
    updateCalendarOptions();
}

/**
 * Populate reseller dropdown
 */
async function populateAccountingResellers() {
    const select = document.getElementById('accounting-reseller');
    if (!select) return;

    console.log('[Accounting] Populating resellers, currentUser:', currentUser);

    // Always try to load resellers from API first
    try {
        const response = await fetch('api/get_resellers.php');
        const data = await response.json();

        console.log('[Accounting] Resellers API response:', data);

        if (data.error === 0 && data.resellers && data.resellers.length > 0) {
            select.innerHTML = '<option value="">-- Select Reseller --</option>';
            data.resellers.forEach(reseller => {
                const opt = document.createElement('option');
                opt.value = reseller.id;
                opt.textContent = `${reseller.full_name || reseller.name || reseller.username} (${reseller.currency_id || 'GBP'})`;
                select.appendChild(opt);
            });
            select.disabled = false;
            console.log('[Accounting] Loaded', data.resellers.length, 'resellers');
        } else if (currentUser && currentUser.id) {
            // Fallback: If no resellers returned (regular reseller), show only self
            select.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = currentUser.id;
            opt.textContent = currentUser.name || currentUser.full_name || currentUser.username || 'My Account';
            opt.selected = true;
            select.appendChild(opt);
            select.disabled = true;
            console.log('[Accounting] Showing self only');
        }
    } catch (error) {
        console.error('[Accounting] Error loading resellers:', error);
        // Fallback on error
        if (currentUser && currentUser.id) {
            select.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = currentUser.id;
            opt.textContent = currentUser.name || currentUser.full_name || currentUser.username || 'My Account';
            opt.selected = true;
            select.appendChild(opt);
        }
    }
}

/**
 * Update calendar options (year and month dropdowns)
 */
function updateCalendarOptions() {
    const calendarType = document.getElementById('accounting-calendar').value;
    const yearSelect = document.getElementById('accounting-year');
    const monthSelect = document.getElementById('accounting-month');

    if (!yearSelect || !monthSelect) return;

    // Clear existing options
    yearSelect.innerHTML = '';
    monthSelect.innerHTML = '';

    if (calendarType === 'shamsi') {
        // Shamsi years (current year and 5 years back)
        const currentShamsi = getCurrentShamsiDate();
        const currentYear = currentShamsi[0];

        for (let y = currentYear; y >= currentYear - 5; y--) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            yearSelect.appendChild(opt);
        }

        // Shamsi months
        shamsiMonths.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.value;
            opt.textContent = `${m.name} (${m.nameEn})`;
            monthSelect.appendChild(opt);
        });

        // Set current month
        monthSelect.value = currentShamsi[1];
    } else {
        // Gregorian years
        const currentYear = new Date().getFullYear();

        for (let y = currentYear; y >= currentYear - 5; y--) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            yearSelect.appendChild(opt);
        }

        // Gregorian months
        gregorianMonths.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.value;
            opt.textContent = m.name;
            monthSelect.appendChild(opt);
        });

        // Set current month
        monthSelect.value = new Date().getMonth() + 1;
    }
}

/**
 * Load monthly invoice data
 */
async function loadMonthlyInvoice() {
    const resellerId = document.getElementById('accounting-reseller').value;
    const calendarType = document.getElementById('accounting-calendar').value;
    const year = document.getElementById('accounting-year').value;
    const month = document.getElementById('accounting-month').value;

    if (!resellerId) {
        // Show empty state
        document.getElementById('invoice-container').style.display = 'none';
        document.getElementById('invoice-empty-state').style.display = 'block';
        return;
    }

    try {
        const response = await fetch(`api/get_monthly_invoice.php?reseller_id=${resellerId}&calendar=${calendarType}&year=${year}&month=${month}`);
        const data = await response.json();

        if (data.error === 0 && data.invoice) {
            currentInvoiceData = data.invoice;
            displayInvoice(data.invoice);
        } else {
            showAlert(data.message || 'Failed to load invoice data', 'error');
        }
    } catch (error) {
        console.error('[Accounting] Error loading invoice:', error);
        showAlert('Failed to load invoice data', 'error');
    }
}

/**
 * Display invoice data
 */
function displayInvoice(invoice) {
    // Hide empty state, show invoice
    document.getElementById('invoice-empty-state').style.display = 'none';
    document.getElementById('invoice-container').style.display = 'block';

    // Update header
    document.getElementById('invoice-title-text').textContent = 'Monthly Invoice';
    document.getElementById('invoice-period').textContent = invoice.period.display_en;

    // Update reseller info
    document.getElementById('invoice-reseller-name').textContent = invoice.reseller.name;
    document.getElementById('invoice-reseller-username').textContent = invoice.reseller.username;
    document.getElementById('invoice-currency').textContent = `${invoice.reseller.currency} (${invoice.reseller.currency_symbol})`;

    // Update summary
    document.getElementById('invoice-new-accounts').textContent = invoice.summary.new_accounts;
    document.getElementById('invoice-renewals').textContent = invoice.summary.renewals;
    document.getElementById('invoice-total-transactions').textContent = invoice.summary.total_transactions;
    document.getElementById('invoice-total-sales').textContent = invoice.reseller.currency_symbol + invoice.summary.total_sales_formatted;

    // Update amount owed
    document.getElementById('invoice-amount-owed').textContent = invoice.reseller.currency_symbol + invoice.summary.amount_owed_formatted;

    // Update transactions table
    const tbody = document.getElementById('invoice-transactions-tbody');
    tbody.innerHTML = '';

    if (invoice.transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999">No transactions in this period</td></tr>';
    } else {
        invoice.transactions.forEach(trans => {
            const tr = document.createElement('tr');

            // Determine transaction type from description
            let description = trans.description || '-';
            const descLower = description.toLowerCase();
            let transType = '';
            let typeClass = '';

            if (descLower.includes('account renewal') || descLower.includes('renew')) {
                transType = 'Renewal';
                typeClass = 'active';
            } else if (descLower.includes('plan ') && descLower.includes('assigned')) {
                transType = 'New Account';
                typeClass = 'inactive';
            }

            // Clean up description - remove MAC address if present
            const macMatch = description.match(/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/);
            if (macMatch) {
                description = description.replace(macMatch[1], '').replace(/\s*,\s*,/g, ',').replace(/,\s*$/, '').replace(/\s+/g, ' ').trim();
            }

            tr.innerHTML = `
                <td>${trans.date_gregorian}</td>
                <td dir="rtl">${trans.date_shamsi}</td>
                <td><span class="badge ${typeClass}" style="font-size: 10px; padding: 4px 8px;">${transType}</span></td>
                <td><code style="font-size: 13px;">${trans.mac_address || '-'}</code></td>
                <td>${invoice.reseller.currency_symbol}${formatNumber(trans.amount, invoice.reseller.currency)}</td>
                <td>${description}</td>
            `;
            tbody.appendChild(tr);
        });
    }
}

/**
 * Format number based on currency
 */
function formatNumber(num, currency) {
    if (currency === 'IRR' || currency === 'IRT') {
        return Math.round(num).toLocaleString('en-US');
    }
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Export invoice to PDF
 */
function exportInvoicePDF() {
    if (!currentInvoiceData) {
        showAlert('No invoice data to export', 'error');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const invoice = currentInvoiceData;

    // Title
    doc.setFontSize(20);
    doc.text('Monthly Invoice', 105, 20, { align: 'center' });

    // Period
    doc.setFontSize(12);
    doc.text(invoice.period.display_en, 105, 30, { align: 'center' });

    // Reseller Info
    doc.setFontSize(10);
    doc.text(`Reseller: ${invoice.reseller.name}`, 20, 45);
    doc.text(`Username: ${invoice.reseller.username}`, 20, 52);
    doc.text(`Currency: ${invoice.reseller.currency}`, 20, 59);

    // Summary
    doc.setFontSize(12);
    doc.text('Summary', 20, 75);
    doc.setFontSize(10);
    doc.text(`New Accounts: ${invoice.summary.new_accounts}`, 20, 85);
    doc.text(`Renewals: ${invoice.summary.renewals}`, 20, 92);
    doc.text(`Total Transactions: ${invoice.summary.total_transactions}`, 20, 99);
    doc.text(`Total Sales: ${invoice.reseller.currency_symbol}${invoice.summary.total_sales_formatted}`, 20, 106);

    // Amount Owed
    doc.setFontSize(14);
    doc.setTextColor(220, 53, 69);
    doc.text(`Amount Owed to System: ${invoice.reseller.currency_symbol}${invoice.summary.amount_owed_formatted}`, 20, 120);
    doc.setTextColor(0, 0, 0);

    // Transaction table
    if (invoice.transactions.length > 0) {
        const tableData = invoice.transactions.map(trans => {
            // Determine type
            const descLower = (trans.description || '').toLowerCase();
            let transType = '';
            if (descLower.includes('account renewal') || descLower.includes('renew')) {
                transType = 'Renewal';
            } else if (descLower.includes('plan ') && descLower.includes('assigned')) {
                transType = 'New Account';
            }

            // Clean description
            let desc = trans.description || '-';
            const macMatch = desc.match(/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/);
            if (macMatch) {
                desc = desc.replace(macMatch[1], '').replace(/\s*,\s*,/g, ',').replace(/,\s*$/, '').replace(/\s+/g, ' ').trim();
            }

            return [
                trans.date_gregorian,
                trans.date_shamsi,
                transType,
                trans.mac_address || '-',
                `${invoice.reseller.currency_symbol}${formatNumber(trans.amount, invoice.reseller.currency)}`,
                desc
            ];
        });

        doc.autoTable({
            startY: 130,
            head: [['Date (Gregorian)', 'Date (Shamsi)', 'Type', 'MAC Address', 'Amount', 'Description']],
            body: tableData,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [102, 126, 234] }
        });
    }

    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 20, doc.internal.pageSize.height - 10);
        doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.width - 30, doc.internal.pageSize.height - 10);
    }

    // Download
    const filename = `invoice_${invoice.reseller.username}_${invoice.period.year}_${invoice.period.month}.pdf`;
    doc.save(filename);

    showAlert('PDF exported successfully', 'success');
}

/**
 * Export invoice to Excel
 */
function exportInvoiceExcel() {
    if (!currentInvoiceData) {
        showAlert('No invoice data to export', 'error');
        return;
    }

    const invoice = currentInvoiceData;

    // Create workbook
    const wb = XLSX.utils.book_new();

    // Summary sheet
    const summaryData = [
        ['Monthly Invoice'],
        [''],
        ['Period', invoice.period.display_en],
        ['Calendar', invoice.period.calendar === 'shamsi' ? 'Persian (Shamsi)' : 'Gregorian'],
        ['Start Date', invoice.period.start_date],
        ['End Date', invoice.period.end_date],
        [''],
        ['Reseller Information'],
        ['Name', invoice.reseller.name],
        ['Username', invoice.reseller.username],
        ['Currency', invoice.reseller.currency],
        [''],
        ['Summary'],
        ['New Accounts', invoice.summary.new_accounts],
        ['Renewals', invoice.summary.renewals],
        ['Total Transactions', invoice.summary.total_transactions],
        ['Total Sales', `${invoice.reseller.currency_symbol}${invoice.summary.total_sales_formatted}`],
        [''],
        ['Amount Owed to System', `${invoice.reseller.currency_symbol}${invoice.summary.amount_owed_formatted}`]
    ];

    const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, summaryWs, 'Summary');

    // Transactions sheet
    const transactionsData = [
        ['Date (Gregorian)', 'Date (Shamsi)', 'Type', 'MAC Address', 'Amount', 'Currency', 'Description']
    ];

    invoice.transactions.forEach(trans => {
        // Determine type
        const descLower = (trans.description || '').toLowerCase();
        let transType = '';
        if (descLower.includes('account renewal') || descLower.includes('renew')) {
            transType = 'Renewal';
        } else if (descLower.includes('plan ') && descLower.includes('assigned')) {
            transType = 'New Account';
        }

        // Clean description
        let desc = trans.description || '';
        const macMatch = desc.match(/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/);
        if (macMatch) {
            desc = desc.replace(macMatch[1], '').replace(/\s*,\s*,/g, ',').replace(/,\s*$/, '').replace(/\s+/g, ' ').trim();
        }

        transactionsData.push([
            trans.date_gregorian,
            trans.date_shamsi,
            transType,
            trans.mac_address || '-',
            trans.amount,
            invoice.reseller.currency,
            desc
        ]);
    });

    // Add totals row
    transactionsData.push([]);
    transactionsData.push(['', '', '', 'TOTAL', invoice.summary.total_sales, invoice.reseller.currency, '']);

    const transWs = XLSX.utils.aoa_to_sheet(transactionsData);
    XLSX.utils.book_append_sheet(wb, transWs, 'Transactions');

    // Download
    const filename = `invoice_${invoice.reseller.username}_${invoice.period.year}_${invoice.period.month}.xlsx`;
    XLSX.writeFile(wb, filename);

    showAlert('Excel exported successfully', 'success');
}

// Initialize accounting when switching to the tab
const originalSwitchTab = switchTab;
switchTab = function(tabName) {
    originalSwitchTab(tabName);

    if (tabName === 'accounting') {
        initAccountingTab();
    }
};

// ========================================
// End of Accounting & Monthly Invoices (v1.15.0)
// ========================================