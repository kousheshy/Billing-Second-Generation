/**
 * Telegram Messaging Functions for ShowBox Billing Panel
 * Handles Telegram bot configuration, notifications, and messaging
 * Version: 1.18.0
 *
 * Access Control:
 * - Only super_admin and reseller_admin have access
 * - Resellers receive notifications only for their own accounts
 * - Reseller admins receive notifications for ALL accounts
 */

console.log('[DEBUG] telegram-functions.js loaded');

// Global Telegram state
let telegramSettings = null;
let telegramRecipients = [];
let telegramHistoryLogs = [];
let telegramFilteredLogs = [];
let telegramCurrentPage = 1;
let telegramPageSize = 25;

/**
 * Initialize Telegram tab based on user permissions
 * Called when dashboard loads
 * ALL users (admins and resellers) can access Telegram to receive notifications
 */
function initializeTelegramTab() {
    const telegramTabBtn = document.getElementById('telegram-tab-btn');

    // Show Telegram tab for ALL users (admins and resellers can link their Telegram)
    if (telegramTabBtn) {
        telegramTabBtn.style.display = 'inline-block';
    }
}

/**
 * Load Telegram settings from server
 */
async function loadTelegramSettings() {
    console.log('[DEBUG] loadTelegramSettings called');
    try {
        const response = await fetch('api/telegram/get_settings.php');
        console.log('[DEBUG] API response status:', response.status);
        const data = await response.json();
        console.log('[DEBUG] API response data:', data);

        if (data.error !== 0) {
            console.error('Failed to load Telegram settings:', data.message);
            showTelegramStatus('telegram-bot-status', data.message, 'error');
            return;
        }

        telegramSettings = data;
        telegramRecipients = data.recipients || [];

        // Update UI based on settings
        console.log('[DEBUG] Calling updateTelegramUI');
        updateTelegramUI(data);

    } catch (error) {
        console.error('Error loading Telegram settings:', error);
        showTelegramStatus('telegram-bot-status', 'Failed to load settings', 'error');
    }
}

/**
 * Update Telegram UI based on loaded settings
 */
function updateTelegramUI(data) {
    try {
        const isSuperAdmin = data.user && data.user.is_super_admin;

        // Show/hide bot configuration section (super admin only)
        const botConfigSection = document.getElementById('telegram-bot-config-section');
        if (botConfigSection) {
            botConfigSection.style.display = isSuperAdmin ? 'block' : 'none';
        }

        // Show/hide Send Message section (only admins and reseller admins can send)
        const canSendMessages = data.user && data.user.can_send_messages;
        const sendMessageSection = document.querySelector('.telegram-send-section');
        if (sendMessageSection) {
            sendMessageSection.style.display = canSendMessages ? 'block' : 'none';
        }

        // Show/hide Message History section (only admins and reseller admins)
        const historySection = document.querySelector('.telegram-history-section');
        if (historySection) {
            historySection.style.display = canSendMessages ? 'block' : 'none';
        }

        // Populate bot token field if available (super admin only)
        if (data.bot_token) {
            const tokenInput = document.getElementById('telegram-bot-token');
            if (tokenInput) {
                tokenInput.value = data.bot_token;
            }
        }

        // Update bot info if configured
        if (data.bot_configured && data.bot_username) {
            const botInfo = document.getElementById('telegram-bot-info');
            const botUsername = document.getElementById('telegram-bot-username');
            const botName = document.getElementById('telegram-bot-name');
            const botLink = document.getElementById('telegram-bot-link');

            if (botInfo) botInfo.style.display = 'block';
            if (botUsername) botUsername.textContent = data.bot_username;
            if (botName) botName.textContent = 'ShowBox Bot';
            if (botLink) {
                botLink.href = `https://t.me/${data.bot_username}`;
                botLink.textContent = `@${data.bot_username}`;
            }
        }

        // Update link status
        updateTelegramLinkStatus(data);

        // Update notification settings checkboxes
        if (data.notification_settings) {
            const ns = data.notification_settings;
            setCheckbox('tg-notify-new-account', ns.notify_new_account);
            setCheckbox('tg-notify-renewal', ns.notify_renewal);
            setCheckbox('tg-notify-expiry', ns.notify_expiry);
            setCheckbox('tg-notify-expired', ns.notify_expired);
            setCheckbox('tg-notify-low-balance', ns.notify_low_balance);
            setCheckbox('tg-notify-new-payment', ns.notify_new_payment);
            setCheckbox('tg-notify-login', ns.notify_login);
            setCheckbox('tg-notify-daily-report', ns.notify_daily_report);
        }

        // Update statistics
        if (data.stats) {
            const statsTotal = document.getElementById('telegram-stats-total');
            const statsSent = document.getElementById('telegram-stats-sent');
            const statsFailed = document.getElementById('telegram-stats-failed');

            if (statsTotal) statsTotal.textContent = data.stats.total_sent || 0;
            if (statsSent) statsSent.textContent = data.stats.sent_success || 0;
            if (statsFailed) statsFailed.textContent = data.stats.sent_failed || 0;
        }

        // Render recipients list
        renderTelegramRecipients(data.recipients);

        // Set today's date for history
        setTelegramHistoryToday();
    } catch (error) {
        console.error('Error updating Telegram UI:', error);
    }
}

/**
 * Update telegram link status display
 */
function updateTelegramLinkStatus(data) {
    const linkStatus = document.getElementById('telegram-link-status');
    const linkForm = document.getElementById('telegram-link-form');
    const linkedInfo = document.getElementById('telegram-linked-info');

    // Safety check - if elements don't exist, return early
    if (!linkStatus || !linkForm || !linkedInfo) {
        console.warn('Telegram link status elements not found in DOM');
        return;
    }

    const isSuperAdmin = data.user && data.user.is_super_admin;

    if (!data.bot_configured) {
        linkStatus.innerHTML = `
            <span style="font-size: 40px; display: block; margin-bottom: 15px;">🤖</span>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Telegram bot is not configured yet.<br>
                ${isSuperAdmin ? 'Please configure the bot token above first.' : 'Please ask admin to configure the bot.'}
            </p>
        `;
        linkStatus.style.display = 'block';
        linkForm.style.display = 'none';
        linkedInfo.style.display = 'none';
        return;
    }

    if (data.user && data.user.telegram_linked) {
        // User has telegram linked
        linkStatus.style.display = 'none';
        linkForm.style.display = 'none';
        linkedInfo.style.display = 'block';
        const linkedDateEl = document.getElementById('telegram-linked-date');
        if (linkedDateEl) {
            linkedDateEl.textContent = formatDate(data.user.telegram_linked_at);
        }
    } else {
        // Bot configured but user not linked
        linkStatus.innerHTML = `
            <span style="font-size: 40px; display: block; margin-bottom: 15px;">📱</span>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Your Telegram is not linked yet.<br>
                Link your account to receive notifications.
            </p>
        `;
        linkStatus.style.display = 'block';
        linkForm.style.display = 'block';
        linkedInfo.style.display = 'none';
    }
}

/**
 * Render recipients list for sending messages
 */
function renderTelegramRecipients(recipients) {
    const container = document.getElementById('telegram-recipients-list');
    if (!container) return;

    if (!recipients || recipients.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No recipients available</p>';
        return;
    }

    let html = '';
    recipients.forEach(r => {
        const linked = r.telegram_linked == 1;
        // Determine badge based on user_type or fallback to super_user/is_reseller_admin
        let badgeIcon = '👤';
        let badgeText = r.user_type || 'Reseller';
        if (r.super_user == 1) {
            badgeIcon = '👑';
            badgeText = 'Admin';
        } else if (r.is_reseller_admin == 1) {
            badgeIcon = '⭐';
            badgeText = 'Reseller Admin';
        }
        const linkedIcon = linked ? '✅' : '❌';
        const linkedText = linked ? 'Linked' : 'Not linked';
        const linkedColor = linked ? '#22c55e' : '#6b7280';

        html += `
            <div style="display: flex; flex-direction: row; align-items: center; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); cursor: ${linked ? 'pointer' : 'not-allowed'}; opacity: ${linked ? '1' : '0.5'};">
                <input type="checkbox" class="telegram-recipient-checkbox" value="${r.id}" ${linked ? '' : 'disabled'} onchange="updateTelegramSelectedCount()" style="width: 18px; height: 18px; margin: 0; flex-shrink: 0;">
                <div style="margin-left: 12px; flex: 1; min-width: 0;">
                    <strong style="display: block; font-size: 14px;">${r.username}</strong>
                    <span style="font-size: 11px; color: #9ca3af;">${badgeIcon} ${badgeText}</span>
                </div>
                <div style="flex-shrink: 0; font-size: 12px; color: ${linkedColor}; white-space: nowrap;">
                    ${linkedIcon} ${linkedText}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    updateTelegramSelectedCount();
}

/**
 * Toggle all telegram recipients
 */
function toggleAllTelegramRecipients() {
    const selectAll = document.getElementById('telegram-select-all-recipients').checked;
    const checkboxes = document.querySelectorAll('.telegram-recipient-checkbox:not(:disabled)');
    checkboxes.forEach(cb => cb.checked = selectAll);
    updateTelegramSelectedCount();
}

/**
 * Update selected recipients count
 */
function updateTelegramSelectedCount() {
    const checked = document.querySelectorAll('.telegram-recipient-checkbox:checked').length;
    const countEl = document.getElementById('telegram-selected-count');
    if (countEl) {
        countEl.textContent = checked;
    }
}

/**
 * Toggle bot token visibility
 */
function toggleTelegramTokenVisibility() {
    const input = document.getElementById('telegram-bot-token');
    const icon = document.getElementById('telegram-token-toggle-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
    } else {
        input.type = 'password';
        icon.textContent = '👁';
    }
}

/**
 * Verify Telegram bot token
 */
async function verifyTelegramBot() {
    const token = document.getElementById('telegram-bot-token').value.trim();

    if (!token) {
        showTelegramStatus('telegram-bot-status', 'Please enter a bot token', 'error');
        return;
    }

    showTelegramStatus('telegram-bot-status', 'Verifying bot token...', 'info');

    try {
        const formData = new FormData();
        formData.append('bot_token', token);

        const response = await fetch('api/telegram/verify_bot.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            const botInfo = document.getElementById('telegram-bot-info');
            const botUsername = document.getElementById('telegram-bot-username');
            const botName = document.getElementById('telegram-bot-name');
            if (botInfo) botInfo.style.display = 'block';
            if (botUsername) botUsername.textContent = data.bot_username;
            if (botName) botName.textContent = data.bot_name;
            showTelegramStatus('telegram-bot-status', `Bot verified: @${data.bot_username}`, 'success');
        } else {
            showTelegramStatus('telegram-bot-status', data.message, 'error');
        }
    } catch (error) {
        showTelegramStatus('telegram-bot-status', 'Failed to verify bot: ' + error.message, 'error');
    }
}

/**
 * Save Telegram bot settings
 */
async function saveTelegramBotSettings() {
    const token = document.getElementById('telegram-bot-token').value.trim();

    if (!token) {
        showTelegramStatus('telegram-bot-status', 'Please enter a bot token', 'error');
        return;
    }

    showTelegramStatus('telegram-bot-status', 'Saving settings...', 'info');

    try {
        const formData = new FormData();
        formData.append('bot_token', token);

        const response = await fetch('api/telegram/update_settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showTelegramStatus('telegram-bot-status', 'Bot settings saved successfully!', 'success');
            // Reload settings to update UI
            loadTelegramSettings();
        } else {
            showTelegramStatus('telegram-bot-status', data.message, 'error');
        }
    } catch (error) {
        showTelegramStatus('telegram-bot-status', 'Failed to save settings: ' + error.message, 'error');
    }
}

/**
 * Link Telegram account
 */
async function linkTelegramAccount() {
    const chatId = document.getElementById('telegram-chat-id-input').value.trim();

    if (!chatId) {
        alert('Please enter your Telegram Chat ID');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'set_chat_id');
        formData.append('chat_id', chatId);

        const response = await fetch('api/telegram/link_telegram.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            alert(data.message);
            loadTelegramSettings();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to link Telegram: ' + error.message);
    }
}

/**
 * Unlink Telegram account
 */
async function unlinkTelegramAccount() {
    if (!confirm('Are you sure you want to unlink your Telegram account? You will stop receiving notifications.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'unlink');

        const response = await fetch('api/telegram/link_telegram.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            alert('Telegram unlinked successfully');
            loadTelegramSettings();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to unlink Telegram: ' + error.message);
    }
}

/**
 * Save notification settings
 */
async function saveTelegramNotificationSettings() {
    try {
        const formData = new FormData();

        // Add checkbox values with null checks
        const newAccount = document.getElementById('tg-notify-new-account');
        const renewal = document.getElementById('tg-notify-renewal');
        const expiry = document.getElementById('tg-notify-expiry');
        const expired = document.getElementById('tg-notify-expired');
        const lowBalance = document.getElementById('tg-notify-low-balance');
        const newPayment = document.getElementById('tg-notify-new-payment');
        const login = document.getElementById('tg-notify-login');
        const dailyReport = document.getElementById('tg-notify-daily-report');

        if (newAccount && newAccount.checked) formData.append('notify_new_account', '1');
        if (renewal && renewal.checked) formData.append('notify_renewal', '1');
        if (expiry && expiry.checked) formData.append('notify_expiry', '1');
        if (expired && expired.checked) formData.append('notify_expired', '1');
        if (lowBalance && lowBalance.checked) formData.append('notify_low_balance', '1');
        if (newPayment && newPayment.checked) formData.append('notify_new_payment', '1');
        if (login && login.checked) formData.append('notify_login', '1');
        if (dailyReport && dailyReport.checked) formData.append('notify_daily_report', '1');

        const response = await fetch('api/telegram/update_notification_settings.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is OK
        if (!response.ok) {
            const text = await response.text();
            console.error('Server error:', response.status, text);
            alert('Server error: ' + response.status);
            return;
        }

        const text = await response.text();

        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Invalid JSON response:', text);
            alert('Invalid response from server. Check console for details.');
            return;
        }

        if (data.error === 0) {
            alert('Notification settings saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Save notification error:', error);
        alert('Failed to save settings: ' + error.message);
    }
}

/**
 * Send Telegram message
 */
async function sendTelegramMessage() {
    const message = document.getElementById('telegram-message').value.trim();
    const checkboxes = document.querySelectorAll('.telegram-recipient-checkbox:checked');
    const recipientIds = Array.from(checkboxes).map(cb => cb.value);

    if (!message) {
        showTelegramStatus('telegram-send-status', 'Please enter a message', 'error');
        return;
    }

    if (recipientIds.length === 0) {
        showTelegramStatus('telegram-send-status', 'Please select at least one recipient', 'error');
        return;
    }

    showTelegramStatus('telegram-send-status', 'Sending message...', 'info');

    try {
        const formData = new FormData();
        formData.append('message', message);
        formData.append('recipient_ids', JSON.stringify(recipientIds));

        const response = await fetch('api/telegram/send_message.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error === 0) {
            showTelegramStatus('telegram-send-status', data.message, 'success');
            document.getElementById('telegram-message').value = '';

            // Uncheck all recipients
            document.querySelectorAll('.telegram-recipient-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('telegram-select-all-recipients').checked = false;
            updateTelegramSelectedCount();

            // Reload history
            loadTelegramHistory();

            // Update stats
            if (telegramSettings && telegramSettings.stats) {
                telegramSettings.stats.total_sent += data.results.sent;
                telegramSettings.stats.sent_success += data.results.sent;
                telegramSettings.stats.sent_failed += data.results.failed;
                const statsTotal = document.getElementById('telegram-stats-total');
                const statsSent = document.getElementById('telegram-stats-sent');
                const statsFailed = document.getElementById('telegram-stats-failed');
                if (statsTotal) statsTotal.textContent = telegramSettings.stats.total_sent;
                if (statsSent) statsSent.textContent = telegramSettings.stats.sent_success;
                if (statsFailed) statsFailed.textContent = telegramSettings.stats.sent_failed;
            }
        } else {
            showTelegramStatus('telegram-send-status', data.message, 'error');
        }
    } catch (error) {
        showTelegramStatus('telegram-send-status', 'Failed to send message: ' + error.message, 'error');
    }
}

/**
 * Load Telegram message history
 */
async function loadTelegramHistory() {
    const dateEl = document.getElementById('telegram-history-date');
    const statusEl = document.getElementById('telegram-history-status-filter');
    const typeEl = document.getElementById('telegram-history-type-filter');

    const date = dateEl ? dateEl.value : '';
    const status = statusEl ? statusEl.value : '';
    const messageType = typeEl ? typeEl.value : '';

    if (!date) return;

    try {
        const params = new URLSearchParams({
            date: date,
            status: status,
            message_type: messageType,
            page: telegramCurrentPage,
            per_page: telegramPageSize
        });

        const response = await fetch('api/telegram/get_logs.php?' + params.toString());
        const data = await response.json();

        if (data.error === 0) {
            telegramHistoryLogs = data.logs;
            renderTelegramHistory(data.logs, data.pagination, data.stats);
        } else {
            console.error('Failed to load history:', data.message);
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

/**
 * Render Telegram history table
 */
function renderTelegramHistory(logs, pagination, stats) {
    const tbody = document.getElementById('telegram-history-tbody');
    const paginationEl = document.getElementById('telegram-history-pagination');

    if (!tbody) {
        console.warn('Telegram history tbody element not found');
        return;
    }

    if (!logs || logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                    No messages found for this date
                </td>
            </tr>
        `;
        if (paginationEl) paginationEl.style.display = 'none';
        return;
    }

    let html = '';
    logs.forEach(log => {
        const statusClass = log.status === 'sent' ? 'status-active' : 'status-expired';
        const statusText = log.status === 'sent' ? 'Delivered' : 'Failed';
        const time = new Date(log.sent_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        const messagePreview = log.message.length > 50 ? log.message.substring(0, 50) + '...' : log.message;

        html += `
            <tr>
                <td>${time}</td>
                <td>${log.recipient_username || '-'}</td>
                <td title="${escapeHtml(log.message)}">${escapeHtml(messagePreview)}</td>
                <td><span class="badge">${formatMessageType(log.message_type)}</span></td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    // Update pagination
    const pageInfo = document.getElementById('telegram-history-page-info');
    const prevBtn = document.getElementById('telegram-history-prev-btn');
    const nextBtn = document.getElementById('telegram-history-next-btn');

    if (pagination && pagination.total_items > pagination.per_page) {
        if (paginationEl) paginationEl.style.display = 'flex';
        if (pageInfo) {
            pageInfo.textContent = `Showing ${(pagination.current_page - 1) * pagination.per_page + 1}-${Math.min(pagination.current_page * pagination.per_page, pagination.total_items)} of ${pagination.total_items}`;
        }
        if (prevBtn) prevBtn.disabled = pagination.current_page <= 1;
        if (nextBtn) nextBtn.disabled = pagination.current_page >= pagination.total_pages;
    } else {
        if (paginationEl) paginationEl.style.display = 'none';
    }

    // Update stats for the day
    if (stats) {
        // Could update daily stats here if needed
    }
}

/**
 * Change history date
 */
function changeTelegramHistoryDate(delta) {
    const dateInput = document.getElementById('telegram-history-date');
    const currentDate = new Date(dateInput.value);
    currentDate.setDate(currentDate.getDate() + delta);
    dateInput.value = currentDate.toISOString().split('T')[0];
    telegramCurrentPage = 1;
    loadTelegramHistory();
}

/**
 * Set history date to today
 */
function setTelegramHistoryToday() {
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('telegram-history-date');
    if (dateInput) {
        dateInput.value = today;
        telegramCurrentPage = 1;
        loadTelegramHistory();
    }
}

/**
 * Change history page
 */
function changeTelegramHistoryPage(delta) {
    telegramCurrentPage += delta;
    if (telegramCurrentPage < 1) telegramCurrentPage = 1;
    loadTelegramHistory();
}

/**
 * Change history page size
 */
function changeTelegramHistoryPageSize() {
    telegramPageSize = parseInt(document.getElementById('telegram-history-page-size').value);
    telegramCurrentPage = 1;
    loadTelegramHistory();
}

/**
 * Filter history (client-side search)
 */
function filterTelegramHistory() {
    // For now, just reload from server
    // Could implement client-side filtering for cached logs
    loadTelegramHistory();
}

// Helper Functions

function showTelegramStatus(elementId, message, type) {
    const element = document.getElementById(elementId);
    if (!element) return;

    element.style.display = 'block';
    element.className = 'reminder-status';

    let bgColor, borderColor;
    switch (type) {
        case 'success':
            bgColor = 'rgba(34, 197, 94, 0.1)';
            borderColor = 'var(--success)';
            break;
        case 'error':
            bgColor = 'rgba(239, 68, 68, 0.1)';
            borderColor = 'var(--error)';
            break;
        default:
            bgColor = 'rgba(59, 130, 246, 0.1)';
            borderColor = 'var(--primary)';
    }

    element.style.background = bgColor;
    element.style.borderLeft = `4px solid ${borderColor}`;
    element.innerHTML = message;

    // Auto-hide after 5 seconds for success messages
    if (type === 'success') {
        setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    }
}

function setCheckbox(id, value) {
    const checkbox = document.getElementById(id);
    if (checkbox) {
        checkbox.checked = value == 1 || value === true;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatMessageType(type) {
    const types = {
        'manual': 'Manual',
        'new_account': 'New Account',
        'renewal': 'Renewal',
        'expiry_reminder': 'Expiry Warning',
        'expired': 'Expired',
        'low_balance': 'Low Balance',
        'new_payment': 'Payment',
        'login_alert': 'Login',
        'daily_report': 'Daily Report',
        'broadcast': 'Broadcast'
    };
    return types[type] || type;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTelegramTab();
});
