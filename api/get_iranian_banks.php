<?php
/**
 * Get Iranian Banks API
 * Version: 1.17.0
 *
 * Returns the list of Iranian banks for the payment form dropdown.
 */

session_start();
ini_set('display_errors', 0);
error_reporting(0);

include(__DIR__ . '/../config.php');

$response = ['error' => 0, 'message' => ''];

// Check login
if (!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['message'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4",
        $ub_db_username,
        $ub_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE '_iranian_banks'");
    if ($stmt->rowCount() === 0) {
        // Return hardcoded list if table doesn't exist yet
        $response['banks'] = getDefaultBanks();
        $response['source'] = 'default';
    } else {
        // Get from database
        $stmt = $pdo->query("
            SELECT code, name_fa, name_en
            FROM _iranian_banks
            WHERE is_active = 1
            ORDER BY sort_order, name_fa
        ");
        $banks = $stmt->fetchAll();

        if (empty($banks)) {
            $response['banks'] = getDefaultBanks();
            $response['source'] = 'default';
        } else {
            $response['banks'] = $banks;
            $response['source'] = 'database';
        }
    }

    $response['error'] = 0;

} catch (Exception $e) {
    $response['error'] = 1;
    $response['message'] = $e->getMessage();
    $response['banks'] = getDefaultBanks();
    $response['source'] = 'fallback';
}

header('Content-Type: application/json');
echo json_encode($response);

/**
 * Default banks list (fallback)
 */
function getDefaultBanks() {
    return [
        // State-owned banks
        ['code' => 'BMELLI', 'name_fa' => 'بانک ملی ایران', 'name_en' => 'Bank Melli Iran'],
        ['code' => 'BSEPAH', 'name_fa' => 'بانک سپه', 'name_en' => 'Bank Sepah'],
        ['code' => 'BMASKAN', 'name_fa' => 'بانک مسکن', 'name_en' => 'Bank Maskan'],
        ['code' => 'BKESHAVARZI', 'name_fa' => 'بانک کشاورزی', 'name_en' => 'Bank Keshavarzi'],
        ['code' => 'BSANAT', 'name_fa' => 'بانک صنعت و معدن', 'name_en' => 'Bank Sanat va Madan'],
        ['code' => 'BTOSEE', 'name_fa' => 'بانک توسعه صادرات', 'name_en' => 'Export Development Bank'],
        ['code' => 'BTAAVON', 'name_fa' => 'بانک توسعه تعاون', 'name_en' => 'Tose-e Ta\'avon Bank'],
        ['code' => 'BPOSTBANK', 'name_fa' => 'پست بانک ایران', 'name_en' => 'Post Bank of Iran'],

        // Private banks
        ['code' => 'BPARSIAN', 'name_fa' => 'بانک پارسیان', 'name_en' => 'Parsian Bank'],
        ['code' => 'BPASARGAD', 'name_fa' => 'بانک پاسارگاد', 'name_en' => 'Bank Pasargad'],
        ['code' => 'BEGHTESAD', 'name_fa' => 'بانک اقتصاد نوین', 'name_en' => 'EN Bank'],
        ['code' => 'BSAMAN', 'name_fa' => 'بانک سامان', 'name_en' => 'Saman Bank'],
        ['code' => 'BKARAFARIN', 'name_fa' => 'بانک کارآفرین', 'name_en' => 'Karafarin Bank'],
        ['code' => 'BSINA', 'name_fa' => 'بانک سینا', 'name_en' => 'Sina Bank'],
        ['code' => 'BSARMAYEH', 'name_fa' => 'بانک سرمایه', 'name_en' => 'Sarmayeh Bank'],
        ['code' => 'BSHAHR', 'name_fa' => 'بانک شهر', 'name_en' => 'Shahr Bank'],
        ['code' => 'BDEY', 'name_fa' => 'بانک دی', 'name_en' => 'Day Bank'],
        ['code' => 'BSADERAT', 'name_fa' => 'بانک صادرات ایران', 'name_en' => 'Bank Saderat Iran'],
        ['code' => 'BMELLAT', 'name_fa' => 'بانک ملت', 'name_en' => 'Bank Mellat'],
        ['code' => 'BTEJARAT', 'name_fa' => 'بانک تجارت', 'name_en' => 'Bank Tejarat'],
        ['code' => 'BREFAH', 'name_fa' => 'بانک رفاه کارگران', 'name_en' => 'Refah Bank'],
        ['code' => 'BANSAR', 'name_fa' => 'بانک انصار', 'name_en' => 'Ansar Bank'],
        ['code' => 'BGARDESH', 'name_fa' => 'بانک گردشگری', 'name_en' => 'Tourism Bank'],
        ['code' => 'BIRAN_ZAMIN', 'name_fa' => 'بانک ایران زمین', 'name_en' => 'Iran Zamin Bank'],
        ['code' => 'BKOSAR', 'name_fa' => 'بانک کوثر', 'name_en' => 'Kosar Bank'],
        ['code' => 'BAYANDEH', 'name_fa' => 'بانک آینده', 'name_en' => 'Ayandeh Bank'],
        ['code' => 'BKHAVARMIANEH', 'name_fa' => 'بانک خاورمیانه', 'name_en' => 'Middle East Bank'],

        // Other
        ['code' => 'CASH', 'name_fa' => 'نقدی', 'name_en' => 'Cash'],
        ['code' => 'CHEQUE', 'name_fa' => 'چک', 'name_en' => 'Cheque'],
        ['code' => 'TRANSFER', 'name_fa' => 'انتقال وجه', 'name_en' => 'Wire Transfer'],
        ['code' => 'OTHER', 'name_fa' => 'سایر', 'name_en' => 'Other'],
    ];
}
?>
