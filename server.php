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
    $method = $request->server['request_method'];
    $uri = $request->server['request_uri'];

    if ($method === 'POST' && $uri === '/payments') {
        PaymentService::handler($request, $response);
    }

    $response->status(404);
    $response->end("Not found");
});

$server->start();
