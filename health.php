<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Coroutine\Http\Client;


function getServiceHealth(): array
{
    $host = 'payment-processor-default';


    $client = new Client($host, 8080);
    $client->set([
        'timeout' => 5.0,
    ]);

    $client->setHeaders([
        'Host' => $host,
        'Accept' => 'application/json'
    ]);

    $response = $client->get("/payments/service-health");

    $data = [];
    if ($response && $client->statusCode >= 200 && $client->statusCode < 300) {
        $decoded = json_decode($client->body, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $client->close();

    return $data;
}