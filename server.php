<?php

declare(strict_types=1);

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

$redis = null;

$server = new Server("0.0.0.0", 9501);

$server->on("start", static function () {
    echo "Servidor Swoole rodando em http://localhost:9501\n";
});

$server->on("workerStart", static function (Server $server, int $workerId) {
    global $redis;

    echo "Worker #{$workerId} iniciado.\n";

    $redis = new Redis();
    $redis->connect('redis');
});

$server->on("request", function (Request $request, Response $response) use ($redis) {
    global $redis;
    $method = $request->server['request_method'];
    $uri = $request->server['request_uri'];

    if ($method === 'POST' && $uri === '/payments') {
        $data = json_decode($request->rawContent());

        if (!$data->correlationId || !$data->amount) {
            $response->status(400);
            $response->end("Bad Request");
            return;
        }

        $dataEncode = json_encode($data);

        $redis->lpush('payments-queue', $dataEncode);

        $response->header('Content-Type', 'application/json');
        $response->status(202);
        $response->end($dataEncode);
    }

    $response->status(404);
    $response->end("Not found");
});

$server->start();
