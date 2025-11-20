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

?>
