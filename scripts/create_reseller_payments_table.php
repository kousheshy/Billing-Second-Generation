<?php
/**
 * Migration Script: Create Reseller Payments Table
 * Version: 1.17.0
 *
 * This script creates the _reseller_payments table for tracking
 * reseller deposits/payments and calculating their running balance.
 *
 * Run this script once on the production server:
 * php scripts/create_reseller_payments_table.php
 */

require_once __DIR__ . '/../config.php';

echo "===========================================\n";
echo "ShowBox Billing - Reseller Payments Migration\n";
echo "Version: 1.17.0\n";
echo "===========================================\n\n";

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

    echo "[1/3] Creating _reseller_payments table...\n";

    // Create reseller payments table
    $sql = "CREATE TABLE IF NOT EXISTS `_reseller_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` INT(11) NOT NULL COMMENT 'FK to _users.id',
        `amount` DECIMAL(15,2) NOT NULL COMMENT 'Payment amount (positive value)',
        `currency` VARCHAR(10) NOT NULL DEFAULT 'IRR' COMMENT 'Currency code',
        `payment_date` DATE NOT NULL COMMENT 'Date payment was made',
        `bank_name` VARCHAR(100) NOT NULL COMMENT 'Bank name from predefined list',
        `reference_number` VARCHAR(100) NULL COMMENT 'Bank reference/tracking number',
        `receipt_path` VARCHAR(255) NULL COMMENT 'Path to uploaded receipt image',
        `description` TEXT NULL COMMENT 'Notes/comments about the payment',
        `recorded_by` INT(11) NOT NULL COMMENT 'User ID who recorded this payment',
        `recorded_by_username` VARCHAR(100) NOT NULL COMMENT 'Username for display',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `status` ENUM('active', 'cancelled') DEFAULT 'active',
        `cancelled_by` INT(11) NULL COMMENT 'User who cancelled (if applicable)',
        `cancelled_at` DATETIME NULL,
        `cancellation_reason` TEXT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_reseller_id` (`reseller_id`),
        INDEX `idx_payment_date` (`payment_date`),
        INDEX `idx_status` (`status`),
        INDEX `idx_bank_name` (`bank_name`),
        INDEX `idx_currency` (`currency`),
        CONSTRAINT `fk_payment_reseller` FOREIGN KEY (`reseller_id`)
            REFERENCES `_users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Reseller payment/deposit tracking for balance calculation (v1.17.0)'";

    $pdo->exec($sql);
    echo "   ✓ Table _reseller_payments created successfully\n\n";

    echo "[2/3] Creating _iranian_banks reference table...\n";

    // Create Iranian banks reference table
    $sql = "CREATE TABLE IF NOT EXISTS `_iranian_banks` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(20) NOT NULL COMMENT 'Bank code',
        `name_fa` VARCHAR(100) NOT NULL COMMENT 'Persian name',
        `name_en` VARCHAR(100) NOT NULL COMMENT 'English name',
        `is_active` TINYINT(1) DEFAULT 1,
        `sort_order` INT DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_code` (`code`),
        INDEX `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Reference table for Iranian banks'";

    $pdo->exec($sql);
    echo "   ✓ Table _iranian_banks created successfully\n\n";

    echo "[3/3] Inserting Iranian banks list...\n";

    // Insert all Iranian banks
    $banks = [
        // State-owned banks (بانک‌های دولتی)
        ['BMELLI', 'بانک ملی ایران', 'Bank Melli Iran', 1],
        ['BSEPAH', 'بانک سپه', 'Bank Sepah', 2],
        ['BMASKAN', 'بانک مسکن', 'Bank Maskan', 3],
        ['BKESHAVARZI', 'بانک کشاورزی', 'Bank Keshavarzi', 4],
        ['BSANAT', 'بانک صنعت و معدن', 'Bank Sanat va Madan', 5],
        ['BTOSEE', 'بانک توسعه صادرات', 'Export Development Bank', 6],
        ['BTAAVON', 'بانک توسعه تعاون', 'Tose-e Taavon Bank', 7],
        ['BPOSTBANK', 'پست بانک ایران', 'Post Bank of Iran', 8],

        // Private banks (بانک‌های خصوصی)
        ['BPARSIAN', 'بانک پارسیان', 'Parsian Bank', 10],
        ['BPASARGAD', 'بانک پاسارگاد', 'Bank Pasargad', 11],
        ['BEGHTESAD', 'بانک اقتصاد نوین', 'EN Bank', 12],
        ['BSAMAN', 'بانک سامان', 'Saman Bank', 13],
        ['BKARAFARIN', 'بانک کارآفرین', 'Karafarin Bank', 14],
        ['BSINA', 'بانک سینا', 'Sina Bank', 15],
        ['BSARMAYEH', 'بانک سرمایه', 'Sarmayeh Bank', 16],
        ['BTAT', 'بانک تات', 'Tat Bank', 17],
        ['BSHAHR', 'بانک شهر', 'Shahr Bank', 18],
        ['BDEY', 'بانک دی', 'Day Bank', 19],
        ['BSADERAT', 'بانک صادرات ایران', 'Bank Saderat Iran', 20],
        ['BMELLAT', 'بانک ملت', 'Bank Mellat', 21],
        ['BTEJARAT', 'بانک تجارت', 'Bank Tejarat', 22],
        ['BREFAH', 'بانک رفاه کارگران', 'Refah Bank', 23],
        ['BANSAR', 'بانک انصار', 'Ansar Bank', 24],
        ['BHEKMAT', 'بانک حکمت ایرانیان', 'Hekmat Iranian Bank', 25],
        ['BGARDESH', 'بانک گردشگری', 'Tourism Bank', 26],
        ['BIRAN_ZAMIN', 'بانک ایران زمین', 'Iran Zamin Bank', 27],
        ['BQAVAMIN', 'بانک قوامین', 'Ghavamin Bank', 28],
        ['BKOSAR', 'بانک کوثر', 'Kosar Bank', 29],
        ['BASIAN', 'بانک آسیا', 'Asia Bank', 30],
        ['BKHAVARMIANEH', 'بانک خاورمیانه', 'Middle East Bank', 31],
        ['BAYANDEH', 'بانک آینده', 'Ayandeh Bank', 32],
        ['BIRAN_VENEZUELA', 'بانک مشترک ایران و ونزوئلا', 'Iran-Venezuela Bank', 33],

        // Credit institutions (موسسات اعتباری)
        ['CMELAL', 'موسسه ملل', 'Melal Credit Institution', 40],
        ['CKOSAR', 'موسسه کوثر', 'Kosar Credit Institution', 41],
        ['CNOOR', 'موسسه نور', 'Noor Credit Institution', 42],

        // Other payment methods
        ['CASH', 'نقدی', 'Cash', 50],
        ['CHEQUE', 'چک', 'Cheque', 51],
        ['TRANSFER', 'انتقال وجه', 'Wire Transfer', 52],
        ['OTHER', 'سایر', 'Other', 99],
    ];

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO _iranian_banks (code, name_fa, name_en, sort_order, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");

    $insertedCount = 0;
    foreach ($banks as $bank) {
        $insertStmt->execute($bank);
        if ($insertStmt->rowCount() > 0) {
            $insertedCount++;
        }
    }

    echo "   ✓ Inserted $insertedCount Iranian banks\n\n";

    // Verify tables
    echo "===========================================\n";
    echo "Verification:\n";
    echo "===========================================\n";

    $stmt = $pdo->query("SHOW TABLES LIKE '_reseller_payments'");
    echo "_reseller_payments table: " . ($stmt->rowCount() > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";

    $stmt = $pdo->query("SHOW TABLES LIKE '_iranian_banks'");
    echo "_iranian_banks table: " . ($stmt->rowCount() > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM _iranian_banks");
    $count = $stmt->fetch()['cnt'];
    echo "Total banks in database: $count\n";

    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
