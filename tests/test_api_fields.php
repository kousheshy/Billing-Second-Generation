<?php
session_start();
include(__DIR__ . '/../config.php');
include(__DIR__ . '/../api/api.php');

// Get one account from Stalker Portal to see all fields
$case = 'accounts';
$op = "GET";

$res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);
$decoded = json_decode($res);

header('Content-Type: application/json');

if($decoded->status == 'OK' && isset($decoded->results)) {
    $accounts = is_array($decoded->results) ? $decoded->results : [$decoded->results];

    if(count($accounts) > 0) {
        // Get first account to see all available fields
        $first_account = $accounts[0];

        echo json_encode([
            'success' => true,
            'total_accounts' => count($accounts),
            'first_account_fields' => json_decode(json_encode($first_account), true),
            'has_created' => isset($first_account->created),
            'has_create_date' => isset($first_account->create_date),
            'has_date_created' => isset($first_account->date_created),
            'has_add_date' => isset($first_account->add_date),
            'created_value' => $first_account->created ?? null,
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'No accounts found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'API call failed', 'response' => $decoded]);
}
?>
