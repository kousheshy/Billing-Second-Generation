<?php
/**
 * Get VAPID Public Key API (v1.11.40)
 *
 * Returns the public key needed for push notification subscription
 */

header('Content-Type: application/json');

// VAPID Public Key - must match the one in push_helper.php (v1.11.46)
$vapidPublicKey = 'BI8Gdm9PK3LeO2mvhV9yt5NzIBFhSrlKRbfHbaDFfvMqJGmI0T0R-huUK7yeo6aPoasqBnu7SLjNUjqb4J_j5L0';

echo json_encode([
    'error' => 0,
    'publicKey' => $vapidPublicKey
]);
?>
