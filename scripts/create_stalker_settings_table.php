<?php
/**
 * Create Stalker Portal Settings Table
 * This script creates the _stalker_settings table for storing Stalker Portal connection settings
 */

include(__DIR__ . '/../config.php');

try {
    $pdo = new PDO("mysql:host=$ub_db_host;dbname=$ub_main_db;charset=utf8mb4", $ub_db_username, $ub_db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create stalker_settings table
    $sql = "CREATE TABLE IF NOT EXISTS `_stalker_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    echo "Table _stalker_settings created successfully.\n";

    // Insert default values from config.php (only if not already set)
    $defaults = [
        'server_address' => $SERVER_1_ADDRESS ?? 'http://81.12.70.4',
        'server_2_address' => $SERVER_2_ADDRESS ?? 'http://81.12.70.4',
        'api_username' => $WEBSERVICE_USERNAME ?? 'admin',
        'api_password' => $WEBSERVICE_PASSWORD ?? '',
        'api_base_url' => $WEBSERVICE_BASE_URL ?? 'http://81.12.70.4/stalker_portal/api/',
        'api_2_base_url' => $WEBSERVICE_2_BASE_URL ?? 'http://81.12.70.4/stalker_portal/api/'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO _stalker_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
        echo "Setting '$key' initialized.\n";
    }

    echo "\nStalker settings table setup complete!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
