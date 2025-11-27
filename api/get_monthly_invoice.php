<?php
/**
 * Monthly Invoice API
 * Returns sales data for a specific reseller for a specific month
 * Only includes debit transactions (sales) - excludes admin credit additions
 */

session_start();
include(__DIR__ . '/../config.php');

header('Content-Type: application/json');

// Check authentication
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    echo json_encode(['error' => 1, 'message' => 'Not logged in']);
    exit();
}

$username = $_SESSION['username'];

// Gregorian to Jalali (Shamsi) conversion functions
function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

// Jalali to Gregorian conversion
function jalaliToGregorian($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = array(0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return array($gy, $gm, $gd);
}

// Get Shamsi month name
function getShamsiMonthName($month) {
    $months = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند'
    ];
    return $months[$month] ?? '';
}

// Get Gregorian month name
function getGregorianMonthName($month) {
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];
    return $months[$month] ?? '';
}

// Get days in Shamsi month
function getDaysInShamsiMonth($month, $year) {
    if ($month <= 6) return 31;
    if ($month <= 11) return 30;
    // Esfand (12th month) - check leap year
    $isLeap = (($year - 474) % 2820 < 474) ?
              ((($year - 474) % 2820 - 474) % 128 < 124 && (($year - 474) % 2820 - 474) % 128 != 0) :
              ((($year - 474) % 2820) % 128 < 124 && (($year - 474) % 2820) % 128 != 0);
    return $isLeap ? 30 : 29;
}

try {
    $dsn = "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8";
    $pdo = new PDO($dsn, $ub_db_username, $ub_db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Get current user info
    $stmt = $pdo->prepare('SELECT * FROM _users WHERE username = ?');
    $stmt->execute([$username]);
    $user_info = $stmt->fetch();

    if (!$user_info) {
        echo json_encode(['error' => 1, 'message' => 'User not found']);
        exit();
    }

    $is_super_user = $user_info['super_user'] == 1;
    $is_observer = $user_info['is_observer'] == 1;

    // Parse permissions
    $permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
    $is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

    // Get request parameters
    $reseller_id = isset($_GET['reseller_id']) ? intval($_GET['reseller_id']) : 0;
    $calendar_type = isset($_GET['calendar']) ? $_GET['calendar'] : 'gregorian';
    $year = isset($_GET['year']) ? intval($_GET['year']) : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;

    // If not super user/observer/reseller admin, can only see own data
    if (!$is_super_user && !$is_observer && !$is_reseller_admin) {
        $reseller_id = $user_info['id'];
    }

    // Validate parameters
    if (!$reseller_id || !$year || !$month) {
        echo json_encode(['error' => 1, 'message' => 'Missing required parameters (reseller_id, year, month)']);
        exit();
    }

    // Calculate date range based on calendar type
    if ($calendar_type === 'shamsi') {
        // Convert Shamsi month to Gregorian date range
        $startDateJalali = jalaliToGregorian($year, $month, 1);
        $endDay = getDaysInShamsiMonth($month, $year);
        $endDateJalali = jalaliToGregorian($year, $month, $endDay);

        $startDate = sprintf('%04d-%02d-%02d', $startDateJalali[0], $startDateJalali[1], $startDateJalali[2]);
        $endDate = sprintf('%04d-%02d-%02d', $endDateJalali[0], $endDateJalali[1], $endDateJalali[2]);

        $periodDisplay = getShamsiMonthName($month) . ' ' . $year;
        $periodDisplayEn = getShamsiMonthName($month) . ' ' . $year . ' (Shamsi)';
    } else {
        // Gregorian calendar
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $periodDisplay = getGregorianMonthName($month) . ' ' . $year;
        $periodDisplayEn = getGregorianMonthName($month) . ' ' . $year;
    }

    // Get reseller info
    $stmt = $pdo->prepare('SELECT id, username, name, email, currency_id, balance FROM _users WHERE id = ?');
    $stmt->execute([$reseller_id]);
    $reseller = $stmt->fetch();

    if (!$reseller) {
        echo json_encode(['error' => 1, 'message' => 'Reseller not found']);
        exit();
    }

    // Get transactions for the period (ONLY sales transactions)
    // Include: Account renewals (type=1), New accounts/Plan assigned (type=0)
    // Exclude: Credit adjustments by admin
    $startTimestamp = strtotime($startDate . ' 00:00:00');
    $endTimestamp = strtotime($endDate . ' 23:59:59');

    $stmt = $pdo->prepare("
        SELECT t.*,
               FROM_UNIXTIME(t.timestamp, '%Y-%m-%d') as date_gregorian,
               FROM_UNIXTIME(t.timestamp, '%H:%i') as time
        FROM _transactions t
        WHERE t.for_user = ?
          AND t.timestamp >= ?
          AND t.timestamp <= ?
          AND t.details NOT LIKE '%Credit adjustment%'
          AND (
              t.type = 1
              OR (t.type = 0 AND t.details LIKE '%Plan %assigned%')
          )
        ORDER BY t.timestamp ASC
    ");
    $stmt->execute([$reseller_id, $startTimestamp, $endTimestamp]);
    $transactions = $stmt->fetchAll();

    // Process transactions and add Shamsi dates
    $processedTransactions = [];
    $totalSales = 0;
    $newAccounts = 0;
    $renewals = 0;

    foreach ($transactions as $trans) {
        // Parse Gregorian date
        $dateParts = explode('-', $trans['date_gregorian']);
        $gy = intval($dateParts[0]);
        $gm = intval($dateParts[1]);
        $gd = intval($dateParts[2]);

        // Convert to Shamsi
        $shamsiDate = gregorianToJalali($gy, $gm, $gd);
        $shamsiDateStr = sprintf('%04d/%02d/%02d', $shamsiDate[0], $shamsiDate[1], $shamsiDate[2]);

        // Count transaction types from description
        $details = $trans['details'] ?? '';
        $description = strtolower($details);
        if (strpos($description, 'account renewal') !== false || strpos($description, 'renew') !== false) {
            $renewals++;
        } elseif (strpos($description, 'plan ') !== false && strpos($description, 'assigned') !== false) {
            $newAccounts++;
        }

        // Extract MAC address from description or look it up from accounts table
        $macAddress = '';

        // First try to find MAC address directly in description (format: XX:XX:XX:XX:XX:XX)
        if (preg_match('/([0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2})/', $details, $matches)) {
            $macAddress = strtoupper($matches[1]);
        }
        // For renewals, extract username and look up MAC from accounts table
        elseif (preg_match('/Account renewal:\s*([a-zA-Z0-9]+)\s*-/', $details, $matches)) {
            $accountUsername = $matches[1];
            $macStmt = $pdo->prepare('SELECT mac FROM _accounts WHERE username = ? LIMIT 1');
            $macStmt->execute([$accountUsername]);
            $accountRow = $macStmt->fetch();
            if ($accountRow && !empty($accountRow['mac'])) {
                $macAddress = strtoupper($accountRow['mac']);
            }
        }

        $totalSales += floatval($trans['amount']);

        $processedTransactions[] = [
            'id' => $trans['id'],
            'date_gregorian' => $trans['date_gregorian'],
            'date_shamsi' => $shamsiDateStr,
            'time' => $trans['time'],
            'mac_address' => $macAddress,
            'amount' => floatval($trans['amount']),
            'currency' => $trans['currency'] ?? $reseller['currency_id'],
            'description' => $trans['details'] ?? ''
        ];
    }

    // Get currency symbol
    $resellerCurrency = $reseller['currency_id'] ?? 'GBP';
    $currencySymbols = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'IRR' => 'IRR ',
        'IRT' => 'IRT '
    ];
    $currencySymbol = $currencySymbols[$resellerCurrency] ?? $resellerCurrency;

    // Prepare response
    $response = [
        'error' => 0,
        'invoice' => [
            'period' => [
                'calendar' => $calendar_type,
                'year' => $year,
                'month' => $month,
                'display' => $periodDisplay,
                'display_en' => $periodDisplayEn,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'reseller' => [
                'id' => $reseller['id'],
                'name' => $reseller['name'] ?: $reseller['username'],
                'username' => $reseller['username'],
                'email' => $reseller['email'],
                'currency' => $resellerCurrency,
                'currency_symbol' => $currencySymbol,
                'current_balance' => floatval($reseller['balance'])
            ],
            'summary' => [
                'new_accounts' => $newAccounts,
                'renewals' => $renewals,
                'total_transactions' => count($processedTransactions),
                'total_sales' => $totalSales,
                'total_sales_formatted' => number_format($totalSales, ($resellerCurrency == 'IRR' || $resellerCurrency == 'IRT') ? 0 : 2),
                'amount_owed' => $totalSales,
                'amount_owed_formatted' => number_format($totalSales, ($resellerCurrency == 'IRR' || $resellerCurrency == 'IRT') ? 0 : 2)
            ],
            'transactions' => $processedTransactions
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => 1, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
