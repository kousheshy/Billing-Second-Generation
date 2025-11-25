<?php
/**
 * Initialize SMS Settings for Existing Resellers
 * Version: 1.10.2
 *
 * This script initializes SMS settings and templates for all existing resellers
 * who don't have SMS configuration yet. This ensures all resellers can
 * automatically send welcome SMS when they add new accounts.
 *
 * Run this once after updating to v1.10.2
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../api/sms_helper.php');

$host = $ub_db_host;
$db   = $ub_main_db;
$user = $ub_db_username;
$pass = $ub_db_password;
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

echo "════════════════════════════════════════════════════════════════\n";
echo "  Initializing SMS Settings for Existing Resellers\n";
echo "  Version: 1.10.2\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);

    // Get all resellers (super_user = 0)
    $stmt = $pdo->prepare('SELECT id, username, name FROM _users WHERE super_user = 0 ORDER BY id ASC');
    $stmt->execute();
    $resellers = $stmt->fetchAll();

    $total_resellers = count($resellers);
    $initialized = 0;
    $already_initialized = 0;

    echo "Found $total_resellers resellers in the system.\n\n";

    foreach ($resellers as $reseller) {
        echo "Processing: {$reseller['username']} (ID: {$reseller['id']}, Name: {$reseller['name']})...\n";

        // Check if SMS settings already exist
        $stmt = $pdo->prepare('SELECT id FROM _sms_settings WHERE user_id = ?');
        $stmt->execute([$reseller['id']]);
        if ($stmt->fetch()) {
            echo "  ✓ Already initialized (skipping)\n\n";
            $already_initialized++;
            continue;
        }

        // Initialize SMS settings and templates
        if (initializeResellerSMS($pdo, $reseller['id'])) {
            echo "  ✓ SMS settings and templates created successfully\n";
            echo "    - 1 SMS settings record\n";
            echo "    - 4 default templates (Expiry Reminder, Welcome, Renewal, Payment)\n\n";
            $initialized++;
        } else {
            echo "  ✗ Failed to initialize SMS settings\n\n";
        }
    }

    echo "════════════════════════════════════════════════════════════════\n";
    echo "  ✅ INITIALIZATION COMPLETE!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "Summary:\n";
    echo "  Total resellers: $total_resellers\n";
    echo "  Already initialized: $already_initialized\n";
    echo "  Newly initialized: $initialized\n\n";

    if ($initialized > 0) {
        echo "📝 Next Steps:\n";
        echo "  1. Resellers can now configure their SMS API in the Messaging tab\n";
        echo "  2. After adding API token and sender number, welcome SMS will be sent automatically\n";
        echo "  3. Test by adding a new account with a phone number\n\n";
    }

    echo "💡 How It Works:\n";
    echo "  - When admin adds account for reseller → Uses reseller's SMS settings\n";
    echo "  - When reseller adds own account → Uses their own SMS settings\n";
    echo "  - When new reseller is created → SMS automatically initialized\n";
    echo "  - Resellers must configure API token to enable SMS sending\n\n";

} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
