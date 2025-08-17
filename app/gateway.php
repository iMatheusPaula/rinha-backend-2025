<?php

declare(strict_types=1);

use Swoole\Coroutine\Http\Client;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Swoole\Runtime;

use function Swoole\Coroutine\run;

Runtime::enableCoroutine();

run(function () {
    $pool = new RedisPool(
        (new RedisConfig)
            ->withHost('redis')
            ->withPort(6379)
    );

    while (true) {
        $redis = $pool->get();
        $result = $redis->brPop('payments-queue', 5);
        $pool->put($redis);

        if (!$result) {
            continue;
        }

        $payload = $result[1];
        $data = json_decode($payload, true);

        go(function () use ($data, $pool, $payload) {
            $redis = $pool->get();
            $bestProcessor = $redis->get('processor') ?? 'default';
            $pool->put($redis);

            $client = new Client("payment-processor-{$bestProcessor}", 8080);
            $client->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]);

            $data['requestedAt'] = gmdate("Y-m-d\TH:i:s.000\Z");

            $client->post('/payments', json_encode($data));

            if ($client->statusCode === 200) {
                $amount = (int)($data['amount'] * 100); // convert to cents

                $redis = $pool->get();
                $redis->multi()
                    ->hIncrBy("report:{$bestProcessor}", 'totalRequests', 1)
                    ->hIncrBy("report:{$bestProcessor}", 'totalAmount', $amount)
                    ->exec();
                $pool->put($redis);

                echo "[Job] Pagamento processado com sucesso. Status: {$client->statusCode}, body: {$client->body}\n";
            } else {
                echo "[Job][Error] Pagamento recolocado na fila. Status: {$client->statusCode}, body: {$client->body}\n";

                $redis = $pool->get();
                $redis->rPush('payments-queue', $payload);
                $pool->put($redis);
            }
        });
    }
});
