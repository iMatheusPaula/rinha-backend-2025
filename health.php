<?php
require_once __DIR__ . '/vendor/autoload.php';

function getServiceHealth()
{
    $host = 'payment-processor-default:8080';
    $path = '/payments/service-health';

    $request = curl_init($host.$path);

    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = [];

    if (! curl_errno($request)) {
        $response = json_decode(curl_exec($request), false);
    }

    curl_close($request);
    return $response;
}