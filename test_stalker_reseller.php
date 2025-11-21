<?php
/**
 * Test script to verify Stalker API reseller integration
 * This script tests:
 * 1. If Stalker API accepts the 'reseller' parameter when creating accounts
 * 2. If Stalker API returns the 'reseller' field when fetching accounts
 */

include('config.php');
include('api.php');

echo "=== STALKER RESELLER INTEGRATION TEST ===\n\n";

// Test 1: Check what fields Stalker returns for existing accounts
echo "TEST 1: Fetching accounts from Stalker to check field structure\n";
echo "---------------------------------------------------------------\n";

$case = 'accounts';
$op = "GET";
$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);
$decoded = json_decode($res);

if($decoded->status == 'OK') {
    echo "✓ Successfully fetched accounts from Stalker\n";
    $total = isset($decoded->total_items) ? $decoded->total_items : count($decoded->results);
    echo "Total accounts: " . $total . "\n\n";

    // Show structure of first account
    if(isset($decoded->results[0])) {
        echo "First account fields:\n";
        $first_account = (array)$decoded->results[0];
        foreach($first_account as $key => $value) {
            $display_value = is_string($value) ? $value : json_encode($value);
            if(strlen($display_value) > 50) {
                $display_value = substr($display_value, 0, 50) . "...";
            }
            echo "  - $key: $display_value\n";
        }

        // Check if 'reseller' field exists
        echo "\n";
        if(isset($first_account['reseller'])) {
            echo "✓ RESELLER FIELD EXISTS in Stalker response!\n";
            echo "  Value: " . ($first_account['reseller'] ?? 'NULL') . "\n";
        } else {
            echo "✗ RESELLER FIELD DOES NOT EXIST in Stalker response\n";
            echo "  This means Stalker is not storing/returning reseller information\n";
        }
    }
} else {
    echo "✗ Failed to fetch accounts: " . ($decoded->error ?? 'Unknown error') . "\n";
}

echo "\n\n";

// Test 2: Try to create a test account with reseller parameter
echo "TEST 2: Checking if Stalker accepts 'reseller' parameter\n";
echo "---------------------------------------------------------------\n";
echo "Creating a test account with reseller=99 (test value)\n";

$test_username = 'test_reseller_' . time();
$test_mac = '00:1A:79:' . sprintf('%02X:%02X:%02X', rand(0, 255), rand(0, 255), rand(0, 255));
$test_data = 'login=' . $test_username .
             '&password=test123' .
             '&full_name=Test Reseller Account' .
             '&stb_mac=' . $test_mac .
             '&status=1' .
             '&reseller=99';

echo "Data being sent: $test_data\n\n";

$case = 'accounts';
$op = "POST";
$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, $test_data);
$decoded = json_decode($res);

if($decoded->status == 'OK') {
    echo "✓ Account created successfully\n";

    // Now fetch this account to see if reseller was stored
    echo "\nFetching the created account to verify reseller value...\n";
    $op = "GET";
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $test_mac, null);
    $decoded = json_decode($res);

    if($decoded->status == 'OK') {
        $account = (array)$decoded->results;
        echo "✓ Account fetched successfully\n";

        if(isset($account['reseller'])) {
            echo "✓ RESELLER FIELD EXISTS!\n";
            echo "  Expected value: 99\n";
            echo "  Actual value: " . ($account['reseller'] ?? 'NULL') . "\n";

            if($account['reseller'] == 99) {
                echo "  ✓✓ SUCCESS! Stalker stored and returned the reseller value!\n";
            } else {
                echo "  ✗ WARNING: Reseller value doesn't match (Stalker may have ignored it)\n";
            }
        } else {
            echo "✗ RESELLER FIELD DOES NOT EXIST\n";
            echo "  This means Stalker ignored the reseller parameter\n";
        }
    }

    // Clean up: delete the test account
    echo "\nCleaning up test account...\n";
    $op = "DELETE";
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, $test_mac, null);
    $decoded = json_decode($res);
    if($decoded->status == 'OK') {
        echo "✓ Test account deleted\n";
    }
} else {
    echo "✗ Failed to create test account: " . ($decoded->error ?? 'Unknown error') . "\n";
}

echo "\n\n=== TEST COMPLETE ===\n\n";
echo "SUMMARY:\n";
echo "--------\n";
echo "If the reseller field exists and the value is stored correctly,\n";
echo "then the Stalker integration is working properly.\n\n";
echo "If the reseller field does NOT exist, you need to:\n";
echo "1. Add a 'reseller' column to Stalker's users table (INT, nullable)\n";
echo "2. Modify Stalker's API to accept and return the reseller parameter\n";
echo "3. Or contact Stalker support to enable this feature\n";

?>
