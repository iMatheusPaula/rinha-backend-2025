<?php

declare(strict_types=1);

use Swoole\Coroutine\Http\Client;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Swoole\Runtime;
use Swoole\Coroutine\Channel;

use function Swoole\Coroutine\run;

Runtime::enableCoroutine();

run(function () {
    $pool = new RedisPool(
        (new RedisConfig)
            ->withHost('redis')
            ->withPort(6379)
    );

    $httpPools = [
        'default' => new Channel(5),
        'fallback' => new Channel(5)
    ];

    foreach ($httpPools as $name => $httpPool) {
        for ($i = 0; $i < 5; $i++) {
            $client = new Client("payment-processor-{$name}", 8080);
            $client->set(['keep_alive' => true]);
            $httpPool->push($client);
        }
    }

    while (true) {
        $redis = $pool->get();
        $result = $redis->brPop('payments-queue', 5);
        $pool->put($redis);

        if (!$result) {
            continue;
        }

        $payload = $result[1];
        $data = json_decode($payload, true);

        go(function () use ($data, $pool, $payload, $httpPools) {
            $redis = $pool->get();
            $bestProcessor = $redis->get('processor') ?? 'default';
            $pool->put($redis);

            try {
                $client = $httpPools[$bestProcessor]->pop();
                $client->setHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]);

                $now = new DateTime;

                $data['requestedAt'] = $now->format('Y-m-d\TH:i:s.v\Z');

                $client->post('/payments', json_encode($data));

                if ($client->statusCode === 200) {
                    $amount = (int)($data['amount'] * 100); // convert to cents

                    $redis = $pool->get();
                    $redis->zAdd(
                        "payments-summary-{$bestProcessor}",
                        (float)$now->format('U.v'),
                        "{$data['correlationId']}:{$amount}"
                    );
                    $pool->put($redis);

                    echo "[Job] Pagamento processado com sucesso. Status: {$client->statusCode}, body: {$client->body}\n";
                } elseif ($client->statusCode !== 422) {
                    echo "[Job][Error] Pagamento recolocado na fila. Status: {$client->statusCode}, body: {$client->body}\n";

                    $redis = $pool->get();
                    $redis->lPush('payments-queue', $payload);
                    $pool->put($redis);
                } else {
                    echo "[Job][Error] Status: {$client->statusCode}, body: {$client->body}\n";
                }
            } finally {
                $httpPools[$bestProcessor]->push($client);
            }
        });
    }
});
