<?php

session_start();
session_destroy();

$response['error'] = 0;
$response['message'] = 'Logged out successfully';

header('Content-Type: application/json');
echo json_encode($response);

?>
