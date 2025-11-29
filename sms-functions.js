/**
 * SMS Messaging Functions for ShowBox Billing Panel
 * Handles SMS configuration, sending, and history management
 */

// Add click handler for telegram button via JavaScript (bypass onclick issue)
document.addEventListener('DOMContentLoaded', function() {
    const telegramBtn = document.getElementById('telegram-tab-btn');
    if (telegramBtn) {
        console.log('[DEBUG] Adding click listener to telegram button');
        telegramBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Direct implementation to bypass any issues with switchMessagingTab
            const stbContent = document.getElementById('stb-messages-content');
            const smsContent = document.getElementById('sms-messages-content');
            const mailContent = document.getElementById('mail-messages-content');
            const telegramContent = document.getElementById('telegram-messages-content');
            const tabs = document.querySelectorAll('.messaging-tab-btn');

            // Hide all content
            if (stbContent) stbContent.style.display = 'none';
            if (smsContent) smsContent.style.display = 'none';
            if (mailContent) mailContent.style.display = 'none';

            // Show telegram content
            if (telegramContent) {
                telegramContent.style.display = 'block';
            } else {
                alert('ERROR: telegram-messages-content NOT FOUND!');
                return;
            }

            // Update active tab
            tabs.forEach(btn => btn.classList.remove('active'));
            telegramBtn.classList.add('active');

            // Load telegram settings
            if (typeof loadTelegramSettings === 'function') {
                loadTelegramSettings();
            }

            localStorage.setItem('messagingSubTab', 'telegram');
        });
    } else {
        console.log('[DEBUG] telegram-tab-btn not found in DOMContentLoaded');
    }
});

// Global SMS state
let smsCurrentPage = 1;
let smsPageSize = 25;
let smsAllLogs = [];
let smsFilteredLogs = [];
let smsTemplates = [];
let smsAccountsWithPhone = [];

// Switch between STB, SMS, Mail, and Telegram messaging tabs
function switchMessagingTab(tab) {
    console.log('[DEBUG] switchMessagingTab called with:', tab);
    const stbContent = document.getElementById('stb-messages-content');
    const smsContent = document.getElementById('sms-messages-content');
    const mailContent = document.getElementById('mail-messages-content');
    const telegramContent = document.getElementById('telegram-messages-content');
    const tabs = document.querySelectorAll('.messaging-tab-btn');
    console.log('[DEBUG] telegramContent found:', !!telegramContent);

    // Hide all content
    if (stbContent) stbContent.style.display = 'none';
    if (smsContent) smsContent.style.display = 'none';
    if (mailContent) mailContent.style.display = 'none';
    if (telegramContent) telegramContent.style.display = 'none';

    // Remove active class from all tabs
    tabs.forEach(btn => btn.classList.remove('active'));

    if (tab === 'stb') {
        if (stbContent) stbContent.style.display = 'block';
        tabs[0].classList.add('active');
    } else if (tab === 'sms') {
        if (smsContent) smsContent.style.display = 'block';
        tabs[1].classList.add('active');

        // Load SMS settings and data when switching to SMS tab
        loadSMSSettings();
        loadAccountsWithPhone();
        loadSMSHistory();
    } else if (tab === 'mail') {
        if (mailContent) mailContent.style.display = 'block';
        const mailTabBtn = document.getElementById('mail-tab-btn');
        if (mailTabBtn) mailTabBtn.classList.add('active');

        // Load mail settings if function exists
        if (typeof loadMailSettings === 'function') {
            loadMailSettings();
        }
    } else if (tab === 'telegram') {
        if (telegramContent) {
            telegramContent.style.display = 'block';
        }
        const telegramTabBtn = document.getElementById('telegram-tab-btn');
        if (telegramTabBtn) telegramTabBtn.classList.add('active');

        // Load telegram settings
        if (typeof loadTelegramSettings === 'function') {
            loadTelegramSettings();
        }
    }

    // Save current messaging sub-tab to localStorage
    localStorage.setItem('messagingSubTab', tab);
}

// Switch between manual and accounts SMS modes
function switchSMSMode(mode) {
    const manualMode = document.getElementById('sms-manual-mode');
    const accountsMode = document.getElementById('sms-accounts-mode');
    const tabs = document.querySelectorAll('.sms-tab-btn');

    tabs.forEach(btn => btn.classList.remove('active'));

    if (mode === 'manual') {
        manualMode.style.display = 'block';
        accountsMode.style.display = 'none';
        tabs[0].classList.add('active');
    } else if (mode === 'accounts') {
        manualMode.style.display = 'none';
        accountsMode.style.display = 'block';
        tabs[1].classList.add('active');
    }
}

// Load SMS settings from server
async function loadSMSSettings() {
    try {
        const response = await fetch('api/get_sms_settings.php');
        const data = await response.json();

        if (data.error === 0) {
            const settings = data.settings;
            const templates = data.templates;
            const stats = data.stats;

            // Populate form fields
            document.getElementById('sms-api-token').value = settings.api_token || '';
            document.getElementById('sms-sender-number').value = settings.sender_number || '';
            document.getElementById('sms-base-url').value = settings.base_url || 'https://edge.ippanel.com/v1';
            document.getElementById('sms-auto-send-enabled').checked = settings.auto_send_enabled == 1;

            // Store templates
            smsTemplates = templates;

            // Populate template dropdown
            const templateSelect = document.getElementById('sms-template-select');
            templateSelect.innerHTML = '<option value="">-- Select a template or type custom message --</option>';
            templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.name;
                option.dataset.template = template.template;
                templateSelect.appendChild(option);
            });

            // Update statistics
            if (stats) {
                document.getElementById('sms-stats-total').textContent = stats.total_sent || 0;
                document.getElementById('sms-stats-successful').textContent = stats.successful || 0;
                document.getElementById('sms-stats-failed').textContent = stats.failed || 0;
                document.getElementById('sms-stats-pending').textContent = stats.pending || 0;
            }

            // Load templates list
            loadTemplatesList();
        }
    } catch (error) {
        console.error('Error loading SMS settings:', error);
    }
}

// Save SMS settings
async function saveSMSSettings() {
    const token = document.getElementById('sms-api-token').value.trim();
    const senderNumber = document.getElementById('sms-sender-number').value.trim();
    const baseUrl = document.getElementById('sms-base-url').value.trim();
    const autoSendEnabled = document.getElementById('sms-auto-send-enabled').checked ? 1 : 0;

    if (!token || !senderNumber) {
        showSMSStatus('Please fill in API Token and Sender Number', 'error', 'sms-config-status');
        return;
    }

    showSMSStatus('Saving SMS configuration...', 'info', 'sms-config-status');

    try {
        const formData = new FormData();
        formData.append('api_token', token);
        formData.append('sender_number', senderNumber);
        formData.append('base_url', baseUrl);
        formData.append('auto_send_enabled', autoSendEnabled);
        formData.append('enable_multistage_reminders', 1); // Always enabled
        formData.append('days_before_expiry', 7); // Not used, but kept for compatibility
        formData.append('expiry_template', ''); // Not used, but kept for compatibility

        const response = await fetch('api/update_sms_settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showSMSStatus('✓ SMS configuration saved successfully!', 'success', 'sms-config-status');
        } else {
            showSMSStatus('✗ Error: ' + data.message, 'error', 'sms-config-status');
        }
    } catch (error) {
        showSMSStatus('✗ Connection error: ' + error.message, 'error', 'sms-config-status');
    }
}

// Load accounts with phone numbers
async function loadAccountsWithPhone() {
    const listContainer = document.getElementById('sms-accounts-list');
    const countText = document.getElementById('sms-account-count-text');

    listContainer.innerHTML = '<p style="color: var(--text-tertiary); padding: 20px; text-align: center;">Loading accounts...</p>';
    countText.textContent = 'Loading accounts...';

    try {
        const response = await fetch('api/get_accounts.php');
        const data = await response.json();

        if (data.error === 0) {
            const accountsWithPhone = data.accounts.filter(acc => acc.phone_number && acc.phone_number.trim() !== '');
            smsAccountsWithPhone = accountsWithPhone;

            if (accountsWithPhone.length === 0) {
                listContainer.innerHTML = '<p style="color: var(--text-tertiary); padding: 20px; text-align: center;">No accounts with phone numbers found</p>';
                countText.textContent = 'No accounts found';
                return;
            }

            // Update count
            countText.textContent = `${accountsWithPhone.length} account${accountsWithPhone.length !== 1 ? 's' : ''} with phone numbers`;

            // Render accounts
            renderSMSAccounts(accountsWithPhone);
        }
    } catch (error) {
        listContainer.innerHTML = '<p style="color: var(--error); padding: 20px; text-align: center;">Error loading accounts</p>';
        countText.textContent = 'Error loading accounts';
    }
}

// Render SMS accounts list
function renderSMSAccounts(accounts) {
    const listContainer = document.getElementById('sms-accounts-list');
    listContainer.innerHTML = '';

    if (accounts.length === 0) {
        listContainer.innerHTML = '<p style="color: var(--text-tertiary); padding: 20px; text-align: center;">No matching accounts found</p>';
        return;
    }

    accounts.forEach(account => {
        const div = document.createElement('div');
        div.className = 'account-checkbox';
        div.setAttribute('data-account-name', account.full_name || '');
        div.setAttribute('data-account-phone', account.phone_number || '');
        div.setAttribute('data-account-mac', account.mac || '');

        div.innerHTML = `
            <label>
                <input type="checkbox" class="sms-account-checkbox" value="${account.id}" data-phone="${account.phone_number}">
                <div class="account-info">
                    <span class="account-name">${account.full_name || 'No Name'}</span>
                    <span class="account-details">
                        <span>📱 ${account.phone_number}</span>
                        <span>🖥️ ${account.mac}</span>
                        <span>📅 Expires: ${account.end_date || 'N/A'}</span>
                    </span>
                </div>
            </label>
        `;
        listContainer.appendChild(div);
    });
}

// Filter SMS accounts based on search input
function filterSMSAccounts() {
    const searchInput = document.getElementById('sms-account-search-input');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const countText = document.getElementById('sms-account-count-text');

    if (!searchTerm) {
        // Show all accounts if search is empty
        renderSMSAccounts(smsAccountsWithPhone);
        countText.textContent = `${smsAccountsWithPhone.length} account${smsAccountsWithPhone.length !== 1 ? 's' : ''} with phone numbers`;
        return;
    }

    // Filter accounts by name, phone, or MAC
    const filtered = smsAccountsWithPhone.filter(account => {
        const name = (account.full_name || '').toLowerCase();
        const phone = (account.phone_number || '').toLowerCase();
        const mac = (account.mac || '').toLowerCase();

        return name.includes(searchTerm) ||
               phone.includes(searchTerm) ||
               mac.includes(searchTerm);
    });

    renderSMSAccounts(filtered);
    countText.textContent = `${filtered.length} of ${smsAccountsWithPhone.length} account${filtered.length !== 1 ? 's' : ''} found`;
}

// Toggle select all SMS accounts (only visible/filtered accounts)
function toggleSelectAllSMS() {
    const selectAll = document.getElementById('select-all-sms');
    const checkboxes = document.querySelectorAll('.account-checkbox:not([style*="display: none"]) .sms-account-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// Load SMS template
function loadSMSTemplate() {
    const templateSelect = document.getElementById('sms-template-select');
    const messageTextarea = document.getElementById('sms-accounts-message');

    const selectedOption = templateSelect.options[templateSelect.selectedIndex];
    if (selectedOption.dataset.template) {
        messageTextarea.value = selectedOption.dataset.template;
        updateCharCount('sms-accounts-message', 'sms-accounts-char-count');
    }
}

// Send manual SMS to a single number
async function sendManualSMS() {
    const recipientNumber = document.getElementById('sms-recipient-number').value.trim();
    const message = document.getElementById('sms-manual-message').value.trim();

    if (!recipientNumber) {
        showSMSStatus('Please enter a recipient phone number', 'error', 'sms-send-status');
        return;
    }

    if (!message) {
        showSMSStatus('Please enter a message', 'error', 'sms-send-status');
        return;
    }

    showSMSStatus('📱 Sending SMS...', 'info', 'sms-send-status');

    try {
        const formData = new FormData();
        formData.append('send_type', 'manual');
        formData.append('recipient_number', recipientNumber);
        formData.append('message', message);

        const response = await fetch('api/send_sms.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showSMSStatus(`✓ ${data.message}`, 'success', 'sms-send-status');
            document.getElementById('sms-manual-message').value = '';
            document.getElementById('sms-recipient-number').value = '';
            updateCharCount('sms-manual-message', 'sms-manual-char-count');

            // Refresh history and stats
            setTimeout(() => {
                loadSMSHistory();
                loadSMSSettings();
            }, 1000);
        } else {
            showSMSStatus(`✗ ${data.message}`, 'error', 'sms-send-status');
        }
    } catch (error) {
        showSMSStatus('✗ Connection error: ' + error.message, 'error', 'sms-send-status');
    }
}

// Send SMS to selected accounts
async function sendAccountsSMS() {
    const checkboxes = document.querySelectorAll('.sms-account-checkbox:checked');
    const message = document.getElementById('sms-accounts-message').value.trim();

    if (checkboxes.length === 0) {
        showSMSStatus('Please select at least one account', 'error', 'sms-send-status');
        return;
    }

    if (!message) {
        showSMSStatus('Please enter a message', 'error', 'sms-send-status');
        return;
    }

    const accountIds = Array.from(checkboxes).map(cb => cb.value);

    showSMSStatus(`📱 Sending SMS to ${accountIds.length} account(s)...`, 'info', 'sms-send-status');

    try {
        const formData = new FormData();
        formData.append('send_type', 'accounts');
        formData.append('account_ids', JSON.stringify(accountIds));
        formData.append('message', message);

        const response = await fetch('api/send_sms.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showSMSStatus(`✓ ${data.message}`, 'success', 'sms-send-status');

            // Uncheck all checkboxes
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('select-all-sms').checked = false;
            document.getElementById('sms-accounts-message').value = '';
            updateCharCount('sms-accounts-message', 'sms-accounts-char-count');

            // Refresh history and stats
            setTimeout(() => {
                loadSMSHistory();
                loadSMSSettings();
            }, 1000);
        } else {
            showSMSStatus(`✗ ${data.message}`, 'error', 'sms-send-status');
        }
    } catch (error) {
        showSMSStatus('✗ Connection error: ' + error.message, 'error', 'sms-send-status');
    }
}

// Load SMS history
async function loadSMSHistory() {
    const dateInput = document.getElementById('sms-history-date');

    // Set today's date as default if not set
    if (!dateInput.value) {
        // Use local date instead of UTC to avoid timezone issues
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const localDate = `${year}-${month}-${day}`;
        dateInput.value = localDate;
    }

    const selectedDate = dateInput.value;

    console.log('Loading SMS history for date:', selectedDate);

    try {
        let url = `api/get_sms_logs.php?page=${smsCurrentPage}&limit=${smsPageSize}`;

        // Add date filter if a date is selected
        if (selectedDate) {
            url += `&date=${selectedDate}`;
        }

        console.log('Fetching from:', url);
        const response = await fetch(url);
        const data = await response.json();

        console.log('SMS History Response:', data);

        if (data.error === 0) {
            smsAllLogs = data.logs;
            smsFilteredLogs = [...smsAllLogs];

            console.log(`Loaded ${smsAllLogs.length} SMS logs`);

            filterSMSHistory();
            updateSMSHistoryStats();
        } else {
            console.error('Error from API:', data.message);
        }
    } catch (error) {
        console.error('Error loading SMS history:', error);
    }
}

// Filter SMS history
function filterSMSHistory() {
    const searchTerm = document.getElementById('sms-history-search').value.toLowerCase();
    const statusFilter = document.getElementById('sms-history-status-filter').value;
    const typeFilter = document.getElementById('sms-history-type-filter').value;

    smsFilteredLogs = smsAllLogs.filter(log => {
        const matchesSearch = !searchTerm ||
            (log.recipient_name && log.recipient_name.toLowerCase().includes(searchTerm)) ||
            (log.recipient_number && log.recipient_number.toLowerCase().includes(searchTerm)) ||
            (log.mac && log.mac.toLowerCase().includes(searchTerm));

        const matchesStatus = !statusFilter || log.status === statusFilter;
        const matchesType = !typeFilter || log.message_type === typeFilter;

        return matchesSearch && matchesStatus && matchesType;
    });

    renderSMSHistory();
    updateSMSHistoryStats();
}

// Render SMS history table
function renderSMSHistory() {
    const tbody = document.getElementById('sms-history-tbody');

    if (smsFilteredLogs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                    No SMS messages found
                </td>
            </tr>
        `;
        document.getElementById('sms-history-pagination').style.display = 'none';
        return;
    }

    const start = (smsCurrentPage - 1) * smsPageSize;
    const end = start + smsPageSize;
    const pageData = smsFilteredLogs.slice(start, end);

    tbody.innerHTML = '';
    pageData.forEach(log => {
        const row = document.createElement('tr');

        const statusBadge = log.status === 'sent'
            ? '<span class="status-badge status-active">✓ Sent</span>'
            : log.status === 'failed'
            ? '<span class="status-badge status-inactive">✗ Failed</span>'
            : '<span class="status-badge">⏳ Pending</span>';

        const typeLabel = log.message_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

        row.innerHTML = `
            <td>${formatDateTime(log.sent_at)}</td>
            <td>${log.recipient_name || 'Manual'}</td>
            <td>${log.recipient_number}</td>
            <td>${typeLabel}</td>
            <td>${statusBadge}</td>
            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" title="${log.message}" dir="auto">${log.message}</td>
            <td>${log.sent_by_name || log.sent_by_username || 'N/A'}</td>
        `;
        tbody.appendChild(row);
    });

    // Update pagination
    renderSMSHistoryPagination();
}

// Update SMS history statistics
function updateSMSHistoryStats() {
    const total = smsFilteredLogs.length;
    const sent = smsFilteredLogs.filter(log => log.status === 'sent').length;
    const failed = smsFilteredLogs.filter(log => log.status === 'failed').length;

    document.getElementById('sms-history-total-count').textContent = `${total} SMS`;
    document.getElementById('sms-history-sent-count').textContent = `${sent} sent`;
    document.getElementById('sms-history-failed-count').textContent = `${failed} failed`;
}

// Render SMS history pagination
function renderSMSHistoryPagination() {
    const pagination = document.getElementById('sms-history-pagination');
    const totalPages = Math.ceil(smsFilteredLogs.length / smsPageSize);

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    const start = (smsCurrentPage - 1) * smsPageSize + 1;
    const end = Math.min(smsCurrentPage * smsPageSize, smsFilteredLogs.length);

    document.getElementById('sms-history-page-info').textContent = `Showing ${start}-${end} of ${smsFilteredLogs.length}`;

    // Enable/disable buttons
    document.getElementById('sms-history-prev-btn').disabled = smsCurrentPage === 1;
    document.getElementById('sms-history-next-btn').disabled = smsCurrentPage === totalPages;
}

// Change SMS history page
function changeSMSHistoryPage(direction) {
    const totalPages = Math.ceil(smsFilteredLogs.length / smsPageSize);
    smsCurrentPage += direction;

    if (smsCurrentPage < 1) smsCurrentPage = 1;
    if (smsCurrentPage > totalPages) smsCurrentPage = totalPages;

    renderSMSHistory();
}

// Change SMS history page size
function changeSMSHistoryPageSize() {
    smsPageSize = parseInt(document.getElementById('sms-history-page-size').value);
    smsCurrentPage = 1;
    renderSMSHistory();
}

// Set SMS history to today
function setSMSHistoryToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('sms-history-date').value = today;
    loadSMSHistory();
}

// Show SMS status message
function showSMSStatus(message, type, elementId) {
    const statusDiv = document.getElementById(elementId);
    statusDiv.style.display = 'block';
    statusDiv.className = 'reminder-status';

    if (type === 'success') {
        statusDiv.classList.add('success');
    } else if (type === 'error') {
        statusDiv.classList.add('error');
    }

    statusDiv.textContent = message;

    // Hide after 5 seconds
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

// Update character count
function updateCharCount(textareaId, counterId) {
    const textarea = document.getElementById(textareaId);
    const counter = document.getElementById(counterId);

    if (textarea && counter) {
        counter.textContent = textarea.value.length;
    }
}

// Format date/time
function formatDateTime(datetime) {
    if (!datetime) return 'N/A';
    const date = new Date(datetime);
    return date.toLocaleString('en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Initialize character count listeners
document.addEventListener('DOMContentLoaded', function() {
    const manualMessage = document.getElementById('sms-manual-message');
    const accountsMessage = document.getElementById('sms-accounts-message');

    if (manualMessage) {
        manualMessage.addEventListener('input', () => {
            updateCharCount('sms-manual-message', 'sms-manual-char-count');
        });
    }

    if (accountsMessage) {
        accountsMessage.addEventListener('input', () => {
            updateCharCount('sms-accounts-message', 'sms-accounts-char-count');
        });
    }
});

// ============================================
// SMS TEMPLATE MANAGEMENT
// ============================================

let smsAllTemplates = [];

// Load and display all templates
function loadTemplatesList() {
    const listContainer = document.getElementById('sms-templates-list');
    
    if (!smsTemplates || smsTemplates.length === 0) {
        listContainer.innerHTML = '<p style="color: var(--text-tertiary); padding: 20px; text-align: center; grid-column: 1 / -1;">No templates found. Click "Add New Template" to create one.</p>';
        return;
    }

    listContainer.innerHTML = '';
    
    smsTemplates.forEach(template => {
        const card = document.createElement('div');
        card.className = 'template-card';
        card.innerHTML = `
            <div class="template-card-header">
                <h4 class="template-card-title">${template.name}</h4>
                <div class="template-card-actions">
                    <button class="btn-edit-template" onclick="editTemplate(${template.id})">Edit</button>
                    <button class="btn-delete-template" onclick="deleteTemplate(${template.id})">Delete</button>
                </div>
            </div>
            <div class="template-card-body">
                <div class="template-card-message" dir="auto">${template.template}</div>
            </div>
            <div class="template-card-footer">
                <span>${template.template.length} chars</span>
                ${template.description ? `<span class="template-system-badge">${template.description}</span>` : ''}
            </div>
        `;
        listContainer.appendChild(card);
    });
}

// Show add template modal
function showAddTemplateModal() {
    document.getElementById('template-modal-title').textContent = 'Add New SMS Template';
    document.getElementById('template-id').value = '';
    document.getElementById('template-name').value = '';
    document.getElementById('template-description').value = '';
    document.getElementById('template-message').value = '';
    updateTemplatePreview();
    document.getElementById('sms-template-modal').style.display = 'block';
}

// Edit template
function editTemplate(templateId) {
    const template = smsTemplates.find(t => t.id == templateId);
    
    if (!template) {
        alert('Template not found');
        return;
    }

    document.getElementById('template-modal-title').textContent = 'Edit SMS Template';
    document.getElementById('template-id').value = template.id;
    document.getElementById('template-name').value = template.name;
    document.getElementById('template-description').value = template.description || '';
    document.getElementById('template-message').value = template.template;
    updateTemplatePreview();
    document.getElementById('sms-template-modal').style.display = 'block';
}

// Close template modal
function closeTemplateModal() {
    document.getElementById('sms-template-modal').style.display = 'none';
}

// Update template preview with sample data
function updateTemplatePreview() {
    const message = document.getElementById('template-message').value;
    const charCount = document.getElementById('template-char-count');
    const preview = document.getElementById('template-preview');

    charCount.textContent = message.length;

    if (!message) {
        preview.textContent = 'Enter a message to see preview...';
        preview.style.color = 'var(--text-tertiary)';
        return;
    }

    // Replace variables with sample data
    const samplePreview = message
        .replace(/{name}/g, 'John Smith')
        .replace(/{mac}/g, '00:1A:2B:3C:4D:5E')
        .replace(/{expiry_date}/g, '2025-12-31')
        .replace(/{days}/g, '7');

    preview.textContent = samplePreview;
    preview.style.color = 'var(--text-primary)';
}

// Save template (create or update)
async function saveTemplate(event) {
    event.preventDefault();

    const templateId = document.getElementById('template-id').value;
    const name = document.getElementById('template-name').value.trim();
    const description = document.getElementById('template-description').value.trim();
    const template = document.getElementById('template-message').value.trim();

    if (!name || !template) {
        alert('Please fill in template name and message');
        return;
    }

    try {
        const formData = new FormData();
        if (templateId) formData.append('template_id', templateId);
        formData.append('name', name);
        formData.append('description', description);
        formData.append('template', template);

        const response = await fetch('api/save_sms_template.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            alert(data.message);
            closeTemplateModal();
            
            // Reload settings to refresh templates list
            await loadSMSSettings();
            loadTemplatesList();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Connection error: ' + error.message);
    }
}

// Delete template
async function deleteTemplate(templateId) {
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('template_id', templateId);

        const response = await fetch('api/delete_sms_template.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            alert(data.message);
            
            // Reload settings to refresh templates list
            await loadSMSSettings();
            loadTemplatesList();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Connection error: ' + error.message);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('sms-template-modal');
    if (event.target == modal) {
        closeTemplateModal();
    }
}

// Close all modals when pressing Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' || event.keyCode === 27) {
        // Close SMS template modal
        const smsTemplateModal = document.getElementById('sms-template-modal');
        if (smsTemplateModal && smsTemplateModal.style.display === 'block') {
            closeTemplateModal();
            return;
        }

        // Close any other modals that might be open
        const allModals = document.querySelectorAll('.modal');
        allModals.forEach(modal => {
            if (modal.style.display === 'block' || modal.classList.contains('show')) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        });
    }
});

