<?php

declare(strict_types=1);

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$redis = null;

$server = new Server("0.0.0.0", 9501);

$server->set([
    'open_tcp_nodelay' => true,
    'log_level' => SWOOLE_LOG_WARNING,
    'dispatch_mode' => 2,
]);

$server->on("start", static function () {
    echo "Servidor Swoole rodando em http://localhost:9501\n";
});

$server->on("workerStart", static function (Server $server, int $workerId) {
    global $redis;

    echo "Worker #{$workerId} iniciado.\n";

    $redis = new Redis();
    $redis->connect('redis');
});

$server->on("request", function (Request $request, Response $response) {
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

        if (!(float)$data->amount || !(string)$data->correlationId) {
            $response->status(422);
            $response->end("Unprocessable Entity");
            return;
        }

        $data->amount = number_format($data->amount, 2);
        $dataEncode = json_encode($data);

        $redis->lpush('payments-queue', $dataEncode);

        $response->header('Content-Type', 'application/json');
        $response->status(202);
        $response->end($dataEncode);
    }

    if ($method === 'GET' && $uri === '/payments-summary') {
        // verificar se tem algo na fila de processamento 'payments-queue' antes de chamar o sumario
        // pq se nao pode dar multa
        // se tiver mesmo que esperar parar a fila, talvez vai ter que colocar algo aqui
        // pra salvar no redis falando que alguem quer o relatorio
        // entao la na fila tem que pegar esse dado que alguem quer ver e pausar a fila
        // resumindo so vai rodar a fila se ninguem quiser ver o sumario
        // depois que enviar o sumario salva que ja mostrou e volta a fila ao normal

        $report = [];
        foreach (['default', 'fallback'] as $processor) {
            $reportKey = "report:{$processor}";
            $data = $redis->hGetAll($reportKey);
            $report[$processor] = [
                'totalRequests' => (int)($data['totalRequests'] ?? 0),
                'totalAmount' => (float)($data['totalAmount'] ?? 0.00),
            ];
        }

        $response->header('Content-Type', 'application/json');
        $response->status(200);
        $response->end(json_encode($report));
    }

    $response->status(404);
    $response->end("Not found");
});

$server->start();
