<?php

declare(strict_types=1);

use Swoole\Coroutine\Http\Client;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Swoole\Runtime;
use Swoole\Coroutine;

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

        $result = $redis->brPop('payments-queue', 0);
        $bestProcessor = $redis->get('best-processor');
        $pool->put($redis);


        if (!$result) {
            continue;
        }

        $payload = $result[1];
        $data = json_decode($payload, true);

        go(function () use ($data, $pool, $payload, $bestProcessor) {
            $client = new Client("payment-processor-{$bestProcessor}", 8080);
            $client->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]);

            $data['requestedAt'] = gmdate("Y-m-d\TH:i:s.000\Z");

            $client->post('/payments', json_encode($data));

            $redis2 = $pool->get();
            if ($client->statusCode === 200) {
                $amount = (int)($data['amount'] * 100);

                $redis2->multi()
                    ->hIncrBy("report:{$bestProcessor}", 'totalRequests', 1)
                    ->hIncrBy("report:{$bestProcessor}", 'totalAmount', $amount)
                    ->exec();

                var_dump([
                    'status' => $client->statusCode,
                    'body' => $client->body,
                ]);
            } else {
                echo "[Job][Error] Pagamento recolocado na fila. Status: {$client->statusCode}, body: {$client->body}\n";
                $redis2->rPush('payments-queue', $payload);
            }
            $pool->put($redis2);
        });
    }
});
