// Global variable to store user info
let currentUser = null;

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

        // Load initial data based on user type
        loadAccounts();
        loadTransactions();
        loadPlans(); // Load plans for both admin and resellers (filtered on backend)

        if(result.user.super_user == 1) {
            loadResellers();
        }

    } catch(error) {
        console.error('Auth check failed:', error);
        window.location.href = 'index.html';
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

// Format currency symbol
function getCurrencySymbol(currencyName) {
    if(currencyName === 'USD') return '$';
    if(currencyName === 'EUR') return '€';
    if(currencyName === 'IRT') return ''; // No symbol for Iranian Toman
    if(currencyName === 'GBP') return '£';
    return '£';
}

// Format balance with proper thousands separator
function formatBalance(amount, currencyName) {
    if(currencyName === 'IRT') {
        return Number(amount).toLocaleString('en-US');
    }
    return amount;
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

        const tbody = document.getElementById('accounts-tbody');

        if(result.error == 0 && result.accounts && result.accounts.length > 0) {
            tbody.innerHTML = '';
            result.accounts.forEach(account => {
                const tr = document.createElement('tr');
                // Only show delete button for admin users
                const deleteButton = currentUser && currentUser.super_user == 1
                    ? `<button class="btn-sm btn-delete" onclick="deleteAccount('${account.username}')">Delete</button>`
                    : '';

                tr.innerHTML = `
                    <td>${account.username || ''}</td>
                    <td>${account.mac || ''}</td>
                    <td>${account.email || ''}</td>
                    <td>${new Date(account.timestamp * 1000).toLocaleDateString()}</td>
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
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">No accounts found</td></tr>';
        }
    } catch(error) {
        console.error('Error loading accounts:', error);
        showAlert('Error loading accounts', 'error');
    }
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
                tr.innerHTML = `
                    <td>${reseller.name || ''}</td>
                    <td>${reseller.username || ''}</td>
                    <td>${reseller.email || ''}</td>
                    <td>${getCurrencySymbol(reseller.currency_name)}${formatBalance(reseller.balance || 0, reseller.currency_name)}</td>
                    <td>${reseller.max_users || 'Unlimited'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-sm btn-edit" onclick="adjustCredit(${reseller.id}, '${reseller.name}', ${reseller.balance}, '${reseller.currency_name}')">Adjust Credit</button>
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
            if(assignPlansSelect) assignPlansSelect.innerHTML = '';
            document.getElementById('total-plans').textContent = result.plans.length;

            result.plans.forEach(plan => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${plan.external_id || ''}</td>
                    <td>${plan.currency_id || ''}</td>
                    <td>${plan.price || 0}</td>
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
                option.textContent = `${plan.external_id} - ${plan.currency_id}${plan.price} (${plan.days} days)`;
                planSelect.appendChild(option);

                // Add to reseller plan assignment dropdowns with planID-currency format
                if(resellerPlansSelect) {
                    const resellerOption = document.createElement('option');
                    resellerOption.value = `${plan.external_id}-${plan.currency_id}`;
                    resellerOption.textContent = `${plan.external_id} - ${plan.currency_id}${plan.price} (${plan.days} days)`;
                    resellerPlansSelect.appendChild(resellerOption);
                }
                if(assignPlansSelect) {
                    const assignOption = document.createElement('option');
                    assignOption.value = `${plan.external_id}-${plan.currency_id}`;
                    assignOption.textContent = `${plan.external_id} - ${plan.currency_id}${plan.price} (${plan.days} days)`;
                    assignPlansSelect.appendChild(assignOption);
                }
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">No plans found</td></tr>';
        }
    } catch(error) {
        console.error('Error loading plans:', error);
        showAlert('Error loading plans', 'error');
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
                tr.innerHTML = `
                    <td>${new Date(tx.timestamp * 1000).toLocaleDateString()}</td>
                    <td>${tx.currency}${tx.amount}</td>
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

// Edit functions (placeholders)
function editAccount(username) {
    showAlert('Edit account feature - Coming soon!', 'success');
}

// Adjust Credit
function adjustCredit(resellerId, resellerName, currentBalance, currencyName) {
    document.getElementById('adjust-reseller-id').value = resellerId;
    document.getElementById('adjust-reseller-name').value = resellerName;
    document.getElementById('adjust-current-balance').value = getCurrencySymbol(currencyName) + formatBalance(currentBalance, currencyName);
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

    // Pre-select current plans
    const assignPlansSelect = document.getElementById('assign-plans-select');
    const plansArray = currentPlans ? currentPlans.split(',') : [];

    Array.from(assignPlansSelect.options).forEach(option => {
        option.selected = plansArray.includes(option.value);
    });

    openModal('assignPlansModal');
}

async function submitPlanAssignment(e) {
    e.preventDefault();

    const resellerId = document.getElementById('assign-reseller-id').value;
    const plansSelect = document.getElementById('assign-plans-select');
    const selectedPlans = Array.from(plansSelect.selectedOptions).map(opt => opt.value);
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
});
