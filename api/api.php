<?php

function api_send_request($url, $username, $password, $case, $op, $mac, $data)
{
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, $username.":".$password);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $op);
    curl_setopt($curl, CURLOPT_POSTREDIR, 3);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);

    if($mac != null)
    {
        curl_setopt($curl, CURLOPT_URL, $url.$mac);
    }else
    {
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}

/**
 * Send message to STB device via Stalker Portal API
 *
 * @param string $mac MAC address of the device
 * @param string $message Message text to send
 * @param array $server_config Server configuration array with WEBSERVICE_URLs, username, password
 * @return array Response with 'error' and 'err_msg' keys
 */
function send_message($mac, $message, $server_config) {
    $data = 'msg=' . urlencode($message);
    $case = 'stb_msg';
    $op = "POST";

    try {
        $res = api_send_request(
            $server_config['WEBSERVICE_URLs'][$case],
            $server_config['WEBSERVICE_USERNAME'],
            $server_config['WEBSERVICE_PASSWORD'],
            $case,
            $op,
            $mac,
            $data
        );

        $decoded = json_decode($res);

        if(isset($decoded->status) && $decoded->status == 'OK') {
            return ['error' => 0, 'err_msg' => ''];
        } else {
            return ['error' => 1, 'err_msg' => $decoded->error ?? 'Unknown error'];
        }
    } catch(Exception $e) {
        return ['error' => 1, 'err_msg' => $e->getMessage()];
    }
}

?>
