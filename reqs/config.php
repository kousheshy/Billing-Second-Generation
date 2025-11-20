<?php


//////////////////System

date_default_timezone_set('Asia/Tehran');
$admins_only = false;
$PANEL_NAME = "ShowBox";
$WELCOME_MSG = "Welcome to ShowBox - 24/7 Support and Movie Order on Whatsapp: 00447736932888 | Instagram: @ShowBoxAdmin";

//////////////////Database

$ub_main_db="showboxt_panel";

$ub_db_host="localhost";
$ub_db_username="root";
$ub_db_password="";


//////////////////Bank terminal



//////////////////api


$SERVER_1_ADDRESS = "http://81.12.70.4";
$SERVER_2_ADDRESS = "http://81.12.70.4";

$WEBSERVICE_USERNAME = "admin";
$WEBSERVICE_PASSWORD = "kamitest13579";
$WEBSERVICE_BASE_URL = "http://81.12.70.4/stalker_portal/api/";
$WEBSERVICE_2_BASE_URL = "http://81.12.70.4/stalker_portal/api/";

$WEBSERVICE_URLs['stb'] = $WEBSERVICE_BASE_URL."stb/";
$WEBSERVICE_URLs['accounts'] = $WEBSERVICE_BASE_URL."accounts/";
$WEBSERVICE_URLs['users'] = $WEBSERVICE_BASE_URL."users/";
$WEBSERVICE_URLs['stb_msg'] = $WEBSERVICE_BASE_URL."stb_msg/";
$WEBSERVICE_URLs['send_event'] = $WEBSERVICE_BASE_URL."send_event/";
$WEBSERVICE_URLs['stb_modules'] = $WEBSERVICE_BASE_URL."stb_modules/";
$WEBSERVICE_URLs['itv'] = $WEBSERVICE_BASE_URL."itv/";
$WEBSERVICE_URLs['itv_subscription'] = $WEBSERVICE_BASE_URL."itv_subscription/";
$WEBSERVICE_URLs['tariffs'] = $WEBSERVICE_BASE_URL."tariffs/";
$WEBSERVICE_URLs['services_plan'] = $WEBSERVICE_BASE_URL."services_plan/";
$WEBSERVICE_URLs['account_subscription'] = $WEBSERVICE_BASE_URL."account_subscription/";
$WEBSERVICE_URLs['reseller'] = $WEBSERVICE_BASE_URL."reseller/";


$WEBSERVICE_2_URLs['stb'] = $WEBSERVICE_2_BASE_URL."stb/";
$WEBSERVICE_2_URLs['accounts'] = $WEBSERVICE_2_BASE_URL."accounts/";
$WEBSERVICE_2_URLs['users'] = $WEBSERVICE_2_BASE_URL."users/";
$WEBSERVICE_2_URLs['stb_msg'] = $WEBSERVICE_2_BASE_URL."stb_msg/";
$WEBSERVICE_2_URLs['send_event'] = $WEBSERVICE_2_BASE_URL."send_event/";
$WEBSERVICE_2_URLs['stb_modules'] = $WEBSERVICE_2_BASE_URL."stb_modules/";
$WEBSERVICE_2_URLs['itv'] = $WEBSERVICE_2_BASE_URL."itv/";
$WEBSERVICE_2_URLs['itv_subscription'] = $WEBSERVICE_2_BASE_URL."itv_subscription/";
$WEBSERVICE_2_URLs['tariffs'] = $WEBSERVICE_2_BASE_URL."tariffs/";
$WEBSERVICE_2_URLs['services_plan'] = $WEBSERVICE_2_BASE_URL."services_plan/";
$WEBSERVICE_2_URLs['account_subscription'] = $WEBSERVICE_2_BASE_URL."account_subscription/";
$WEBSERVICE_2_URLs['reseller'] = $WEBSERVICE_2_BASE_URL."reseller/";




?>