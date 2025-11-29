<?php
/**
 * Cron Job: Send Expiry Reminder Emails
 *
 * This script should be run daily via cron to send expiry reminder emails.
 * It implements a multi-stage reminder system:
 * - Stage 1: 7 days before expiry
 * - Stage 2: 3 days before expiry
 * - Stage 3: 1 day before expiry
 * - Stage 4: On expiry date (account expired)
 *
 * Usage: Add to crontab:
 * 0 9 * * * /usr/bin/php /var/www/showbox/cron/cron_send_expiry_mail.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    die('This script can only be run from command line or with valid cron key');
}

// Configuration
define('REMINDER_STAGES', [
    1 => 7,   // Stage 1: 7 days before
    2 => 3,   // Stage 2: 3 days before
    3 => 1,   // Stage 3: 1 day before
    4 => 0    // Stage 4: On expiry date
]);

// Include required files
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../api/mail_helper.php');

// Set timezone
date_default_timezone_set('Asia/Tehran');

echo "=== Expiry Email Reminder Cron Job ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$stats = [
    'total_processed' => 0,
    'emails_sent' => 0,
    'already_sent' => 0,
    'no_email' => 0,
    'failed' => 0
];

try {
    // Get all accounts with email that haven't been renewed
    // We need to check accounts expiring at each stage
    foreach (REMINDER_STAGES as $stage => $days_before) {
        $target_date = date('Y-m-d', strtotime("+$days_before days"));

        echo "Processing Stage $stage (expires in $days_before days - $target_date):\n";

        // Find accounts expiring on target date with email
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.full_name,
                a.email,
                a.mac,
                a.expire_billing_date,
                a.reseller,
                a.status
            FROM _accounts a
            WHERE DATE(a.expire_billing_date) = ?
              AND a.email IS NOT NULL
              AND a.email != ''
              AND a.status = 1
        ");
        $stmt->execute([$target_date]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "  Found " . count($accounts) . " accounts expiring on $target_date\n";

        foreach ($accounts as $account) {
            $stats['total_processed']++;

            // Check if reminder already sent for this stage
            $checkStmt = $pdo->prepare("
                SELECT id FROM _mail_reminder_tracking
                WHERE account_id = ? AND reminder_stage = ?
            ");
            $checkStmt->execute([$account['id'], $stage]);

            if ($checkStmt->fetch()) {
                $stats['already_sent']++;
                echo "    - {$account['full_name']} ({$account['email']}): Already sent stage $stage\n";
                continue;
            }

            // Get the account owner ID (reseller or system admin)
            $owner_id = $account['reseller'] ?: 1;

            // Send the email
            try {
                $result = sendExpiryMail(
                    $pdo,
                    $owner_id,
                    $account['full_name'],
                    $account['email'],
                    $account['mac'],
                    $account['expire_billing_date'],
                    $account['id'],
                    $stage
                );

                if ($result) {
                    $stats['emails_sent']++;
                    echo "    + {$account['full_name']} ({$account['email']}): Sent stage $stage email\n";
                } else {
                    $stats['failed']++;
                    echo "    x {$account['full_name']} ({$account['email']}): Failed to send\n";
                }
            } catch (Exception $e) {
                $stats['failed']++;
                echo "    x {$account['full_name']} ({$account['email']}): Error - " . $e->getMessage() . "\n";
            }

            // Small delay between emails to avoid overwhelming SMTP server
            usleep(200000); // 200ms
        }

        echo "\n";
    }

    // Clean up old tracking records (older than 30 days)
    $pdo->exec("DELETE FROM _mail_reminder_tracking WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

    // Reset tracking for renewed accounts
    // If an account was renewed (expiry date pushed forward), remove their tracking
    $pdo->exec("
        DELETE mrt FROM _mail_reminder_tracking mrt
        INNER JOIN _accounts a ON mrt.account_id = a.id
        WHERE a.expire_billing_date > DATE_ADD(NOW(), INTERVAL 7 DAY)
    ");

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Print summary
echo "=== Summary ===\n";
echo "Total processed: {$stats['total_processed']}\n";
echo "Emails sent: {$stats['emails_sent']}\n";
echo "Already sent: {$stats['already_sent']}\n";
echo "Failed: {$stats['failed']}\n";
echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
?>
