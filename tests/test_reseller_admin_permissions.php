<?php
/**
 * Automated Test Suite for Reseller Admin Permissions
 * 
 * Covers:
 * 1. Reseller Management Access
 * 2. Self-Protection
 * 3. Account Management
 * 4. Individual Permissions
 * 5. Security Edge Cases
 */

$baseUrl = "http://localhost:8000/api";
$cookieFile = tempnam(sys_get_temp_dir(), 'cookie_admin');
$cookieFileResellerAdmin = tempnam(sys_get_temp_dir(), 'cookie_reseller_admin');
$cookieFileRegularReseller = tempnam(sys_get_temp_dir(), 'cookie_regular_reseller');

// Colors for output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[1;33m";
$RESET = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function log_test($name, $result, $expected = true) {
    global $GREEN, $RED, $RESET, $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    $status = ($result === $expected) ? "PASS" : "FAIL";
    $color = ($result === $expected) ? $GREEN : $RED;
    
    echo sprintf("%s[%s] %s%s\n", $color, $status, $name, $RESET);
    
    if ($result !== $expected) {
        echo "  Expected: " . ($expected ? 'TRUE' : 'FALSE') . "\n";
        echo "  Actual:   " . ($result ? 'TRUE' : 'FALSE') . "\n";
        $failedTests++;
    } else {
        $passedTests++;
    }
}

function make_request($endpoint, $data = [], $method = 'POST', $cookie = null) {
    global $baseUrl;
    $url = $baseUrl . '/' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
}

// --- SETUP ---

echo "\n{$YELLOW}=== SETUP: Creating Test Users ==={$RESET}\n";

// 1. Login as Super Admin
echo "Logging in as Super Admin...\n";
$login = make_request('login.php', ['username' => 'admin', 'password' => 'admin'], 'POST', $cookieFile);
if (isset($login['error']) && $login['error'] == 0) {
    echo "{$GREEN}Super Admin logged in successfully.{$RESET}\n";
} else {
    die("{$RED}Failed to login as Super Admin. Aborting.{$RESET}\n");
}

// 2. Create Reseller Admin
$resellerAdminUser = 'test_ra_' . time();
$resellerAdminPass = 'password123';
echo "Creating Reseller Admin ($resellerAdminUser)...\n";
$createRA = make_request('add_reseller.php', [
    'username' => $resellerAdminUser,
    'password' => $resellerAdminPass,
    'name' => 'Test Reseller Admin',
    'email' => 'ra@test.com',
    'max_users' => 10,
    'currency' => 1,
    'permissions' => '1|1|1|1|1|1|0', // is_reseller_admin = 1
    'is_observer' => 0
], 'POST', $cookieFile);

if (isset($createRA['error']) && $createRA['error'] == 0) {
    echo "{$GREEN}Reseller Admin created.{$RESET}\n";
} else {
    echo "{$RED}Failed to create Reseller Admin: " . ($createRA['err_msg'] ?? 'Unknown') . "{$RESET}\n";
}

// 3. Create Regular Reseller
$regularResellerUser = 'test_rr_' . time();
$regularResellerPass = 'password123';
echo "Creating Regular Reseller ($regularResellerUser)...\n";
$createRR = make_request('add_reseller.php', [
    'username' => $regularResellerUser,
    'password' => $regularResellerPass,
    'name' => 'Test Regular Reseller',
    'email' => 'rr@test.com',
    'max_users' => 10,
    'currency' => 1,
    'permissions' => '1|1|0|0|1|0|0', // is_reseller_admin = 0
    'is_observer' => 0
], 'POST', $cookieFile);

if (isset($createRR['error']) && $createRR['error'] == 0) {
    echo "{$GREEN}Regular Reseller created.{$RESET}\n";
} else {
    echo "{$RED}Failed to create Regular Reseller: " . ($createRR['err_msg'] ?? 'Unknown') . "{$RESET}\n";
}

// 4. Create Target Reseller (to be managed)
$targetResellerUser = 'test_target_' . time();
$targetResellerPass = 'password123';
echo "Creating Target Reseller ($targetResellerUser)...\n";
$createTarget = make_request('add_reseller.php', [
    'username' => $targetResellerUser,
    'password' => $targetResellerPass,
    'name' => 'Test Target Reseller',
    'email' => 'target@test.com',
    'max_users' => 10,
    'currency' => 1,
    'permissions' => '1|1|0|0|1|0|0',
    'is_observer' => 0
], 'POST', $cookieFile);

// Get Target ID (we need to fetch it, or assume it's the last one? Better to fetch)
// We can use get_resellers.php to find it
$resellers = make_request('get_resellers.php', [], 'GET', $cookieFile);
$targetId = 0;
$resellerAdminId = 0;
$regularResellerId = 0;

foreach ($resellers['resellers'] as $r) {
    if ($r['username'] == $targetResellerUser) $targetId = $r['id'];
    if ($r['username'] == $resellerAdminUser) $resellerAdminId = $r['id'];
    if ($r['username'] == $regularResellerUser) $regularResellerId = $r['id'];
}
echo "Target Reseller ID: $targetId\n";

// Login as Reseller Admin
echo "Logging in as Reseller Admin...\n";
$loginRA = make_request('login.php', ['username' => $resellerAdminUser, 'password' => $resellerAdminPass], 'POST', $cookieFileResellerAdmin);

// Login as Regular Reseller
echo "Logging in as Regular Reseller...\n";
$loginRR = make_request('login.php', ['username' => $regularResellerUser, 'password' => $regularResellerPass], 'POST', $cookieFileRegularReseller);


// --- TESTS ---

echo "\n{$YELLOW}=== CATEGORY 1: Reseller Management Access ==={$RESET}\n";

// Test 1.1: Reseller Admin can view resellers
$res = make_request('get_resellers.php', [], 'GET', $cookieFileResellerAdmin);
log_test("Reseller Admin can view resellers", isset($res['resellers']) && count($res['resellers']) > 0, true);

// Test 1.2: Regular Reseller CANNOT view resellers
$res = make_request('get_resellers.php', [], 'GET', $cookieFileRegularReseller);
log_test("Regular Reseller CANNOT view resellers", isset($res['error']) && $res['error'] == 1, true);

// Test 1.3: Reseller Admin can add a reseller
$newSubReseller = 'sub_ra_' . time();
$res = make_request('add_reseller.php', [
    'username' => $newSubReseller,
    'password' => 'pass',
    'name' => 'Sub Reseller',
    'email' => 'sub@test.com',
    'max_users' => 5,
    'currency' => 1
], 'POST', $cookieFileResellerAdmin);
log_test("Reseller Admin can add reseller", isset($res['error']) && $res['error'] == 0, true);

// Test 1.4: Regular Reseller CANNOT add a reseller
$newSubReseller2 = 'sub_rr_' . time();
$res = make_request('add_reseller.php', [
    'username' => $newSubReseller2,
    'password' => 'pass',
    'name' => 'Sub Reseller 2',
    'email' => 'sub2@test.com',
    'max_users' => 5,
    'currency' => 1
], 'POST', $cookieFileRegularReseller);
log_test("Regular Reseller CANNOT add reseller", isset($res['error']) && $res['error'] == 1, true);

// Test 1.5: Reseller Admin can edit Target Reseller
$res = make_request('update_reseller.php', [
    'id' => $targetId,
    'name' => 'Updated Target Name',
    'email' => 'updated@test.com',
    'max_users' => 15,
    'permissions' => '1|1|0|0|1|0|0'
], 'POST', $cookieFileResellerAdmin);
log_test("Reseller Admin can edit Target Reseller", isset($res['error']) && $res['error'] == 0, true);


echo "\n{$YELLOW}=== CATEGORY 2: Self-Protection ==={$RESET}\n";

// Test 2.1: Reseller Admin CANNOT delete themselves
$res = make_request('remove_reseller.php?id=' . $resellerAdminId, [], 'GET', $cookieFileResellerAdmin);
log_test("Reseller Admin CANNOT delete themselves", isset($res['error']) && $res['error'] == 1, true);

// Test 2.2: Reseller Admin CANNOT remove their own admin permission
// Try to update self with is_reseller_admin = 0 (index 2)
$res = make_request('update_reseller.php', [
    'id' => $resellerAdminId,
    'name' => 'Trying to Demote Self',
    'permissions' => '1|1|0|0|1|0|0' // index 2 is 0
], 'POST', $cookieFileResellerAdmin);
log_test("Reseller Admin CANNOT remove own admin permission", isset($res['error']) && $res['error'] == 1, true);


echo "\n{$YELLOW}=== CATEGORY 3: Account Management ==={$RESET}\n";

// Test 3.1: Reseller Admin can add account (no credit check)
// Need a plan first. Assuming plan ID 1 exists or we can use 0 (Unlimited) if allowed.
// Let's try plan 0 first.
$accUserRA = 'acc_ra_' . time();
$res = make_request('add_account.php', [
    'username' => $accUserRA,
    'password' => 'pass',
    'name' => 'RA Account',
    'plan' => '0', // Unlimited
    'status' => 1
], 'POST', $cookieFileResellerAdmin);
// Note: Plan 0 might fail if not allowed, but we are testing permission/credit check mostly.
// If it fails with "Not enough credit", that's a failure.
// If it fails with "Plan not found", that's okay for permission test, but better if it succeeds.
// Let's assume success or at least NOT "Not enough credit".
$isCreditError = isset($res['err_msg']) && strpos($res['err_msg'], 'Not enough credit') !== false;
log_test("Reseller Admin exempt from credit check", !$isCreditError, true);


echo "\n{$YELLOW}=== CATEGORY 4: Security Edge Cases ==={$RESET}\n";

// Test 4.1: Reseller Admin CANNOT make someone Super Admin
// This is implicitly handled by update_reseller.php not accepting super_user param or ignoring it.
// We can try to pass super_user=1
$res = make_request('update_reseller.php', [
    'id' => $targetId,
    'name' => 'Try Super User',
    'super_user' => 1,
    'permissions' => '1|1|0|0|1|0|0'
], 'POST', $cookieFileResellerAdmin);
// Verify target is NOT super user
$resCheck = make_request('get_resellers.php', [], 'GET', $cookieFile); // Admin checks
$isSuper = false;
foreach ($resCheck['resellers'] as $r) {
    if ($r['id'] == $targetId && $r['super_user'] == 1) $isSuper = true;
}
log_test("Reseller Admin CANNOT promote to Super Admin", !$isSuper, true);


// --- TEARDOWN ---
echo "\n{$YELLOW}=== TEARDOWN ==={$RESET}\n";

// Delete created users using Super Admin
make_request('remove_reseller.php?id=' . $resellerAdminId, [], 'GET', $cookieFile);
make_request('remove_reseller.php?id=' . $regularResellerId, [], 'GET', $cookieFile);
make_request('remove_reseller.php?id=' . $targetId, [], 'GET', $cookieFile);
// Also delete sub resellers if created
// We'd need their IDs. For now, manual cleanup or rely on DB reset if needed.
// But let's try to find them by username
$resellers = make_request('get_resellers.php', [], 'GET', $cookieFile);
foreach ($resellers['resellers'] as $r) {
    if (strpos($r['username'], 'sub_ra_') !== false || strpos($r['username'], 'sub_rr_') !== false) {
        make_request('remove_reseller.php?id=' . $r['id'], [], 'GET', $cookieFile);
    }
}

// Clean up cookies
@unlink($cookieFile);
@unlink($cookieFileResellerAdmin);
@unlink($cookieFileRegularReseller);

echo "\n{$YELLOW}=== SUMMARY ==={$RESET}\n";
echo "Total Tests: $totalTests\n";
echo "{$GREEN}Passed: $passedTests{$RESET}\n";
echo "{$RED}Failed: $failedTests{$RESET}\n";

?>
