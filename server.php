<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use App\PaymentService;

$server = new Server("0.0.0.0", 9501);

$server->on("start", static function () {
    echo "Servidor Swoole rodando em http://localhost:9501\n";
});

$server->on("request", function (Request $request, Response $response) {
    $redis = new Redis();
    $redis->connect('redis');

    $method = $request->server['request_method'];
    $uri = $request->server['request_uri'];

    if ($method === 'POST' && $uri === '/payments') {
        $data = json_decode($request->rawContent());

        if (!$data->correlationId || !$data->amount) {
            $response->status(400);
            $response->end("Bad Request");
            return;
        }

        $redis->rpush('payments-queue', $data);
        $dataResponse = json_encode($data);

        $response->header('Content-Type', 'application/json');
        $response->status(202);
        $response->end($dataResponse);
    }

    $response->status(404);
    $response->end("Not found");
});

$server->start();
