<?php
session_start();
include(__DIR__ . '/config.php');

// Function to get auto-logout timeout
function getAutoLogoutTimeout($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM _app_settings WHERE setting_key = ?');
        $stmt->execute(['auto_logout_timeout']);
        $result = $stmt->fetch();
        return $result ? (int)$result['setting_value'] : 5;
    } catch (Exception $e) {
        return 5;
    }
}

// Check if user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    header('Location: index.html');
    exit();
}

// Check for session timeout
try {
    $dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
    $pdo = new PDO($dsn, $ub_db_username, $ub_db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $timeout_minutes = getAutoLogoutTimeout($pdo);

    if ($timeout_minutes > 0) {
        $timeout_seconds = $timeout_minutes * 60;

        if (isset($_SESSION['last_activity'])) {
            $inactive_time = time() - $_SESSION['last_activity'];
            // Use >= to ensure timeout at exactly the limit
            if ($inactive_time >= $timeout_seconds) {
                // Session expired - properly destroy session and clear cookie
                $_SESSION = array();

                // Clear the session cookie to prevent session ID reuse
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }

                session_destroy();
                header('Location: index.html?expired=1');
                exit();
            }
        }

        // Update last activity ONLY on page load (not heartbeat - that's handled separately)
        $_SESSION['last_activity'] = time();
    }
} catch (Exception $e) {
    // On error, just continue (don't block user)
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- Enhanced viewport for iOS PWA (v1.10.0) - viewport-fit removed for desktop compatibility -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>ShowBox - Billing Dashboard</title>

    <!-- PWA Meta Tags -->
    <meta name="description" content="IPTV Billing & Reseller Management System">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Billing Panel">
    <link rel="manifest" href="manifest.json">

    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="assets/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="assets/icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="assets/icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="assets/icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="assets/icons/icon-512x512.png">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="shortcut icon" href="assets/images/favicon.png">

    <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime('dashboard.css'); ?>">

    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p class="loading-text">Loading dashboard...</p>
    </div>

    <nav class="navbar">
        <div class="navbar-brand">
            <h1>ShowBox Billing Panel</h1>
            <small class="app-version">© 2025 All Rights Reserved | v1.11.49</small>
        </div>
        <div class="user-info">
            <span id="user-balance"></span>
            <span id="username-display"></span>
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                <span id="theme-icon">☀️</span>
            </button>
            <button class="refresh-btn" onclick="refreshPage()" title="Refresh Page">
                🔄
            </button>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Accounts</h3>
                <div class="value" id="total-accounts">0</div>
            </div>
            <div class="stat-card">
                <h3>Your Balance</h3>
                <div class="value" id="balance-display">£0</div>
            </div>
            <div class="stat-card">
                <h3>Total Resellers</h3>
                <div class="value" id="total-resellers">0</div>
            </div>
            <div class="stat-card">
                <h3>Total Plans</h3>
                <div class="value" id="total-plans">0</div>
            </div>
            <div class="stat-card expiring-soon">
                <h3>Expiring Soon</h3>
                <div class="value" id="expiring-soon">0</div>
                <small style="color: #666; font-size: 12px;">Next 2 weeks</small>
            </div>
            <div class="stat-card expired-last-month">
                <h3>Expired Last Month</h3>
                <div class="value" id="expired-last-month">0</div>
                <small style="color: #fff; font-size: 12px;">Not renewed</small>
            </div>
        </div>

        <!-- View Mode Toggle (for reseller admins) -->
        <div id="view-mode-toggle" class="view-mode-toggle">
            <label class="view-mode-switch">
                <input type="checkbox" id="view-all-accounts" onchange="toggleAccountViewMode()">
                <span class="view-mode-slider"></span>
            </label>
            <span id="view-mode-label" class="view-mode-label">View All Accounts</span>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('accounts')">Accounts</button>
            <button class="tab" onclick="switchTab('resellers')">Resellers</button>
            <button class="tab" onclick="switchTab('plans')">Plans</button>
            <button class="tab" onclick="switchTab('transactions')">Transactions</button>
            <button class="tab" onclick="switchTab('stb-control')">STB Control</button>
            <button class="tab" onclick="switchTab('messaging')">Messaging</button>
            <button class="tab" onclick="switchTab('reports')">Reports</button>
            <button class="tab" onclick="switchTab('settings')">Settings</button>
        </div>

        <div class="content">
            <!-- Accounts Tab -->
            <div id="accounts-tab" class="tab-content active">
                <div class="section-header">
                    <h2>Account Management</h2>
                    <button class="btn-primary" onclick="openModal('addAccountModal')">+ Add Account</button>
                </div>
                <div class="search-container">
                    <input type="text" id="accounts-search" placeholder="Search by username, name, MAC, or tariff..." onkeyup="searchAccounts()">
                    <button id="reset-sort-btn" class="btn-reset-sort" onclick="resetSorting()" style="display:none;" title="Reset to default order">
                        ⟲ Reset Sort
                    </button>
                </div>
                <div class="pagination-controls">
                    <div class="per-page-selector">
                        <label>Show:</label>
                        <select id="accounts-per-page" onchange="changeAccountsPerPage()">
                            <option value="25">25 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div id="accounts-pagination-info" class="pagination-info"></div>
                </div>
                <table id="accounts-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th class="sortable" data-sort="full_name">Full Name <span class="sort-icon"></span></th>
                            <th>Phone</th>
                            <th>MAC Address</th>
                            <th>Tariff Plan</th>
                            <th class="sortable" data-sort="reseller_name">Reseller <span class="sort-icon"></span></th>
                            <th>Status</th>
                            <th class="sortable" data-sort="end_date">Expiration Date <span class="sort-icon"></span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="accounts-tbody">
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:#999">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="accounts-pagination" class="pagination-buttons"></div>
            </div>

            <!-- Resellers Tab -->
            <div id="resellers-tab" class="tab-content">
                <div class="section-header">
                    <h2>Reseller Management</h2>
                    <button class="btn-primary" onclick="openModal('addResellerModal')">+ Add Reseller</button>
                </div>
                <table id="resellers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Total Accounts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="resellers-tbody">
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:#999">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Plans Tab -->
            <div id="plans-tab" class="tab-content">
                <div class="section-header">
                    <h2>Plan Management</h2>
                    <button id="add-plan-btn" class="btn-primary" onclick="openModal('addPlanModal')">+ Add Plan</button>
                </div>
                <table id="plans-table">
                    <thead>
                        <tr>
                            <th>Plan ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Currency</th>
                            <th>Price</th>
                            <th>Days</th>
                            <th id="plans-actions-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="plans-tbody">
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:#999">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Transactions Tab -->
            <div id="transactions-tab" class="tab-content">
                <div class="section-header">
                    <h2>Transaction History</h2>
                </div>
                <div class="pagination-controls">
                    <div class="per-page-selector">
                        <label>Show:</label>
                        <select id="transactions-per-page" onchange="changeTransactionsPerPage()">
                            <option value="25">25 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div id="transactions-pagination-info" class="pagination-info"></div>
                </div>
                <table id="transactions-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="timestamp" onclick="sortTransactions('timestamp')">Date <span class="sort-icon"></span></th>
                            <th>Amount</th>
                            <th>Currency</th>
                            <th>Type</th>
                            <th id="reseller-column-header">Reseller</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-tbody">
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:#999">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="transactions-pagination" class="pagination-buttons"></div>
            </div>

            <!-- STB Control Tab -->
            <div id="stb-control-tab" class="tab-content">
                <div class="section-header">
                    <h2>STB Device Control</h2>
                    <p style="color: #666; font-size: 14px; margin-top: 8px;">Send commands and messages to Set-Top Box devices</p>
                </div>

                <div class="stb-control-container">
                    <!-- Send Event Section -->
                    <div class="stb-control-section">
                        <h3>Send Event to Device</h3>
                        <form id="stbEventForm" onsubmit="sendStbEvent(event)">
                            <div class="form-group">
                                <label>MAC Address *</label>
                                <input type="text" id="event-mac" name="mac" required placeholder="00:1A:79:XX:XX:XX">
                            </div>

                            <div class="form-group">
                                <label>Event Type *</label>
                                <select id="event-type" name="event" required>
                                    <option value="">-- Select Event --</option>
                                    <option value="reboot">Reboot</option>
                                    <option value="reload_portal">Reload Portal</option>
                                    <option value="update_channels">Update Channels</option>
                                    <option value="play_channel">Play Channel</option>
                                    <option value="play_radio_channel">Play Radio Channel</option>
                                    <option value="update_image">Update Image</option>
                                    <option value="show_menu">Show Menu</option>
                                    <option value="cut_off">Cut Off</option>
                                </select>
                            </div>

                            <!-- Additional fields for specific events -->
                            <div id="channel-field" class="form-group" style="display: none;">
                                <label>Channel ID</label>
                                <input type="text" id="channel-id" name="channel_id" placeholder="Enter channel ID">
                            </div>

                            <button type="submit" class="btn-primary">Send Event</button>
                        </form>
                    </div>

                    <!-- Send Message Section -->
                    <div class="stb-control-section">
                        <h3>Send Message to Device</h3>
                        <form id="stbMessageForm" onsubmit="sendStbMessage(event)">
                            <div class="form-group">
                                <label>MAC Address *</label>
                                <input type="text" id="message-mac" name="mac" required placeholder="00:1A:79:XX:XX:XX">
                            </div>

                            <div class="form-group">
                                <label>Message *</label>
                                <textarea id="message-text" name="message" rows="4" required placeholder="Enter message to display on device..."></textarea>
                            </div>

                            <button type="submit" class="btn-primary">Send Message</button>
                        </form>
                    </div>
                </div>

                <!-- Recent STB Actions -->
                <div class="stb-actions-history" style="margin-top: 30px;">
                    <h3>Recent Actions</h3>
                    <div id="stb-history" class="stb-history-list">
                        <p style="color: #999; text-align: center; padding: 20px;">No recent actions</p>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div id="reports-tab" class="tab-content">
                <div class="section-header">
                    <h2>Reports & Analytics</h2>
                </div>

                <!-- Dynamic Filters Section -->
                <div id="dynamic-reports-section" class="report-filters">
                    <div class="filter-group">
                        <label>📅 Expired & Not Renewed:</label>
                        <select id="expired-filter" onchange="handleExpiredFilterChange()">
                            <option value="7">Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="180">Last 6 Months</option>
                            <option value="365">Last Year</option>
                            <option value="custom">Custom Days</option>
                        </select>
                        <div id="expired-custom-input" style="display: none;" class="custom-input-group">
                            <input type="number" id="expired-custom-days" min="1" max="3650" placeholder="Enter days" onchange="updateDynamicReports()">
                            <small>Enter number of days (1-3650)</small>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>⏰ Expiring Accounts in Next:</label>
                        <select id="expiring-filter" onchange="handleExpiringFilterChange()">
                            <option value="7">Next 7 Days</option>
                            <option value="14" selected>Next 14 Days</option>
                            <option value="30">Next 30 Days</option>
                            <option value="60">Next 60 Days</option>
                            <option value="90">Next 90 Days</option>
                            <option value="custom">Custom Days</option>
                        </select>
                        <div id="expiring-custom-input" style="display: none;" class="custom-input-group">
                            <input type="number" id="expiring-custom-days" min="1" max="3650" placeholder="Enter days" onchange="updateDynamicReports()">
                            <small>Enter number of days (1-3650)</small>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Report Cards -->
                <div id="dynamic-reports-cards" class="dynamic-reports">
                    <div class="report-card dynamic-card clickable-report" onclick="showReportAccountsList('expired-dynamic')" title="Click to view list of expired accounts">
                        <div class="report-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <span>📉</span>
                        </div>
                        <div class="report-details">
                            <h3>Expired & Not Renewed</h3>
                            <div class="report-value" id="dynamic-expired-count">0</div>
                            <small id="dynamic-expired-label">Last 30 days</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('expired-dynamic', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('expired-dynamic', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card dynamic-card clickable-report" onclick="showReportAccountsList('expiring-dynamic')" title="Click to view list of expiring accounts">
                        <div class="report-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <span>📈</span>
                        </div>
                        <div class="report-details">
                            <h3>Expiring in Selected Period</h3>
                            <div class="report-value" id="dynamic-expiring-count">0</div>
                            <small id="dynamic-expiring-label">Next 14 days</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('expiring-dynamic', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('expiring-dynamic', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>
                </div>

                <!-- Report Stats Grid -->
                <div class="report-stats-grid">
                    <div class="report-card clickable-report" onclick="showReportAccountsList('all-accounts')" title="Click to view all accounts">
                        <div class="report-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <span>📊</span>
                        </div>
                        <div class="report-details">
                            <h3>Total Accounts</h3>
                            <div class="report-value" id="report-total-accounts">0</div>
                            <small>All registered accounts</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('all-accounts', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('all-accounts', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card clickable-report" onclick="showReportAccountsList('active-accounts')" title="Click to view all active accounts">
                        <div class="report-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <span>✅</span>
                        </div>
                        <div class="report-details">
                            <h3>Active Accounts</h3>
                            <div class="report-value" id="report-active-accounts">0</div>
                            <small>Currently active subscriptions</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('active-accounts', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('active-accounts', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card clickable-report" onclick="showReportAccountsList('expired-all')" title="Click to view all expired accounts">
                        <div class="report-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <span>❌</span>
                        </div>
                        <div class="report-details">
                            <h3>Expired Accounts</h3>
                            <div class="report-value" id="report-expired-accounts">0</div>
                            <small>Subscriptions that have expired</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('expired-all', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('expired-all', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card clickable-report" onclick="showReportAccountsList('expiring-soon')" title="Click to view accounts expiring in next 2 weeks">
                        <div class="report-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <span>⚠️</span>
                        </div>
                        <div class="report-details">
                            <h3>Expiring Soon</h3>
                            <div class="report-value" id="report-expiring-soon">0</div>
                            <small>Expiring in next 2 weeks</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('expiring-soon', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('expiring-soon', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card clickable-report" onclick="showReportAccountsList('unlimited-plans')" title="Click to view accounts with unlimited plans">
                        <div class="report-icon" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);">
                            <span>♾️</span>
                        </div>
                        <div class="report-details">
                            <h3>Unlimited Plans</h3>
                            <div class="report-value" id="report-unlimited-accounts">0</div>
                            <small>No expiration date</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('unlimited-plans', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('unlimited-plans', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>

                    <div class="report-card clickable-report" onclick="showReportAccountsList('expired-last-month-static')" title="Click to view accounts expired in last 30 days">
                        <div class="report-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);">
                            <span>📅</span>
                        </div>
                        <div class="report-details">
                            <h3>Expired Last Month</h3>
                            <div class="report-value" id="report-expired-last-month">0</div>
                            <small>Not renewed in 30 days</small>
                        </div>
                        <div class="report-export-buttons">
                            <button class="btn-export btn-export-pdf" onclick="event.stopPropagation(); exportReport('expired-last-month-static', 'pdf')" title="Export as PDF">📄 PDF</button>
                            <button class="btn-export btn-export-excel" onclick="event.stopPropagation(); exportReport('expired-last-month-static', 'excel')" title="Export as Excel">📊 Excel</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-content">
                <div class="section-header">
                    <h2>Settings</h2>
                </div>

                <!-- Change Password Section -->
                <div class="settings-item">
                    <div class="settings-item-content">
                        <h3>Change Password</h3>
                        <p>Update your account password</p>
                    </div>
                    <button class="btn-primary" onclick="openModal('changePasswordModal')">Change Password</button>
                </div>

                <!-- Biometric Login Section -->
                <div id="biometric-settings-section" class="settings-item" style="margin-top: 20px; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary);">
                    <h3 style="margin-bottom: 10px;">🔐 Face ID / Touch ID Login</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Enable biometric authentication for faster, secure login on this device.</p>

                    <div id="biometric-loading" style="padding: 12px; color: var(--text-secondary);">
                        Checking biometric support...
                    </div>

                    <div id="biometric-not-supported" style="display:none; padding: 12px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 6px; color: #856404;">
                        <strong>⚠️ Not Supported:</strong> Your device does not support biometric authentication. This feature requires Face ID (iOS), Touch ID (Mac/iOS), or Windows Hello.
                    </div>

                    <div id="biometric-content" style="display:none;">
                        <div id="biometric-status" style="margin-bottom: 16px;">
                            <div id="no-biometric-registered" style="padding: 12px; background: var(--bg-tertiary); border-radius: 6px;">
                                <p style="margin: 0 0 12px 0; color: var(--text-secondary);">No biometric credentials registered for this device.</p>
                                <button class="btn-primary" onclick="registerBiometric()" id="register-biometric-btn">
                                    🔐 Enable Face ID / Touch ID
                                </button>
                            </div>
                            <div id="biometric-registered" style="display:none;">
                                <div style="padding: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; margin-bottom: 12px;">
                                    <strong style="color: #10b981;">✓ Biometric Login Enabled</strong>
                                    <p style="margin: 8px 0 0 0; color: var(--text-secondary); font-size: 13px;">You can now use Face ID / Touch ID to log in.</p>
                                </div>
                                <div id="biometric-credentials-list" style="margin-bottom: 12px;"></div>
                                <button class="btn-secondary" onclick="registerBiometric()" style="margin-right: 8px;">
                                    ➕ Add Another Device
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auto-Logout Settings (Super Admin Only) -->
                <div id="auto-logout-settings-section" class="settings-item" style="display:none; margin-top: 20px; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary);">
                    <h3 style="margin-bottom: 10px;">⏱️ Auto-Logout Settings</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Configure automatic logout after a period of inactivity. Applies to all users (PWA and Web).</p>

                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <label for="auto-logout-timeout" style="color: var(--text-primary); font-weight: 500;">Logout after inactivity:</label>
                        <select id="auto-logout-timeout" style="padding: 10px 15px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 14px; min-width: 150px;">
                            <option value="0">Disabled</option>
                            <option value="1">1 minute</option>
                            <option value="2">2 minutes</option>
                            <option value="3">3 minutes</option>
                            <option value="5" selected>5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="60">60 minutes</option>
                        </select>
                        <button class="btn-primary" onclick="saveAutoLogoutSettings()" id="save-auto-logout-btn">
                            💾 Save
                        </button>
                    </div>
                    <div id="auto-logout-status" style="margin-top: 12px; display:none; padding: 10px; border-radius: 6px;"></div>
                    <p style="color: var(--text-secondary); font-size: 12px; margin-top: 15px;">
                        <strong>Note:</strong> Users will be automatically logged out after the specified period of no activity (no mouse movement, clicks, or keyboard input).
                    </p>
                </div>

                <!-- Push Notification Settings (All Users) -->
                <div id="push-notification-section" class="settings-item" style="margin-top: 20px; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary);">
                    <h3 style="margin-bottom: 10px;">Push Notifications</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Receive alerts when accounts are added, renewed, or expired.</p>

                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div id="push-status" style="display: flex; align-items: center; gap: 10px; min-width: 180px;">
                                <span id="push-status-icon">...</span>
                                <span id="push-status-text">Checking...</span>
                            </div>
                            <button id="push-subscribe-btn" class="btn-primary" onclick="togglePushNotifications()" style="display:none;">
                                Enable Notifications
                            </button>
                        </div>
                        <div id="push-notification-status" style="display:none; padding: 10px; border-radius: 6px;"></div>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 12px; margin-top: 15px;">
                        <strong>Note:</strong> On iOS, you must install this app to Home Screen first (Share > Add to Home Screen), then enable notifications.
                    </p>
                </div>

                <!-- Sync Accounts Section (Admin Only) -->
                <div id="sync-section" class="sync-section" style="display:none;">
                    <h3>Sync Accounts</h3>
                    <p>Sync all accounts from Stalker Portal. This will delete all existing accounts and fetch fresh data from the server.</p>
                    <button class="btn-sync" onclick="syncAccounts()">
                        <span id="sync-icon">⟳</span> Sync from Server
                    </button>
                </div>
                <div id="sync-status" class="sync-status" style="display:none;"></div>

                <!-- Database Backup & Restore Section (Admin & Reseller Admin Only) -->
                <div id="database-backup-section" class="settings-item" style="display:none; margin-top: 30px; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary);">
                    <h3 style="margin-bottom: 10px;">💾 Database Backup & Restore</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Export and import your database for backup or migration purposes.</p>

                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Export Section -->
                        <div style="padding: 15px; background: var(--bg-primary); border-radius: 6px; border-left: 4px solid var(--success);">
                            <h4 style="margin: 0 0 8px 0; color: var(--success);">📤 Export Database</h4>
                            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Download a complete backup of your database as an SQL file.</p>
                            <button class="btn-primary" onclick="exportDatabase(this)" style="background: var(--success); border-color: var(--success);">
                                <span id="export-icon">💾</span> Export Database (.sql)
                            </button>
                            <div id="export-status" style="margin-top: 10px; display:none;"></div>
                        </div>

                        <!-- Import Section -->
                        <div style="padding: 15px; background: var(--bg-primary); border-radius: 6px; border-left: 4px solid var(--warning);">
                            <h4 style="margin: 0 0 8px 0; color: var(--warning);">📥 Import Database</h4>
                            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">⚠️ <strong>Warning:</strong> Importing will replace your current database. Make sure to export first!</p>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="file" id="db-import-file" accept=".sql" style="display: none;" onchange="handleDBFileSelected()">
                                <button class="btn-secondary" onclick="document.getElementById('db-import-file').click()">
                                    📁 Choose SQL File
                                </button>
                                <span id="selected-file-name" style="color: var(--text-secondary); font-size: 13px;"></span>
                            </div>
                            <button id="import-db-btn" class="btn-primary" onclick="importDatabase()" style="margin-top: 12px; background: var(--warning); border-color: var(--warning); display: none;">
                                <span id="import-icon">📥</span> Import Database
                            </button>
                            <div id="import-status" style="margin-top: 10px; display:none;"></div>
                        </div>
                    </div>
                </div>

                <!-- Stalker Portal Settings Section (Super Admin Only) -->
                <div id="stalker-settings-section" class="settings-item" style="display:none; margin-top: 30px; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary);">
                    <h3 style="margin-bottom: 10px;">🖥️ Stalker Portal Connection</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Configure connection settings to your Stalker Portal server</p>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <!-- Dual Server Mode Toggle (at top with Edit/Save protection) -->
                        <div class="form-group" style="margin: 0; padding: 16px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; flex: 1;">
                                    <input type="checkbox" id="stalker-dual-server-mode" style="width: 20px; height: 20px;" disabled onchange="toggleDualServerMode(this.checked)">
                                    <div>
                                        <span style="font-weight: 600; font-size: 14px;">Enable Dual Server Mode</span>
                                        <p style="color: var(--text-secondary); font-size: 12px; margin: 4px 0 0 0;">When enabled, all operations (create, update, delete) will be performed on both servers simultaneously</p>
                                    </div>
                                </label>
                                <div style="display: flex; gap: 10px; flex-shrink: 0;">
                                    <button id="dual-mode-save-btn" class="btn-primary" onclick="saveDualServerMode()" style="display: none; padding: 12px 24px; font-size: 15px; min-width: 110px; border-radius: 8px; font-weight: 500;">
                                        💾 Save
                                    </button>
                                    <button id="dual-mode-cancel-btn" class="btn-secondary" onclick="cancelDualServerModeEdit()" style="display: none; padding: 12px 24px; font-size: 15px; min-width: 110px; border-radius: 8px; font-weight: 500;">
                                        ✖ Cancel
                                    </button>
                                    <button id="dual-mode-edit-btn" class="btn-secondary" onclick="enableDualServerModeEdit()" style="padding: 12px 24px; font-size: 15px; min-width: 110px; border-radius: 8px; font-weight: 500;">
                                        ✏️ Edit
                                    </button>
                                </div>
                            </div>
                            <div id="dual-server-status" style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);"></div>
                            <div id="dual-server-warning" style="display: none; margin-top: 12px; padding: 10px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 6px; color: #856404;">
                                <strong>⚠️ Warning:</strong> Both servers must have different addresses for dual mode to work. Please set a different Secondary Server Address.
                            </div>
                        </div>

                        <!-- Server Address -->
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Primary Server Address *</label>
                            <input type="text" id="stalker-server-address" class="reminder-input stalker-field" value="http://81.12.70.4" style="width: 100%;" readonly>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Main Stalker Portal server IP or domain</small>
                        </div>

                        <!-- Secondary Server Address (only visible when dual mode enabled) -->
                        <div id="secondary-server-group" class="form-group" style="margin: 0; display: none;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Secondary Server Address</label>
                            <input type="text" id="stalker-server-2-address" class="reminder-input stalker-field" value="http://81.12.70.4" style="width: 100%;" readonly>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Second Stalker Portal server for redundancy</small>
                        </div>

                        <!-- API Username -->
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">API Username *</label>
                            <input type="text" id="stalker-api-username" class="reminder-input stalker-field" value="admin" style="width: 100%;" readonly>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Stalker Portal API username</small>
                        </div>

                        <!-- API Password -->
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">API Password *</label>
                            <input type="password" id="stalker-api-password" class="reminder-input stalker-field" value="********" style="width: 100%;" readonly>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Stalker Portal API password (leave empty to keep current)</small>
                        </div>

                        <!-- API Base URL (always disabled, auto-generated) -->
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">API Base URL</label>
                            <input type="text" id="stalker-api-base-url" class="reminder-input" value="http://81.12.70.4/stalker_portal/api/" style="width: 100%; background: var(--bg-tertiary); color: var(--text-secondary);" disabled>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Auto-generated from Primary Server Address</small>
                        </div>

                        <!-- Secondary API Base URL (only visible when dual mode enabled) -->
                        <div id="secondary-api-url-group" class="form-group" style="margin: 0; display: none;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Secondary API Base URL</label>
                            <input type="text" id="stalker-api-2-base-url" class="reminder-input" value="http://81.12.70.4/stalker_portal/api/" style="width: 100%; background: var(--bg-tertiary); color: var(--text-secondary);" disabled>
                            <small style="color: var(--text-tertiary); font-size: 12px;">Auto-generated from Secondary Server Address</small>
                        </div>

                        <!-- Test Connection Checkbox (hidden when not editing) -->
                        <div id="stalker-test-checkbox-group" class="form-group" style="margin: 0; display: none;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="stalker-test-connection" checked style="width: 18px; height: 18px;">
                                <span>Test connection before saving</span>
                            </label>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 12px; margin-top: 8px;">
                            <button id="stalker-save-btn" class="btn-primary" onclick="saveStalkerSettings()" style="display: none;">
                                💾 Save Settings
                            </button>
                            <button id="stalker-cancel-btn" class="btn-secondary" onclick="cancelStalkerEdit()" style="display: none;">
                                ✖ Cancel
                            </button>
                            <button id="stalker-edit-btn" class="btn-primary" onclick="enableStalkerEdit()">
                                ✏️ Edit
                            </button>
                            <button class="btn-secondary" onclick="testStalkerConnection()">
                                🔌 Test Connection
                            </button>
                        </div>

                        <div id="stalker-settings-status" style="margin-top: 10px; display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Messaging Tab -->
            <div id="messaging-tab" class="tab-content">
                <div class="section-header">
                    <h2>Messaging Center</h2>
                    <p style="color: var(--text-secondary); margin-top: 8px;">Manage automated and manual messaging to your customers</p>
                </div>

                <!-- SMS Tab Navigation -->
                <div class="messaging-tabs" style="margin-bottom: 20px; border-bottom: 2px solid var(--border-color);">
                    <button class="messaging-tab-btn active" onclick="switchMessagingTab('stb')">STB Messages</button>
                    <button class="messaging-tab-btn" onclick="switchMessagingTab('sms')">SMS Messages</button>
                </div>

                <!-- STB Messages Content -->
                <div id="stb-messages-content" class="messaging-tab-content">
                    <!-- Expiry Reminder Section (STB Permission Required) -->
                    <div id="reminder-section" class="reminder-section" style="display:none;">
                    <h3>Expiry Reminder Settings</h3>
                    <p>Automatically send reminders to customers whose accounts are expiring soon.</p>

                    <div class="reminder-config">
                        <div class="form-group">
                            <label class="toggle-label-horizontal">
                                <span class="toggle-text">Enable Automatic Reminders</span>
                                <div class="reminder-toggle">
                                    <input type="checkbox" id="auto-send-enabled">
                                    <span class="toggle-slider-reminder round"></span>
                                </div>
                            </label>
                            <small>When enabled, system will automatically send reminders daily to accounts expiring in configured days</small>
                        </div>

                        <div class="form-group">
                            <label>Days Before Expiry</label>
                            <input type="number" id="reminder-days" min="1" max="90" value="7" class="reminder-input">
                            <small>Send reminders when accounts expire in this many days (1-90)</small>
                        </div>

                        <div class="form-group">
                            <label>Message Template</label>
                            <textarea id="reminder-template" rows="3" class="reminder-textarea" placeholder="Use {days}, {name}, {username}, {date} as variables">Dear {name}, your subscription expires in {days} days. Please renew soon to maintain uninterrupted service. Thank you for choosing us.</textarea>
                            <small>Available variables: {days}, {name}, {username}, {date}</small>
                        </div>

                        <div class="reminder-actions">
                            <button class="btn-primary" onclick="saveReminderSettings()">Save Reminder Configuration</button>
                            <button class="btn-reminder-send" onclick="sendExpiryReminders()">
                                <span id="reminder-icon">📧</span> Send Reminders Now (Manual)
                            </button>
                        </div>

                        <div id="reminder-status" class="reminder-status" style="display:none;"></div>
                        <div id="reminder-results" class="reminder-results" style="display:none;"></div>
                    </div>

                    <div class="reminder-info">
                        <p><strong>How it works:</strong></p>
                        <ul>
                            <li>Click "Send Reminders Now" to find accounts expiring in N days</li>
                            <li>Each customer receives a personalized message on their device</li>
                            <li>Duplicate reminders are prevented automatically</li>
                            <li>View sent/skipped/failed counts after each sweep</li>
                        </ul>
                        <p id="last-sweep-info" class="last-sweep"></p>
                    </div>
                </div>

                <!-- Reminder History Log -->
                <div id="reminder-history-section" class="reminder-history-section" style="display:none; margin-top: 30px;">
                    <h3>Reminder History</h3>
                    <p>View all sent reminder messages with date filtering</p>

                    <div class="history-controls">
                        <div class="date-browser">
                            <button class="btn-date-nav" onclick="changeHistoryDate(-1)" title="Previous day">◀</button>
                            <input type="date" id="history-date" onchange="loadReminderHistory()" class="history-date-input">
                            <button class="btn-date-nav" onclick="changeHistoryDate(1)" title="Next day">▶</button>
                            <button class="btn-today" onclick="setHistoryToday()">📅 Today</button>
                        </div>
                        <div class="history-stats">
                            <span id="history-total-count" class="stat-badge">0 reminders</span>
                            <span id="history-sent-count" class="stat-badge stat-success">0 sent</span>
                            <span id="history-failed-count" class="stat-badge stat-error">0 failed</span>
                        </div>
                    </div>

                    <div class="history-search-controls">
                        <input type="text" id="history-search" placeholder="Search by account, name, or MAC address..." class="history-search-input" oninput="filterReminderHistory()">
                        <select id="history-status-filter" class="history-filter-select" onchange="filterReminderHistory()">
                            <option value="">All Status</option>
                            <option value="sent">✓ Sent Only</option>
                            <option value="failed">✗ Failed Only</option>
                        </select>
                    </div>

                    <div id="history-table-container" class="history-table-container">
                        <table id="reminder-history-table" class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Account</th>
                                    <th>Full Name</th>
                                    <th>MAC Address</th>
                                    <th>Expiry Date</th>
                                    <th>Days Before</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="history-tbody">
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                                        Select a date to view reminder history
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="history-pagination" id="history-pagination" style="display:none;">
                        <div class="pagination-info">
                            <span id="history-page-info">Showing 0-0 of 0</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="btn-pagination" onclick="changeHistoryPage(-1)" id="history-prev-btn" disabled>Previous</button>
                            <span id="history-page-numbers"></span>
                            <button class="btn-pagination" onclick="changeHistoryPage(1)" id="history-next-btn" disabled>Next</button>
                        </div>
                        <div class="pagination-size">
                            <label>Show:</label>
                            <select id="history-page-size" onchange="changeHistoryPageSize()">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>per page</span>
                        </div>
                    </div>
                </div>
                </div> <!-- End STB Messages Content -->

                <!-- SMS Messages Content -->
                <div id="sms-messages-content" class="messaging-tab-content" style="display:none;">
                    <!-- SMS Configuration Section -->
                    <div class="sms-config-section">
                        <h3>SMS Configuration</h3>
                        <p>Configure your Faraz SMS API settings for sending SMS messages</p>

                        <div class="reminder-config">
                            <div class="form-group">
                                <label>API Token *</label>
                                <input type="password" id="sms-api-token" class="reminder-input" placeholder="Your Faraz SMS API token">
                                <small>Get your API token from <a href="https://sms.farazsms.com/dashboard" target="_blank">Faraz SMS Dashboard</a></small>
                            </div>

                            <div class="form-group">
                                <label>Sender Number *</label>
                                <input type="text" id="sms-sender-number" class="reminder-input" placeholder="+983000505">
                                <small>Your SMS sender phone number in E.164 format</small>
                            </div>

                            <div class="form-group">
                                <label>API Base URL</label>
                                <input type="text" id="sms-base-url" class="reminder-input" value="https://edge.ippanel.com/v1" readonly>
                                <small>Faraz SMS API endpoint (default: https://edge.ippanel.com/v1)</small>
                            </div>

                            <div class="form-group">
                                <label class="toggle-label-horizontal">
                                    <span class="toggle-text">Enable Automatic SMS Reminders</span>
                                    <div class="reminder-toggle">
                                        <input type="checkbox" id="sms-auto-send-enabled">
                                        <span class="toggle-slider-reminder round"></span>
                                    </div>
                                </label>
                                <small>Automatically send 4-stage SMS reminders: 7 days, 3 days, 1 day before expiry, and when account expires</small>
                            </div>

                            <div class="form-group" style="background: var(--bg-secondary); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid var(--primary);">
                                <h4 style="margin: 0 0 8px 0; color: var(--text-primary); font-size: 14px;">📱 Multi-Stage Reminder System</h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary);">
                                    <li>Stage 1: 7 days before expiry (early warning)</li>
                                    <li>Stage 2: 3 days before expiry (urgent reminder ⚠️)</li>
                                    <li>Stage 3: 1 day before expiry (final warning 🚨)</li>
                                    <li>Stage 4: Account expired (service deactivated ❌)</li>
                                </ul>
                                <p style="margin: 12px 0 0 0; font-size: 12px; color: var(--text-tertiary);">✓ Smart duplicate prevention • ✓ Stops after renewal • ✓ Personalized messages</p>
                            </div>

                            <button class="btn-primary" onclick="saveSMSSettings()">Save SMS Configuration</button>
                        </div>

                        <div id="sms-config-status" class="reminder-status" style="display:none;"></div>
                    </div>

                    <!-- Send SMS Section -->
                    <div class="sms-send-section" style="margin-top: 30px;">
                        <h3>Send SMS Messages</h3>
                        <p>Send SMS to individual numbers or multiple accounts</p>

                        <div class="sms-tabs" style="margin-bottom: 20px;">
                            <button class="sms-tab-btn active" onclick="switchSMSMode('manual')">Send to Number</button>
                            <button class="sms-tab-btn" onclick="switchSMSMode('accounts')">Send to Accounts</button>
                        </div>

                        <!-- Manual SMS Mode -->
                        <div id="sms-manual-mode" class="sms-mode-content">
                            <div class="form-group">
                                <label>Recipient Phone Number *</label>
                                <input type="text" id="sms-recipient-number" class="reminder-input" placeholder="+989120000000">
                                <small>Enter phone number in E.164 format (e.g., +989120000000)</small>
                            </div>

                            <div class="form-group">
                                <label>Message *</label>
                                <textarea id="sms-manual-message" rows="4" class="reminder-textarea" maxlength="500" placeholder="Type your SMS message here..." dir="auto"></textarea>
                                <small><span id="sms-manual-char-count">0</span>/500 characters</small>
                            </div>

                            <button class="btn-primary" onclick="sendManualSMS()">📱 Send SMS</button>
                        </div>

                        <!-- Accounts SMS Mode -->
                        <div id="sms-accounts-mode" class="sms-mode-content" style="display:none;">
                            <div class="form-group">
                                <label>Select Accounts *</label>

                                <!-- Search Box -->
                                <div class="sms-account-search">
                                    <input type="text" id="sms-account-search-input" placeholder="Search by name, phone, or MAC..." oninput="filterSMSAccounts()">
                                    <span class="sms-search-icon">🔍</span>
                                </div>

                                <!-- Account Count -->
                                <div class="sms-account-count">
                                    <span id="sms-account-count-text">Loading accounts...</span>
                                </div>

                                <!-- Select All -->
                                <div style="margin-bottom: 12px; padding: 0 4px;">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="select-all-sms" onchange="toggleSelectAllSMS()">
                                        <span>Select All Visible Accounts</span>
                                    </label>
                                </div>

                                <!-- Accounts List -->
                                <div id="sms-accounts-list">
                                    <p style="color: var(--text-tertiary); padding: 20px; text-align: center;">Loading accounts...</p>
                                </div>

                                <small style="display: block; margin-top: 8px;">Only accounts with phone numbers will be shown</small>
                            </div>

                            <div class="form-group">
                                <label>Message Template *</label>
                                <select id="sms-template-select" class="reminder-input" onchange="loadSMSTemplate()">
                                    <option value="">-- Select a template or type custom message --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Message *</label>
                                <textarea id="sms-accounts-message" rows="4" class="reminder-textarea" maxlength="500" placeholder="Type your SMS message. Use {name}, {mac}, {expiry_date} for personalization..." dir="auto"></textarea>
                                <small><span id="sms-accounts-char-count">0</span>/500 characters | Variables: {name}, {mac}, {expiry_date}</small>
                            </div>

                            <button class="btn-primary" onclick="sendAccountsSMS()">📱 Send SMS to Selected Accounts</button>
                        </div>

                        <div id="sms-send-status" class="reminder-status" style="display:none;"></div>
                    </div>

                    <!-- SMS Template Management -->
                    <div class="sms-templates-section" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="margin: 0 0 4px 0;">SMS Message Templates</h3>
                                <p style="margin: 0; font-size: 13px; color: var(--text-secondary);">Create and manage reusable message templates with variables</p>
                            </div>
                            <button class="btn-primary" onclick="showAddTemplateModal()">+ Add Template</button>
                        </div>

                        <!-- Templates List -->
                        <div id="sms-templates-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                            <p style="color: var(--text-tertiary); padding: 20px; text-align: center; grid-column: 1 / -1;">Loading templates...</p>
                        </div>
                    </div>

                    <!-- SMS History Log -->
                    <div class="sms-history-section" style="margin-top: 30px;">
                        <h3>SMS History</h3>
                        <p>View all sent SMS messages with filtering options</p>

                        <div class="history-controls">
                            <div class="date-browser">
                                <input type="date" id="sms-history-date" onchange="loadSMSHistory()" class="history-date-input">
                                <button class="btn-today" onclick="setSMSHistoryToday()">📅 Today</button>
                                <button class="btn-today" onclick="loadSMSHistory()">🔄 Refresh</button>
                            </div>
                            <div class="history-stats">
                                <span id="sms-history-total-count" class="stat-badge">0 SMS</span>
                                <span id="sms-history-sent-count" class="stat-badge stat-success">0 sent</span>
                                <span id="sms-history-failed-count" class="stat-badge stat-error">0 failed</span>
                            </div>
                        </div>

                        <div class="history-search-controls">
                            <input type="text" id="sms-history-search" placeholder="Search by name, number, or MAC..." class="history-search-input" oninput="filterSMSHistory()">
                            <select id="sms-history-status-filter" class="history-filter-select" onchange="filterSMSHistory()">
                                <option value="">All Status</option>
                                <option value="sent">✓ Sent Only</option>
                                <option value="failed">✗ Failed Only</option>
                                <option value="pending">⏳ Pending Only</option>
                            </select>
                            <select id="sms-history-type-filter" class="history-filter-select" onchange="filterSMSHistory()">
                                <option value="">All Types</option>
                                <option value="manual">Manual</option>
                                <option value="expiry_reminder">Expiry Reminder</option>
                                <option value="renewal">Renewal</option>
                                <option value="new_account">New Account</option>
                            </select>
                        </div>

                        <div id="sms-history-table-container" class="history-table-container">
                            <table id="sms-history-table" class="data-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Recipient</th>
                                        <th>Phone Number</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Sent By</th>
                                    </tr>
                                </thead>
                                <tbody id="sms-history-tbody">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                                            Click refresh to load SMS history
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="history-pagination" id="sms-history-pagination" style="display:none;">
                            <div class="pagination-info">
                                <span id="sms-history-page-info">Showing 0-0 of 0</span>
                            </div>
                            <div class="pagination-controls">
                                <button class="btn-pagination" onclick="changeSMSHistoryPage(-1)" id="sms-history-prev-btn" disabled>Previous</button>
                                <span id="sms-history-page-numbers"></span>
                                <button class="btn-pagination" onclick="changeSMSHistoryPage(1)" id="sms-history-next-btn" disabled>Next</button>
                            </div>
                            <div class="pagination-size">
                                <label>Show:</label>
                                <select id="sms-history-page-size" onchange="changeSMSHistoryPageSize()">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>per page</span>
                            </div>
                        </div>
                    </div>

                    <!-- SMS Statistics -->
                    <div class="sms-stats-section" style="margin-top: 30px;">
                        <h3>SMS Statistics</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                            <div class="stat-card">
                                <div class="stat-value" id="sms-stats-total">0</div>
                                <div class="stat-label">Total Sent</div>
                            </div>
                            <div class="stat-card stat-success-card">
                                <div class="stat-value" id="sms-stats-successful">0</div>
                                <div class="stat-label">Successful</div>
                            </div>
                            <div class="stat-card stat-error-card">
                                <div class="stat-value" id="sms-stats-failed">0</div>
                                <div class="stat-label">Failed</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="sms-stats-pending">0</div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End SMS Messages Content -->
            </div>
        </div>
    </div>
    </div>

    <!-- Add Account Modal -->
    <div id="addAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Account</h3>
                <button class="close-btn" onclick="closeModal('addAccountModal')">&times;</button>
            </div>
            <form onsubmit="addAccount(event)" id="addAccountForm">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="account-username" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="text" name="password" id="account-password" required>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="account-fullname" autocapitalize="words" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="phone-input-container">
                        <select name="country_code" id="add-country-code" class="country-code-select">
                            <option value="+98" selected>🇮🇷 +98 (Iran)</option>
                            <option value="+1">🇺🇸 +1 (USA)</option>
                            <option value="+44">🇬🇧 +44 (UK)</option>
                            <option value="+86">🇨🇳 +86 (China)</option>
                            <option value="+91">🇮🇳 +91 (India)</option>
                            <option value="+81">🇯🇵 +81 (Japan)</option>
                            <option value="+49">🇩🇪 +49 (Germany)</option>
                            <option value="+33">🇫🇷 +33 (France)</option>
                            <option value="+7">🇷🇺 +7 (Russia)</option>
                            <option value="+82">🇰🇷 +82 (South Korea)</option>
                            <option value="+39">🇮🇹 +39 (Italy)</option>
                            <option value="custom">✏️ Custom</option>
                        </select>
                        <input type="text" id="add-custom-code" class="custom-code-input" placeholder="+XX" style="display: none;" maxlength="5">
                        <input type="text" name="phone_number" id="add-phone-number" class="phone-number-input" placeholder="9121234567">
                    </div>
                    <small class="phone-hint">Enter number without leading zero (e.g., 9121234567 for Iran)</small>
                </div>
                <div class="form-group">
                    <label>MAC Address *</label>
                    <input type="text" name="mac" required placeholder="00:1A:79:xx:xx:xx">
                </div>

                <!-- Admin Plan Selection (Dropdown) -->
                <div class="form-group" id="add-admin-plan-group">
                    <label>Plan</label>
                    <select name="plan" id="add-plan-select">
                        <option value="0">No Plan</option>
                    </select>
                </div>

                <!-- Reseller Plan Selection (Cards) -->
                <div id="add-reseller-plan-section" style="display: none;">
                    <div class="renewal-header">
                        <h4>Select Plan for New Device</h4>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Choose a plan for this new account</p>
                    </div>
                    <div id="add-new-device-plans-container" class="renewal-plans-grid">
                        <!-- New device plans will be loaded dynamically as cards -->
                    </div>
                </div>

                <div class="form-group" id="add-admin-status-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="display: none;">
                    <label>Expiry Date (leave empty for auto)</label>
                    <input type="text" name="expire_billing_date" placeholder="YYYY/MM/DD">
                </div>
                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" rows="3"></textarea>
                </div>
                <button type="submit" class="btn-primary">Create Account</button>
            </form>
        </div>
    </div>

    <!-- Add Reseller Modal -->
    <div id="addResellerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Reseller</h3>
                <button class="close-btn" onclick="closeModal('addResellerModal')">&times;</button>
            </div>
            <form onsubmit="addReseller(event)">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Max Users (0 = unlimited)</label>
                    <input type="number" name="max_users" value="0">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency">
                        <option value="GBP">GBP</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="IRR">IRR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Balance</label>
                    <input type="number" step="0.01" name="balance" value="0">
                </div>
                <div class="form-group">
                    <label>Assigned Plans</label>
                    <select name="plans" id="reseller-plans-select" multiple size="8" style="width:100%">
                        <!-- Plans will be loaded dynamically -->
                    </select>
                    <small>Hold Ctrl/Cmd to select multiple plans</small>
                </div>
                <div class="form-group">
                    <label>User Theme</label>
                    <select name="theme" id="add-reseller-theme">
                        <!-- Themes will be loaded dynamically -->
                    </select>
                    <small>Portal theme that will be applied to all subscribers under this reseller</small>
                </div>

                <!-- Permissions Section -->
                <div class="form-group">
                    <label style="font-weight: 600; color: var(--text-primary); margin-bottom: 12px; display: block;">User Type & Permissions</label>
                    <div class="permissions-container">
                        <div class="permission-item permission-observer">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_observer" value="1">
                                <span>Observer (Read-Only Access)</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Can view everything but cannot make any changes</small>
                        </div>
                        <div class="permission-item permission-admin">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_admin" value="1">
                                <span>Grant Admin Permissions</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Give full admin access to this reseller</small>
                        </div>
                        <div class="permission-item permission-edit">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_edit_accounts" value="1">
                                <span>Can Edit Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to edit existing accounts</small>
                        </div>
                        <div class="permission-item permission-add">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_add_accounts" value="1">
                                <span>Can Add New Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to create new accounts</small>
                        </div>
                        <div class="permission-item permission-delete">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_delete_accounts" value="1">
                                <span>Can Delete Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to delete accounts</small>
                        </div>
                        <div class="permission-item permission-stb">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_control_stb" value="1">
                                <span>Can Send STB Events & Messages</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to send events and messages to their customers' devices</small>
                        </div>
                        <div class="permission-item permission-status">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_toggle_status" value="1">
                                <span>Can Toggle Account Status</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to enable or disable their customers' accounts</small>
                        </div>
                        <div class="permission-item permission-messaging">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_access_messaging" value="1">
                                <span>Can Access Messaging Tab</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to access the messaging center and expiry reminder features</small>
                        </div>
                        <div class="permission-item permission-edit-phone-name">
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_edit_phone_name" value="1">
                                <span>Can Edit Phone Number, Email & Name</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to edit phone number, email, and name fields when editing/renewing accounts</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Create Reseller</button>
            </form>
        </div>
    </div>

    <!-- Edit Reseller Modal -->
    <div id="editResellerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Reseller</h3>
                <button class="close-btn" onclick="closeModal('editResellerModal')">&times;</button>
            </div>
            <form onsubmit="updateReseller(event)">
                <input type="hidden" id="edit-reseller-id" name="id">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" id="edit-reseller-username" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="edit-reseller-password" name="password" placeholder="Leave blank to keep current password">
                    <small>Only enter a password if you want to change it</small>
                </div>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="edit-reseller-name" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit-reseller-email" name="email">
                </div>
                <div class="form-group">
                    <label>Max Users (0 = unlimited)</label>
                    <input type="number" id="edit-reseller-max-users" name="max_users" value="0">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select id="edit-reseller-currency" name="currency">
                        <option value="GBP">GBP</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="IRR">IRR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>User Theme</label>
                    <select name="theme" id="edit-reseller-theme">
                        <!-- Themes will be loaded dynamically -->
                    </select>
                    <small class="text-warning"><strong>⚠️ Warning:</strong> Changing the theme will update the Stalker Portal theme for ALL existing accounts under this reseller. This change will take effect immediately.</small>
                </div>

                <!-- Permissions Section -->
                <div class="form-group">
                    <label style="font-weight: 600; color: var(--text-primary); margin-bottom: 12px; display: block;">User Type & Permissions</label>
                    <div class="permissions-container">
                        <div class="permission-item permission-observer">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-is-observer" name="is_observer" value="1">
                                <span>Observer (Read-Only Access)</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Can view everything but cannot make any changes</small>
                        </div>
                        <div class="permission-item permission-admin">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-is-admin" name="is_admin" value="1">
                                <span>Grant Admin Permissions</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Give full admin access to this reseller</small>
                        </div>
                        <div class="permission-item permission-edit">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-edit-accounts" name="can_edit_accounts" value="1">
                                <span>Can Edit Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to edit existing accounts</small>
                        </div>
                        <div class="permission-item permission-add">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-add-accounts" name="can_add_accounts" value="1">
                                <span>Can Add New Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to create new accounts</small>
                        </div>
                        <div class="permission-item permission-delete">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-delete-accounts" name="can_delete_accounts" value="1">
                                <span>Can Delete Accounts</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to delete accounts</small>
                        </div>
                        <div class="permission-item permission-stb">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-control-stb" name="can_control_stb" value="1">
                                <span>Can Send STB Events & Messages</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to send events and messages to their customers' devices</small>
                        </div>
                        <div class="permission-item permission-status">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-toggle-status" name="can_toggle_status" value="1">
                                <span>Can Toggle Account Status</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to enable or disable their customers' accounts</small>
                        </div>
                        <div class="permission-item permission-messaging">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-access-messaging" name="can_access_messaging" value="1">
                                <span>Can Access Messaging Tab</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to access the messaging center and expiry reminder features</small>
                        </div>
                        <div class="permission-item permission-edit-phone-name">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit-can-edit-phone-name" name="can_edit_phone_name" value="1">
                                <span>Can Edit Phone Number, Email & Name</span>
                            </label>
                            <small style="margin-left: 24px; color: var(--text-tertiary);">Allow reseller to edit phone number, email, and name fields when editing/renewing accounts</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Update Reseller</button>
            </form>
        </div>
    </div>

    <!-- Add Plan Modal -->
    <div id="addPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Plan</h3>
                <button class="close-btn" onclick="closeModal('addPlanModal')">&times;</button>
            </div>
            <form onsubmit="addPlan(event)">
                <div class="form-group">
                    <label>Select Tariff Plan *</label>
                    <select name="tariff_id" id="tariff-select" required onchange="updatePlanDetails(this)">
                        <option value="">-- Select a tariff --</option>
                    </select>
                    <small>Choose a tariff plan from your Stalker Portal server</small>
                </div>
                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" id="plan-name-input" required>
                    <small>Auto-filled from tariff, but you can edit it</small>
                </div>
                <div class="form-group">
                    <label>Currency *</label>
                    <select name="currency" required>
                        <option value="GBP">GBP (£)</option>
                        <option value="USD">USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="IRR">IRR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" step="0.01" name="price" required>
                    <small>Set the price for this plan in selected currency</small>
                </div>
                <div class="form-group">
                    <label>Duration (Days) *</label>
                    <input type="number" name="days" id="plan-days-input" required readonly>
                    <small>Automatically filled from selected tariff</small>
                </div>
                <div class="form-group">
                    <label>Plan Category *</label>
                    <select name="category" required>
                        <option value="">-- Select Category --</option>
                        <option value="new_device">New Device</option>
                        <option value="application">Application</option>
                        <option value="renew_device">Renew Device</option>
                    </select>
                    <small>Choose the category for this plan (for filtering purposes)</small>
                </div>
                <button type="submit" class="btn-primary">Create Plan</button>
            </form>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div id="editPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Plan</h3>
                <button class="close-btn" onclick="closeModal('editPlanModal')">&times;</button>
            </div>
            <form onsubmit="submitEditPlan(event)">
                <input type="hidden" id="edit-plan-id" name="plan_id">
                <input type="hidden" id="edit-plan-external-id" name="external_id">
                <input type="hidden" id="edit-plan-currency" name="currency">

                <div class="form-group">
                    <label>Plan ID</label>
                    <input type="text" id="edit-plan-id-display" readonly>
                    <small>Plan ID cannot be changed</small>
                </div>

                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" id="edit-plan-name" required>
                </div>

                <div class="form-group">
                    <label>Currency</label>
                    <input type="text" id="edit-plan-currency-display" readonly>
                    <small>Currency cannot be changed</small>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" step="0.01" name="price" id="edit-plan-price" required>
                </div>

                <div class="form-group">
                    <label>Duration (Days) *</label>
                    <input type="number" name="days" id="edit-plan-days" required>
                </div>

                <div class="form-group">
                    <label>Plan Category *</label>
                    <select name="category" id="edit-plan-category" required>
                        <option value="">-- Select Category --</option>
                        <option value="new_device">New Device</option>
                        <option value="application">Application</option>
                        <option value="renew_device">Renew Device</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Update Plan</button>
            </form>
        </div>
    </div>

    <!-- Adjust Credit Modal -->
    <div id="adjustCreditModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Adjust Credit</h3>
                <button class="close-btn" onclick="closeModal('adjustCreditModal')">&times;</button>
            </div>
            <form onsubmit="submitCreditAdjustment(event)">
                <input type="hidden" id="adjust-reseller-id" name="reseller_id">
                <div class="form-group">
                    <label>Reseller</label>
                    <input type="text" id="adjust-reseller-name" readonly>
                </div>
                <div class="form-group">
                    <label>Current Balance</label>
                    <input type="text" id="adjust-current-balance" readonly>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" id="adjust-action">
                        <option value="add">Add Credit</option>
                        <option value="deduct">Deduct Credit</option>
                        <option value="set">Set Balance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" step="0.01" name="amount" id="adjust-amount" required>
                </div>
                <button type="submit" class="btn-primary">Update Credit</button>
            </form>
        </div>
    </div>

    <!-- Assign Plans Modal -->
    <div id="assignPlansModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Plans to Reseller</h3>
                <button class="close-btn" onclick="closeModal('assignPlansModal')">&times;</button>
            </div>
            <form onsubmit="submitPlanAssignment(event)">
                <input type="hidden" id="assign-reseller-id" name="reseller_id">
                <div class="form-group">
                    <label>Reseller</label>
                    <input type="text" id="assign-reseller-name" readonly>
                </div>
                <div class="form-group">
                    <label>Available Plans</label>
                    <div id="assign-plans-checkboxes" class="plans-checkbox-container">
                        <!-- Checkboxes will be loaded dynamically -->
                    </div>
                    <small>Check the plans you want to assign to this reseller. Uncheck to remove access.</small>
                </div>
                <button type="submit" class="btn-primary">Update Plans</button>
            </form>
        </div>
    </div>

    <!-- Edit Account Modal -->
    <div id="editAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit/Renew Account</h3>
                <button class="close-btn" onclick="closeModal('editAccountModal')">&times;</button>
            </div>
            <form onsubmit="return submitEditAccount(event)" id="editAccountForm">
                <input type="hidden" id="edit-original-username" name="original_username">

                <div class="form-group" id="edit-username-group">
                    <label>Username*</label>
                    <input type="text" id="edit-username" name="username" required>
                    <small>Must be unique.</small>
                </div>

                <div class="form-group" id="edit-password-group">
                    <label>Password</label>
                    <input type="text" id="edit-password" name="password" placeholder="">
                    <small>Leave blank for making no change.</small>
                </div>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="edit-name" name="name">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit-email" name="email">
                </div>

                <div class="form-group">
                    <label>Phone number</label>
                    <div class="phone-input-container">
                        <select name="country_code" id="edit-country-code" class="country-code-select">
                            <option value="+98" selected>🇮🇷 +98 (Iran)</option>
                            <option value="+1">🇺🇸 +1 (USA)</option>
                            <option value="+44">🇬🇧 +44 (UK)</option>
                            <option value="+86">🇨🇳 +86 (China)</option>
                            <option value="+91">🇮🇳 +91 (India)</option>
                            <option value="+81">🇯🇵 +81 (Japan)</option>
                            <option value="+49">🇩🇪 +49 (Germany)</option>
                            <option value="+33">🇫🇷 +33 (France)</option>
                            <option value="+7">🇷🇺 +7 (Russia)</option>
                            <option value="+82">🇰🇷 +82 (South Korea)</option>
                            <option value="+39">🇮🇹 +39 (Italy)</option>
                            <option value="custom">✏️ Custom</option>
                        </select>
                        <input type="text" id="edit-custom-code" class="custom-code-input" placeholder="+XX" style="display: none;" maxlength="5">
                        <input type="text" id="edit-phone" name="phone" class="phone-number-input" placeholder="9121234567">
                    </div>
                    <small class="phone-hint">Enter number without leading zero (e.g., 9121234567 for Iran)</small>
                </div>

                <!-- Admin-only fields -->
                <div class="form-group" id="edit-plan-group">
                    <label>Plan</label>
                    <select id="edit-plan" name="plan">
                        <option value="0">SELECT ONE TO UPDATE</option>
                        <!-- Plans will be loaded dynamically -->
                    </select>
                </div>

                <div class="form-group" id="edit-status-group">
                    <label>Status</label>
                    <select id="edit-status" name="status">
                        <option value="1">On</option>
                        <option value="0">Off</option>
                    </select>
                </div>

                <!-- Reseller Renewal Section (hidden by default) -->
                <div id="reseller-renewal-section" style="display: none;">
                    <div class="renewal-header">
                        <h4>Select Renewal Plan</h4>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Choose a plan to renew this account</p>
                    </div>
                    <div id="renewal-plans-container" class="renewal-plans-grid">
                        <!-- Plans will be loaded dynamically as beautiful buttons -->
                    </div>
                </div>

                <div class="form-group">
                    <label>Comment</label>
                    <textarea id="edit-comment" name="comment" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-primary">Save</button>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Password</h3>
                <button class="close-btn" onclick="closeModal('changePasswordModal')">&times;</button>
            </div>
            <form onsubmit="changePassword(event)" id="changePasswordForm">
                <div class="form-group">
                    <label>Old Password *</label>
                    <input type="password" id="old-password" required>
                </div>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" id="new-password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" id="confirm-password" required>
                </div>
                <button type="submit" class="btn-primary">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Assign Reseller Modal -->
    <div id="assignResellerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Account to Reseller</h3>
                <button class="close-btn" onclick="closeModal('assignResellerModal')">&times;</button>
            </div>
            <form id="assignResellerForm" onsubmit="submitAssignReseller(event)">
                <input type="hidden" id="assign-account-username" name="username">

                <div class="form-group">
                    <label>Account Username</label>
                    <input type="text" id="assign-account-display" disabled>
                </div>

                <div class="form-group">
                    <label>Assign to Reseller *</label>
                    <select id="assign-reseller-select" name="reseller_id" required>
                        <option value="">-- Not Assigned --</option>
                    </select>
                    <small>Select a reseller to assign this account, or leave as "Not Assigned"</small>
                </div>

                <button type="submit" class="btn-primary">Assign Reseller</button>
            </form>
        </div>
    </div>

    <!-- SMS Template Editor Modal -->
    <div id="sms-template-modal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="closeTemplateModal()">&times;</span>
            <h2 id="template-modal-title">Add SMS Template</h2>

            <form id="sms-template-form" onsubmit="saveTemplate(event)">
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" id="template-name" required placeholder="e.g., Payment Reminder">
                    <small>Give your template a descriptive name</small>
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" id="template-description" placeholder="Brief description of when to use this template">
                </div>

                <div class="form-group">
                    <label>Message Template *</label>
                    <textarea id="template-message" rows="6" required placeholder="Type your message here..." oninput="updateTemplatePreview()" dir="auto"></textarea>
                    <small>Available variables: <code>{name}</code>, <code>{mac}</code>, <code>{expiry_date}</code>, <code>{days}</code></small>
                    <div style="margin-top: 8px;">
                        <span id="template-char-count">0</span> characters
                    </div>
                </div>

                <div class="form-group" style="background: var(--bg-secondary); padding: 12px; border-radius: var(--radius-md);">
                    <label style="margin-bottom: 8px; display: block; font-weight: 600;">Preview:</label>
                    <div id="template-preview" style="white-space: pre-wrap; color: var(--text-secondary); font-size: 14px; font-family: monospace;" dir="auto">
                        Enter a message to see preview...
                    </div>
                </div>

                <input type="hidden" id="template-id" value="">
                <button type="submit" class="btn-primary">Save Template</button>
                <button type="button" class="btn-secondary" onclick="closeTemplateModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Pull-to-Refresh Indicator (v1.10.0) -->
    <div id="pull-to-refresh" class="pull-to-refresh" style="display: none;">
        <div class="pull-to-refresh-icon">↻</div>
        <div class="pull-to-refresh-text">Release to refresh</div>
    </div>

    <!-- Mobile Settings Page (v1.10.1 - redesigned) -->
    <div id="mobile-settings-page" class="mobile-settings-page" style="display: none;">
        <div class="mobile-settings-header">
            <h2>Settings</h2>
        </div>
        <div class="mobile-settings-content">
            <!-- User Profile Card -->
            <div class="settings-profile-card">
                <div class="profile-avatar">
                    <span id="settings-avatar-initial">?</span>
                </div>
                <div class="profile-info">
                    <div class="profile-username" id="settings-username">Loading...</div>
                    <div class="profile-role" id="settings-role">-</div>
                </div>
            </div>

            <!-- Settings Options -->
            <div class="settings-options">
                <button class="settings-option-btn" onclick="showChangePassword()">
                    <span class="option-icon">🔑</span>
                    <span class="option-text">Change Password</span>
                    <span class="option-arrow">›</span>
                </button>
                <button class="settings-option-btn" id="mobile-biometric-btn" onclick="showMobileBiometricSettings()">
                    <span class="option-icon">🔐</span>
                    <span class="option-text">Face ID / Touch ID</span>
                    <span class="option-arrow">›</span>
                </button>
                <button class="settings-option-btn" id="mobile-auto-logout-btn" onclick="showMobileAutoLogoutSettings()" style="display: none;">
                    <span class="option-icon">⏱️</span>
                    <span class="option-text">Auto-Logout Settings</span>
                    <span class="option-arrow">›</span>
                </button>
                <button class="settings-option-btn" id="mobile-push-btn" onclick="showMobilePushSettings()" style="display: none;">
                    <span class="option-icon">🔔</span>
                    <span class="option-text">Push Notifications</span>
                    <span class="option-arrow">›</span>
                </button>
                <button class="settings-option-btn settings-logout" onclick="logout()">
                    <span class="option-icon">🚪</span>
                    <span class="option-text">Logout</span>
                    <span class="option-arrow">›</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal (v1.10.1) -->
    <div id="change-password-modal" class="change-password-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeChangePassword()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Password</h3>
                <button class="modal-close-btn" onclick="closeChangePassword()">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" id="current-password" class="form-input" placeholder="Enter current password">
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="new-password" class="form-input" placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirm-password" class="form-input" placeholder="Confirm new password">
                </div>
                <div id="password-error" class="password-error" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeChangePassword()">Cancel</button>
                <button class="btn-save" onclick="saveNewPassword()">Change Password</button>
            </div>
        </div>
    </div>

    <!-- Mobile Biometric Settings Modal (v1.11.18) -->
    <div id="mobile-biometric-modal" class="change-password-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeMobileBiometricSettings()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Face ID / Touch ID</h3>
                <button class="modal-close-btn" onclick="closeMobileBiometricSettings()">✕</button>
            </div>
            <div class="modal-body">
                <div id="mobile-biometric-not-supported" style="display:none; padding: 16px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 8px; color: #856404; text-align: center;">
                    <p style="margin: 0;">Your device does not support biometric authentication.</p>
                </div>
                <div id="mobile-biometric-content">
                    <p style="color: var(--text-secondary); margin-bottom: 20px; text-align: center;">Enable biometric authentication for faster, secure login on this device.</p>
                    <div id="mobile-no-biometric-registered">
                        <button class="btn-save" onclick="registerBiometric()" style="width: 100%; padding: 16px; font-size: 16px;">
                            🔐 Enable Face ID / Touch ID
                        </button>
                    </div>
                    <div id="mobile-biometric-registered" style="display:none;">
                        <div style="padding: 16px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; margin-bottom: 16px; text-align: center;">
                            <strong style="color: #10b981; font-size: 18px;">✓ Biometric Login Enabled</strong>
                            <p style="margin: 8px 0 0 0; color: var(--text-secondary);">You can now use Face ID / Touch ID to log in.</p>
                        </div>
                        <div id="mobile-biometric-credentials-list" style="margin-bottom: 16px;"></div>
                        <button class="btn-cancel" onclick="registerBiometric()" style="width: 100%; padding: 14px;">
                            ➕ Add Another Device
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Auto-Logout Info Modal (v1.11.32 - Read-only) -->
    <div id="mobile-auto-logout-modal" class="change-password-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeMobileAutoLogoutSettings()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>⏱️ Auto-Logout Status</h3>
                <button class="modal-close-btn" onclick="closeMobileAutoLogoutSettings()">✕</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; margin-bottom: 15px;">⏱️</div>
                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">Current Setting:</div>
                    <div id="mobile-auto-logout-display" style="font-size: 24px; font-weight: 600; color: var(--primary);">Loading...</div>
                </div>
                <p style="color: var(--text-secondary); font-size: 12px; margin-top: 20px; text-align: center;">
                    Users will be automatically logged out after the specified period of no activity.
                    <br><br>
                    <strong>To change this setting, use the desktop version.</strong>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-save" onclick="closeMobileAutoLogoutSettings()" style="width: 100%;">OK</button>
            </div>
        </div>
    </div>

    <!-- Mobile Push Notification Modal (v1.11.43) -->
    <div id="mobile-push-modal" class="change-password-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeMobilePushSettings()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Push Notifications</h3>
                <button class="modal-close-btn" onclick="closeMobilePushSettings()">✕</button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-secondary); margin-bottom: 20px; text-align: center;">
                    Receive notifications when resellers add or renew accounts.
                </p>
                <div id="mobile-push-status" style="text-align: center; margin-bottom: 20px;">
                    <span id="mobile-push-status-icon" style="font-size: 48px;">🔔</span>
                    <p id="mobile-push-status-text" style="margin-top: 10px; color: var(--text-secondary);">Checking...</p>
                </div>
                <div id="mobile-push-buttons">
                    <button id="mobile-push-enable-btn" class="btn-save" onclick="togglePushNotifications()" style="width: 100%; padding: 16px; font-size: 16px; display: none;">
                        🔔 Enable Notifications
                    </button>
                    <button id="mobile-push-disable-btn" class="btn-cancel" onclick="togglePushNotifications()" style="width: 100%; padding: 16px; font-size: 16px; display: none;">
                        🔕 Disable Notifications
                    </button>
                </div>
                <div id="mobile-push-result" style="margin-top: 15px; padding: 12px; border-radius: 8px; display: none;"></div>
                <p style="color: var(--text-secondary); font-size: 12px; margin-top: 20px; text-align: center;">
                    <strong>Note:</strong> On iOS, you must install this app to Home Screen first, then enable notifications.
                </p>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation Bar - Mobile Only (v1.10.1 - removed Dashboard, added Settings) -->
    <nav class="bottom-nav" id="bottom-nav">
        <div class="bottom-nav-items">
            <a href="#" class="bottom-nav-item active" data-tab="accounts" onclick="switchTab('accounts'); return false;">
                <span class="bottom-nav-icon">👥</span>
                <span class="bottom-nav-label">Accounts</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="plans" onclick="switchTab('plans'); return false;">
                <span class="bottom-nav-icon">📋</span>
                <span class="bottom-nav-label">Plans</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="resellers" onclick="switchTab('resellers'); return false;">
                <span class="bottom-nav-icon">🏢</span>
                <span class="bottom-nav-label">Resellers</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="messaging" onclick="switchTab('messaging'); return false;">
                <span class="bottom-nav-icon">💬</span>
                <span class="bottom-nav-label">Messages</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="reports" onclick="switchTab('reports'); return false;">
                <span class="bottom-nav-icon">📈</span>
                <span class="bottom-nav-label">Reports</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="transactions" onclick="switchTab('transactions'); return false;">
                <span class="bottom-nav-icon">💳</span>
                <span class="bottom-nav-label">Transactions</span>
            </a>
            <a href="#" class="bottom-nav-item" data-tab="settings" onclick="showMobileSettings(); return false;">
                <span class="bottom-nav-icon">⚙️</span>
                <span class="bottom-nav-label">Settings</span>
            </a>
        </div>
    </nav>

    <!-- Alert notification (placed at end of body to ensure it appears on top of modals) -->
    <div id="alert" class="alert"></div>

    <script src="dashboard.js?v=<?php echo filemtime('dashboard.js'); ?>"></script>
    <script src="sms-functions.js?v=<?php echo filemtime('sms-functions.js'); ?>"></script>
</body>
</html>
