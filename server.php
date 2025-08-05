<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

$server = new Server("0.0.0.0", 9501);

$server->on("start", function (Server $server) {
    echo "Servidor Swoole rodando em http://localhost:9501\n";
});

$server->on("request", function (Request $request, Response $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Olá, mundo com Swoole!");
});

$server->start();
