/**
 * Mail Functions - UI Logic for Email System
 * Handles mail configuration, sending, templates, and history
 */

// Mail state variables
let mailSettings = null;
let mailTemplates = [];
let mailAccountsData = [];
let mailHistoryData = [];
let mailHistoryPage = 1;
let mailHistoryPageSize = 25;

/**
 * Initialize mail tab when switching to it
 */
function initializeMailTab() {
    loadMailSettings();
    loadAccountsWithEmail();
    setMailHistoryToday();
}

/**
 * Load mail settings from server
 */
async function loadMailSettings() {
    try {
        const response = await fetch('api/get_mail_settings.php');
        const data = await response.json();

        if (data.error === 0) {
            mailSettings = data.settings;
            mailTemplates = data.templates || [];

            // Populate form fields
            document.getElementById('mail-smtp-host').value = mailSettings.smtp_host || 'mail.showboxtv.tv';
            document.getElementById('mail-smtp-port').value = mailSettings.smtp_port || '587';
            document.getElementById('mail-smtp-username').value = mailSettings.smtp_username || '';
            document.getElementById('mail-from-email').value = mailSettings.from_email || '';
            document.getElementById('mail-from-name').value = mailSettings.from_name || 'ShowBox';

            // Password status
            const passwordStatus = document.getElementById('mail-password-status');
            if (mailSettings.smtp_password_set) {
                passwordStatus.textContent = 'Password is set (leave empty to keep current)';
                passwordStatus.style.color = 'var(--success)';
            } else {
                passwordStatus.textContent = 'No password set';
                passwordStatus.style.color = 'var(--warning)';
            }

            // Auto-send options
            document.getElementById('mail-auto-new-account').checked = mailSettings.auto_send_new_account == 1;
            document.getElementById('mail-auto-renewal').checked = mailSettings.auto_send_renewal == 1;
            document.getElementById('mail-auto-expiry').checked = mailSettings.auto_send_expiry == 1;
            document.getElementById('mail-notify-admin').checked = mailSettings.notify_admin == 1;
            document.getElementById('mail-notify-reseller').checked = mailSettings.notify_reseller == 1;

            // Load templates into dropdowns and grid
            populateMailTemplates();
            updateMailStats(data.stats);
        } else {
            showMailStatus('mail-config-status', 'Failed to load settings: ' + data.message, true);
        }
    } catch (error) {
        console.error('Error loading mail settings:', error);
        showMailStatus('mail-config-status', 'Error loading settings', true);
    }
}

/**
 * Save mail settings to server
 */
async function saveMailSettings() {
    const settings = {
        smtp_host: document.getElementById('mail-smtp-host').value.trim(),
        smtp_port: document.getElementById('mail-smtp-port').value,
        smtp_secure: document.getElementById('mail-smtp-port').value === '465' ? 'ssl' : 'tls',
        smtp_username: document.getElementById('mail-smtp-username').value.trim(),
        smtp_password: document.getElementById('mail-smtp-password').value,
        from_email: document.getElementById('mail-from-email').value.trim(),
        from_name: document.getElementById('mail-from-name').value.trim(),
        auto_send_new_account: document.getElementById('mail-auto-new-account').checked ? 1 : 0,
        auto_send_renewal: document.getElementById('mail-auto-renewal').checked ? 1 : 0,
        auto_send_expiry: document.getElementById('mail-auto-expiry').checked ? 1 : 0,
        notify_admin: document.getElementById('mail-notify-admin').checked ? 1 : 0,
        notify_reseller: document.getElementById('mail-notify-reseller').checked ? 1 : 0
    };

    // Validation
    if (!settings.smtp_host) {
        showMailStatus('mail-config-status', 'SMTP host is required', true);
        return;
    }
    if (!settings.smtp_username) {
        showMailStatus('mail-config-status', 'Email address is required', true);
        return;
    }

    try {
        const response = await fetch('api/update_mail_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        const data = await response.json();

        if (data.error === 0) {
            showMailStatus('mail-config-status', 'Settings saved successfully!', false);
            document.getElementById('mail-smtp-password').value = '';
            loadMailSettings(); // Reload to update status
        } else {
            showMailStatus('mail-config-status', 'Failed to save: ' + data.message, true);
        }
    } catch (error) {
        console.error('Error saving mail settings:', error);
        showMailStatus('mail-config-status', 'Error saving settings', true);
    }
}

/**
 * Test SMTP connection
 */
async function testMailConnection() {
    const settings = {
        smtp_host: document.getElementById('mail-smtp-host').value.trim(),
        smtp_port: document.getElementById('mail-smtp-port').value,
        smtp_secure: document.getElementById('mail-smtp-port').value === '465' ? 'ssl' : 'tls',
        smtp_username: document.getElementById('mail-smtp-username').value.trim(),
        smtp_password: document.getElementById('mail-smtp-password').value
    };

    if (!settings.smtp_username) {
        showMailStatus('mail-config-status', 'Email address is required for testing', true);
        return;
    }

    showMailStatus('mail-config-status', 'Testing connection...', false);

    try {
        const response = await fetch('api/test_mail_connection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        const data = await response.json();

        if (data.error === 0) {
            showMailStatus('mail-config-status', 'Connection successful! SMTP server is working.', false);
        } else {
            showMailStatus('mail-config-status', 'Connection failed: ' + data.message, true);
        }
    } catch (error) {
        console.error('Error testing connection:', error);
        showMailStatus('mail-config-status', 'Error testing connection', true);
    }
}

/**
 * Toggle password visibility
 */
function toggleMailPasswordVisibility() {
    const input = document.getElementById('mail-smtp-password');
    const icon = document.getElementById('mail-password-toggle-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
    } else {
        input.type = 'password';
        icon.textContent = '👁';
    }
}

/**
 * Populate mail templates in dropdowns and grid
 */
function populateMailTemplates() {
    // Update template dropdowns
    const manualSelect = document.getElementById('mail-manual-template');
    const accountsSelect = document.getElementById('mail-accounts-template');

    const optionsHtml = '<option value="">-- Select a template --</option>' +
        mailTemplates.map(t => `<option value="${t.id}">${t.name}</option>`).join('');

    manualSelect.innerHTML = optionsHtml;
    accountsSelect.innerHTML = optionsHtml;

    // Update templates grid
    const grid = document.getElementById('mail-templates-grid');

    if (mailTemplates.length === 0) {
        grid.innerHTML = '<p style="text-align: center; color: var(--text-tertiary); grid-column: 1/-1; padding: 40px;">No templates found. Click "Add Template" to create one.</p>';
        return;
    }

    grid.innerHTML = mailTemplates.map(t => `
        <div class="template-card" style="background: var(--bg-secondary); padding: 15px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                <h4 style="margin: 0; font-size: 14px; color: var(--text-primary);">${escapeHtml(t.name)}</h4>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 10px; background: ${t.is_active == 1 ? 'var(--success)' : 'var(--text-tertiary)'}; color: white;">
                    ${t.is_active == 1 ? 'Active' : 'Inactive'}
                </span>
            </div>
            <p style="font-size: 12px; color: var(--text-secondary); margin: 0 0 10px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${escapeHtml(t.subject || '')}
            </p>
            <p style="font-size: 11px; color: var(--text-tertiary); margin: 0 0 15px 0;">
                ${escapeHtml(t.description || 'No description')}
            </p>
            <div style="display: flex; gap: 8px;">
                <button class="btn-small" onclick="editMailTemplate(${t.id})">Edit</button>
                <button class="btn-small btn-danger" onclick="deleteMailTemplate(${t.id}, '${escapeHtml(t.name)}')">Delete</button>
            </div>
        </div>
    `).join('');
}

/**
 * Load template content into form
 */
async function loadMailTemplateContent(mode) {
    const templateId = document.getElementById(`mail-${mode}-template`).value;

    if (!templateId) return;

    try {
        const response = await fetch(`api/get_mail_template.php?id=${templateId}`);
        const data = await response.json();

        if (data.error === 0 && data.template) {
            document.getElementById(`mail-${mode}-subject`).value = data.template.subject || '';
            document.getElementById(`mail-${mode}-message`).value = data.template.body_html || '';
        }
    } catch (error) {
        console.error('Error loading template:', error);
    }
}

/**
 * Switch between manual and accounts send modes
 */
function switchMailSendMode(mode) {
    document.getElementById('mail-manual-mode').style.display = mode === 'manual' ? 'block' : 'none';
    document.getElementById('mail-accounts-mode').style.display = mode === 'accounts' ? 'block' : 'none';

    document.getElementById('mail-mode-manual-btn').classList.toggle('active', mode === 'manual');
    document.getElementById('mail-mode-accounts-btn').classList.toggle('active', mode === 'accounts');

    if (mode === 'accounts') {
        loadAccountsWithEmail();
    }
}

/**
 * Load accounts that have email addresses
 */
async function loadAccountsWithEmail() {
    try {
        const response = await fetch('api/get_accounts.php');
        const data = await response.json();

        if (data.error === 0) {
            // Filter accounts with email
            mailAccountsData = (data.accounts || []).filter(a => a.email && a.email.trim() !== '');
            renderMailAccountsList();
        }
    } catch (error) {
        console.error('Error loading accounts:', error);
    }
}

/**
 * Render accounts list for email sending
 */
function renderMailAccountsList() {
    const container = document.getElementById('mail-accounts-list');
    const searchTerm = (document.getElementById('mail-accounts-search')?.value || '').toLowerCase();

    const filtered = mailAccountsData.filter(a => {
        const name = (a.full_name || '').toLowerCase();
        const email = (a.email || '').toLowerCase();
        const mac = (a.mac || '').toLowerCase();
        return name.includes(searchTerm) || email.includes(searchTerm) || mac.includes(searchTerm);
    });

    if (filtered.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No accounts with email found</p>';
        return;
    }

    container.innerHTML = filtered.map(a => `
        <label class="account-item" style="display: flex; align-items: center; padding: 8px; border-bottom: 1px solid var(--border-color); cursor: pointer;">
            <input type="checkbox" class="mail-account-checkbox" value="${a.id}" data-email="${escapeHtml(a.email)}" onchange="updateMailSelectedCount()">
            <span style="margin-left: 10px; flex: 1;">
                <strong>${escapeHtml(a.full_name || 'N/A')}</strong>
                <br>
                <small style="color: var(--text-secondary);">${escapeHtml(a.email)} | ${escapeHtml(a.mac)}</small>
            </span>
        </label>
    `).join('');

    updateMailSelectedCount();
}

/**
 * Filter accounts in the list
 */
function filterMailAccounts() {
    renderMailAccountsList();
}

/**
 * Toggle all mail account checkboxes
 */
function toggleAllMailAccounts() {
    const selectAll = document.getElementById('mail-select-all-accounts').checked;
    document.querySelectorAll('.mail-account-checkbox').forEach(cb => {
        cb.checked = selectAll;
    });
    updateMailSelectedCount();
}

/**
 * Update selected accounts count
 */
function updateMailSelectedCount() {
    const count = document.querySelectorAll('.mail-account-checkbox:checked').length;
    document.getElementById('mail-selected-count').textContent = count;
}

/**
 * Send manual email
 */
async function sendManualMail() {
    const email = document.getElementById('mail-manual-email').value.trim();
    const name = document.getElementById('mail-manual-name').value.trim();
    const subject = document.getElementById('mail-manual-subject').value.trim();
    const message = document.getElementById('mail-manual-message').value.trim();

    if (!email) {
        showMailStatus('mail-send-status', 'Recipient email is required', true);
        return;
    }
    if (!subject) {
        showMailStatus('mail-send-status', 'Subject is required', true);
        return;
    }
    if (!message) {
        showMailStatus('mail-send-status', 'Message is required', true);
        return;
    }

    showMailStatus('mail-send-status', 'Sending email...', false);

    try {
        const response = await fetch('api/send_mail.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mode: 'manual',
                email: email,
                recipient_name: name,
                subject: subject,
                message: message
            })
        });
        const data = await response.json();

        if (data.error === 0) {
            showMailStatus('mail-send-status', `Email sent successfully!`, false);
            // Clear form
            document.getElementById('mail-manual-email').value = '';
            document.getElementById('mail-manual-name').value = '';
            document.getElementById('mail-manual-subject').value = '';
            document.getElementById('mail-manual-message').value = '';
            document.getElementById('mail-manual-template').value = '';
            // Refresh history
            loadMailHistory();
            loadMailSettings(); // Update stats
        } else {
            showMailStatus('mail-send-status', 'Failed: ' + data.message, true);
        }
    } catch (error) {
        console.error('Error sending email:', error);
        showMailStatus('mail-send-status', 'Error sending email', true);
    }
}

/**
 * Send email to selected accounts
 */
async function sendMailToAccounts() {
    const checkboxes = document.querySelectorAll('.mail-account-checkbox:checked');
    const accountIds = Array.from(checkboxes).map(cb => cb.value);

    if (accountIds.length === 0) {
        showMailStatus('mail-send-status', 'Please select at least one account', true);
        return;
    }

    const subject = document.getElementById('mail-accounts-subject').value.trim();
    const message = document.getElementById('mail-accounts-message').value.trim();

    if (!subject) {
        showMailStatus('mail-send-status', 'Subject is required', true);
        return;
    }
    if (!message) {
        showMailStatus('mail-send-status', 'Message is required', true);
        return;
    }

    showMailStatus('mail-send-status', `Sending to ${accountIds.length} recipients...`, false);

    try {
        const response = await fetch('api/send_mail.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mode: 'accounts',
                account_ids: accountIds,
                subject: subject,
                message: message
            })
        });
        const data = await response.json();

        if (data.error === 0) {
            showMailStatus('mail-send-status', `Sent: ${data.sent_count}, Failed: ${data.failed_count}`, data.failed_count > 0);
            // Clear selection
            document.querySelectorAll('.mail-account-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('mail-select-all-accounts').checked = false;
            updateMailSelectedCount();
            // Refresh history
            loadMailHistory();
            loadMailSettings();
        } else {
            showMailStatus('mail-send-status', 'Failed: ' + data.message, true);
        }
    } catch (error) {
        console.error('Error sending emails:', error);
        showMailStatus('mail-send-status', 'Error sending emails', true);
    }
}

/**
 * Open template modal for adding
 */
function openMailTemplateModal() {
    document.getElementById('mail-template-modal-title').textContent = 'Add Email Template';
    document.getElementById('mail-template-id').value = '';
    document.getElementById('mail-template-name').value = '';
    document.getElementById('mail-template-subject').value = '';
    document.getElementById('mail-template-body').value = '';
    document.getElementById('mail-template-description').value = '';
    document.getElementById('mail-template-active').checked = true;
    openModal('mailTemplateModal');
}

/**
 * Edit existing template
 */
async function editMailTemplate(templateId) {
    try {
        const response = await fetch(`api/get_mail_template.php?id=${templateId}`);
        const data = await response.json();

        if (data.error === 0 && data.template) {
            document.getElementById('mail-template-modal-title').textContent = 'Edit Email Template';
            document.getElementById('mail-template-id').value = data.template.id;
            document.getElementById('mail-template-name').value = data.template.name || '';
            document.getElementById('mail-template-subject').value = data.template.subject || '';
            document.getElementById('mail-template-body').value = data.template.body_html || '';
            document.getElementById('mail-template-description').value = data.template.description || '';
            document.getElementById('mail-template-active').checked = data.template.is_active == 1;
            openModal('mailTemplateModal');
        } else {
            alert('Failed to load template');
        }
    } catch (error) {
        console.error('Error loading template:', error);
        alert('Error loading template');
    }
}

/**
 * Save template (create or update)
 */
async function saveMailTemplate(event) {
    event.preventDefault();

    const templateData = {
        template_id: document.getElementById('mail-template-id').value || null,
        name: document.getElementById('mail-template-name').value.trim(),
        subject: document.getElementById('mail-template-subject').value.trim(),
        body_html: document.getElementById('mail-template-body').value,
        description: document.getElementById('mail-template-description').value.trim(),
        is_active: document.getElementById('mail-template-active').checked ? 1 : 0
    };

    try {
        const response = await fetch('api/save_mail_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(templateData)
        });
        const data = await response.json();

        if (data.error === 0) {
            closeModal('mailTemplateModal');
            loadMailSettings(); // Refresh templates
        } else {
            alert('Failed to save template: ' + data.message);
        }
    } catch (error) {
        console.error('Error saving template:', error);
        alert('Error saving template');
    }
}

/**
 * Delete template
 */
async function deleteMailTemplate(templateId, templateName) {
    if (!confirm(`Are you sure you want to delete "${templateName}"?`)) {
        return;
    }

    try {
        const response = await fetch('api/delete_mail_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: templateId })
        });
        const data = await response.json();

        if (data.error === 0) {
            loadMailSettings(); // Refresh templates
        } else {
            alert('Failed to delete template: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting template:', error);
        alert('Error deleting template');
    }
}

/**
 * Set history date to today
 */
function setMailHistoryToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('mail-history-date').value = today;
    loadMailHistory();
}

/**
 * Change history date by offset
 */
function changeMailHistoryDate(offset) {
    const dateInput = document.getElementById('mail-history-date');
    const currentDate = new Date(dateInput.value || new Date());
    currentDate.setDate(currentDate.getDate() + offset);
    dateInput.value = currentDate.toISOString().split('T')[0];
    loadMailHistory();
}

/**
 * Load mail history
 */
async function loadMailHistory() {
    const date = document.getElementById('mail-history-date').value;
    const status = document.getElementById('mail-history-status-filter').value;
    const type = document.getElementById('mail-history-type-filter').value;
    const search = document.getElementById('mail-history-search').value;

    if (!date) return;

    try {
        const params = new URLSearchParams({
            date: date,
            page: mailHistoryPage,
            limit: mailHistoryPageSize
        });
        if (status) params.append('status', status);
        if (type) params.append('type', type);
        if (search) params.append('search', search);

        const response = await fetch(`api/get_mail_logs.php?${params}`);
        const data = await response.json();

        if (data.error === 0) {
            mailHistoryData = data.logs || [];
            renderMailHistory();
            updateMailHistoryPagination(data.pagination);
        }
    } catch (error) {
        console.error('Error loading mail history:', error);
    }
}

/**
 * Render mail history table
 */
function renderMailHistory() {
    const tbody = document.getElementById('mail-history-tbody');

    if (mailHistoryData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-tertiary);">No emails found for this date</td></tr>';
        return;
    }

    tbody.innerHTML = mailHistoryData.map(log => {
        const statusClass = log.status === 'sent' ? 'stat-success' : (log.status === 'failed' ? 'stat-error' : '');
        const statusIcon = log.status === 'sent' ? '✓' : (log.status === 'failed' ? '✗' : '⏳');
        const typeLabels = {
            'manual': 'Manual',
            'new_account': 'New Account',
            'renewal': 'Renewal',
            'expiry_reminder': 'Expiry'
        };

        return `
            <tr>
                <td>${log.time || ''}</td>
                <td>
                    <strong>${escapeHtml(log.recipient_name || 'N/A')}</strong><br>
                    <small>${escapeHtml(log.recipient_email)}</small>
                </td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(log.subject)}">
                    ${escapeHtml(log.subject)}
                </td>
                <td>${typeLabels[log.message_type] || log.message_type}</td>
                <td>
                    <span class="stat-badge ${statusClass}">${statusIcon} ${log.status}</span>
                    ${log.error_message ? `<br><small style="color: var(--error);" title="${escapeHtml(log.error_message)}">Error</small>` : ''}
                </td>
                <td>${escapeHtml(log.sent_by || 'System')}</td>
            </tr>
        `;
    }).join('');
}

/**
 * Update pagination controls
 */
function updateMailHistoryPagination(pagination) {
    const paginationDiv = document.getElementById('mail-history-pagination');

    if (!pagination || pagination.total === 0) {
        paginationDiv.style.display = 'none';
        return;
    }

    paginationDiv.style.display = 'flex';

    const start = (pagination.page - 1) * pagination.limit + 1;
    const end = Math.min(pagination.page * pagination.limit, pagination.total);

    document.getElementById('mail-history-page-info').textContent = `Showing ${start}-${end} of ${pagination.total}`;

    document.getElementById('mail-history-prev-btn').disabled = pagination.page <= 1;
    document.getElementById('mail-history-next-btn').disabled = pagination.page >= pagination.pages;

    mailHistoryPage = pagination.page;
}

/**
 * Change history page
 */
function changeMailHistoryPage(offset) {
    mailHistoryPage += offset;
    loadMailHistory();
}

/**
 * Change history page size
 */
function changeMailHistoryPageSize() {
    mailHistoryPageSize = parseInt(document.getElementById('mail-history-page-size').value);
    mailHistoryPage = 1;
    loadMailHistory();
}

/**
 * Filter mail history (debounced)
 */
let mailHistoryFilterTimeout;
function filterMailHistory() {
    clearTimeout(mailHistoryFilterTimeout);
    mailHistoryFilterTimeout = setTimeout(() => {
        mailHistoryPage = 1;
        loadMailHistory();
    }, 300);
}

/**
 * Update mail statistics display
 */
function updateMailStats(stats) {
    if (!stats) return;

    document.getElementById('mail-stats-total').textContent = stats.total || 0;
    document.getElementById('mail-stats-sent').textContent = stats.sent || 0;
    document.getElementById('mail-stats-failed').textContent = stats.failed || 0;
    document.getElementById('mail-stats-pending').textContent = stats.pending || 0;
}

/**
 * Show status message
 */
function showMailStatus(elementId, message, isError) {
    const statusDiv = document.getElementById(elementId);
    statusDiv.textContent = message;
    statusDiv.className = 'reminder-status ' + (isError ? 'error' : 'success');
    statusDiv.style.display = 'block';

    // Auto-hide after 5 seconds
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Extend switchMessagingTab to handle mail tab
const originalSwitchMessagingTab = window.switchMessagingTab || function() {};
window.switchMessagingTab = function(tab) {
    const stbContent = document.getElementById('stb-messages-content');
    const smsContent = document.getElementById('sms-messages-content');
    const mailContent = document.getElementById('mail-messages-content');
    const tabs = document.querySelectorAll('.messaging-tab-btn');

    tabs.forEach(btn => btn.classList.remove('active'));

    if (tab === 'stb') {
        stbContent.style.display = 'block';
        smsContent.style.display = 'none';
        mailContent.style.display = 'none';
        tabs[0].classList.add('active');
        loadReminderSettings && loadReminderSettings();
    } else if (tab === 'sms') {
        stbContent.style.display = 'none';
        smsContent.style.display = 'block';
        mailContent.style.display = 'none';
        tabs[1].classList.add('active');
        loadSMSSettings && loadSMSSettings();
    } else if (tab === 'mail') {
        stbContent.style.display = 'none';
        smsContent.style.display = 'none';
        mailContent.style.display = 'block';
        tabs[2].classList.add('active');
        initializeMailTab();
    }

    localStorage.setItem('messagingSubTab', tab);
};
