<?php

session_start();
include('config.php');
include('api.php');

// Check authentication
if(!isset($_SESSION['login']) || $_SESSION['login'] != 1) {
    $response['error'] = 1;
    $response['err_msg'] = 'Not logged in';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Check if user is super admin
$session_username = $_SESSION['username'];

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

$pdo = new PDO($dsn, $user, $pass, $opt);

$stmt = $pdo->prepare('SELECT super_user, permissions FROM _users WHERE username = ?');
$stmt->execute([$session_username]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is admin or reseller admin
// Format: can_edit|can_add|is_reseller_admin|can_delete|can_control_stb|can_toggle_status|can_access_messaging
$permissions = explode('|', $user_info['permissions'] ?? '0|0|0|0|0|0|0');
$is_reseller_admin = isset($permissions[2]) && $permissions[2] === '1';

if($user_info['super_user'] != 1 && !$is_reseller_admin) {
    $response['error'] = 1;
    $response['err_msg'] = 'Permission denied. Admin or Reseller Admin only.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Themes list from your Stalker Portal server
$default_themes = [
    ['id' => 'HenSoft-TV Realistic-Centered SHOWBOX', 'name' => 'HenSoft-TV Realistic-Centered SHOWBOX (Default)', 'is_default' => true],
    ['id' => 'HenSoft-TV Realistic-Centered', 'name' => 'HenSoft-TV Realistic-Centered'],
    ['id' => 'HenSoft-TV Realistic-Dark', 'name' => 'HenSoft-TV Realistic-Dark'],
    ['id' => 'HenSoft-TV Realistic-Light', 'name' => 'HenSoft-TV Realistic-Light'],
    ['id' => 'cappuccino', 'name' => 'Cappuccino'],
    ['id' => 'digital', 'name' => 'Digital'],
    ['id' => 'emerald', 'name' => 'Emerald'],
    ['id' => 'graphite', 'name' => 'Graphite'],
    ['id' => 'ocean_blue', 'name' => 'Ocean Blue'],
];

// Try to fetch themes from Stalker Portal API (if endpoint exists)
// This is an attempt to get dynamic theme list
try {
    // Attempt 1: Try profiles endpoint (might have theme list)
    $case = 'users';
    $op = "GET";
    $res = api_send_request($WEBSERVICE_URLs[$case], $WEBSERVICE_USERNAME, $WEBSERVICE_PASSWORD, $case, $op, null, null);

    $decoded = json_decode($res);

    // If API returns data, try to extract themes
    // Note: This is exploratory - Stalker API might not have a direct theme endpoint
    // In that case, we'll use the default list above

    // For now, use default themes
    $themes = $default_themes;

} catch(Exception $e) {
    // If API fails, use default themes
    error_log("get_themes.php: Failed to fetch from API, using default list. Error: " . $e->getMessage());
    $themes = $default_themes;
}

// Alternatively, try to read themes from Stalker Portal filesystem if accessible
// This would require SSH access or direct filesystem access to:
// /var/www/stalker_portal/server/themes/ or similar path
// For security reasons, this is commented out by default

/*
$stalker_themes_path = '/var/www/stalker_portal/server/themes/';
if(is_dir($stalker_themes_path)) {
    $theme_dirs = array_diff(scandir($stalker_themes_path), array('.', '..'));
    $filesystem_themes = [];
    foreach($theme_dirs as $dir) {
        if(is_dir($stalker_themes_path . $dir)) {
            $filesystem_themes[] = [
                'id' => $dir,
                'name' => ucwords(str_replace(['_', '-'], ' ', $dir))
            ];
        }
    }
    if(!empty($filesystem_themes)) {
        $themes = $filesystem_themes;
    }
}
*/

$response['error'] = 0;
$response['themes'] = $themes;

header('Content-Type: application/json');
echo json_encode($response);

?>
