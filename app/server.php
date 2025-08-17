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

    //payments
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

        $dataEncode = json_encode($data);

        $redis->lpush('payments-queue', $dataEncode);

        $response->status(202);
        $response->end();
    }

    //summary
    if ($method === 'GET' && $uri === '/payments-summary') {
        parse_str($request->server['query_string'] ?? '', $queryParams);

        $minScore = '-inf';
        $maxScore = '+inf';
        if (!empty($queryParams['from'])) {
            $minScore = (float)new DateTimeImmutable($queryParams['from'])->format('U.v');
        }
        if (!empty($queryParams['to'])) {
            $maxScore = (float)new DateTimeImmutable($queryParams['to'])->format('U.v');
        }

        $report = [];
        foreach (['default', 'fallback'] as $processorName) {
            $reportKey = "payments-summary-{$processorName}";

            $logEntries = $redis->zRangeByScore($reportKey, (string)$minScore, (string)$maxScore);

            $totalRequests = 0;
            $totalAmountInCents = 0;

            foreach ($logEntries as $entry) {
                // "correlationId:amountInCents"
                $parts = explode(':', $entry);
                $totalRequests++;
                $totalAmountInCents += (int)$parts[1];
            }

            $report[$processorName] = [
                'totalRequests' => $totalRequests,
                'totalAmount' => (float)($totalAmountInCents / 100),
            ];
        }

        $response->header('Content-Type', 'application/json');
        $response->status(200);
        $response->end(json_encode($report));
    }

    //purge
    if ($method === 'POST' && $uri === '/purge-payments') {
        $redis->flushAll();

        $response->status(204);
        $response->end();
    }

    $response->status(404);
    $response->end("Not found");
});

$server->start();
