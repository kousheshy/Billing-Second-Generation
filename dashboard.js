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
    const savedTheme = localStorage.getItem('theme') || 'light';
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

        // Hide balance for admin (super_user), show only for resellers
        if(result.user.super_user == 1) {
            document.getElementById('balance-display').style.display = 'none';
            document.querySelector('.stat-card:nth-child(2)').style.display = 'none';

            // Show sync section for admin only
            document.getElementById('sync-section').style.display = 'block';
        } else {
            document.getElementById('balance-display').textContent = getCurrencySymbol(result.user.currency_name) + formatBalance(result.user.balance, result.user.currency_name);

            // Hide admin-only tabs for resellers
            document.querySelectorAll('.tab').forEach(tab => {
                const tabText = tab.textContent.toLowerCase();
                if(tabText.includes('reseller') || tabText.includes('plan')) {
                    tab.style.display = 'none';
                }
            });

            // Hide admin-only stat cards for resellers
            document.querySelector('.stat-card:nth-child(3)').style.display = 'none'; // Total Resellers
            document.querySelector('.stat-card:nth-child(4)').style.display = 'none'; // Total Plans
        }

        document.getElementById('total-accounts').textContent = result.total_accounts;

        // Auto-sync accounts on login (for both admin and resellers)
        await autoSyncAccounts();

        // Load initial data based on user type
        loadAccounts();
        loadTransactions();
        loadPlans(); // Load plans for both admin and resellers (filtered on backend)

        if(result.user.super_user == 1) {
            loadResellers();
            // Auto-fetch tariffs from server for admin
            loadTariffs();
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

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');

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
        const response = await fetch('get_accounts.php');
        const result = await response.json();

        if(result.error == 0 && result.accounts) {
            // Store all accounts
            accountsPagination.allAccounts = result.accounts;
            accountsPagination.totalItems = result.accounts.length;

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
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
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
            // Only show delete button for admin users
            const deleteButton = currentUser && currentUser.super_user == 1
                ? `<button class="btn-sm btn-delete" onclick="deleteAccount('${account.username}')">Delete</button>`
                : '';

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
                        <span class="exp-date">—</span>
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

            tr.innerHTML = `
                <td>${account.username || ''}</td>
                <td>${account.full_name || ''}</td>
                <td>${account.mac || ''}</td>
                <td>${account.tariff_plan || ''}</td>
                <td>${expirationCell}</td>
                <td>${creationDate}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-sm btn-edit" onclick="editAccount('${account.username}')">Edit</button>
                        ${deleteButton}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
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

                tr.innerHTML = `
                    <td>${reseller.name || ''}</td>
                    <td>${reseller.username || ''}</td>
                    <td>${reseller.email || ''}</td>
                    <td>${getCurrencySymbol(reseller.currency_name)}${formatBalance(reseller.balance || 0, reseller.currency_name)}</td>
                    <td>${reseller.max_users || 'Unlimited'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-sm btn-edit" onclick="adjustCredit(${reseller.id}, '${reseller.name}', ${resellerBalance}, '${resellerCurrency}')">Adjust Credit</button>
                            <button class="btn-sm btn-edit" onclick="assignPlans(${reseller.id}, '${reseller.name}', '${reseller.plans || ''}')">Assign Plans</button>
                            <button class="btn-sm btn-delete" onclick="deleteReseller(${reseller.id})">Delete</button>
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
        const response = await fetch('get_plans.php');
        const result = await response.json();

        const tbody = document.getElementById('plans-tbody');
        const planSelect = document.getElementById('plan-select');
        const resellerPlansSelect = document.getElementById('reseller-plans-select');
        const assignPlansSelect = document.getElementById('assign-plans-select');

        if(result.error == 0 && result.plans && result.plans.length > 0) {
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

                tr.innerHTML = `
                    <td>${plan.external_id || ''}</td>
                    <td>${plan.name || ''}</td>
                    <td>${displayCurrency || ''}</td>
                    <td>${formattedPrice}</td>
                    <td>${plan.days || 0}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-sm btn-delete" onclick="deletePlan('${plan.external_id}', '${plan.currency_id}')">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);

                // Add to plan select for account creation
                const option = document.createElement('option');
                option.value = plan.external_id;
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
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#999">No plans found</td></tr>';
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

// Load Transactions
async function loadTransactions() {
    try {
        const response = await fetch('get_transactions.php');
        const result = await response.json();

        const tbody = document.getElementById('transactions-tbody');

        if(result.error == 0 && result.transactions && result.transactions.length > 0) {
            tbody.innerHTML = '';

            result.transactions.forEach(tx => {
                const tr = document.createElement('tr');
                const type = tx.type == 1 ? 'Credit' : 'Debit';
                const currencySymbol = getCurrencySymbol(tx.currency);
                const formattedAmount = formatBalance(tx.amount, tx.currency);

                tr.innerHTML = `
                    <td>${new Date(tx.timestamp * 1000).toLocaleDateString()}</td>
                    <td>${currencySymbol}${formattedAmount}</td>
                    <td>${tx.currency || ''}</td>
                    <td><span class="badge ${tx.type == 1 ? 'active' : 'inactive'}">${type}</span></td>
                    <td>${tx.details || ''}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">No transactions found</td></tr>';
        }
    } catch(error) {
        console.error('Error loading transactions:', error);
        showAlert('Error loading transactions', 'error');
    }
}

// Add Account
async function addAccount(e) {
    e.preventDefault();

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
    formData.append('is_admin', '0');
    formData.append('permissions', '1|1|1|1|1');

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
        document.getElementById('edit-phone').value = account.phone || '';
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
function assignPlans(resellerId, resellerName, currentPlans) {
    document.getElementById('assign-reseller-id').value = resellerId;
    document.getElementById('assign-reseller-name').value = resellerName;

    // Get currently assigned plans
    const plansArray = currentPlans ? currentPlans.split(',') : [];

    // Populate checkboxes
    const checkboxContainer = document.getElementById('assign-plans-checkboxes');
    checkboxContainer.innerHTML = '';

    if (availablePlans.length === 0) {
        checkboxContainer.innerHTML = '<p style="color: var(--text-tertiary); text-align: center; padding: 20px;">No plans available. Please create plans first.</p>';
    } else {
        availablePlans.forEach(plan => {
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    checkAuth();
});
