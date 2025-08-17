<?php

declare(strict_types=1);

use Swoole\Coroutine as Co;
use Swoole\Coroutine\Http\Client;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Swoole\Runtime;

Runtime::enableCoroutine();

Co\run(function () {
    $pool = new RedisPool(
        (new RedisConfig)
            ->withHost('redis')
            ->withPort(6379)
    );

    while (true) {
        $redis = $pool->get();
        try {
            $result = $redis->brPop('payments-queue', 0);

            if (!$result) {
                continue;
            }

            go(function () use ($result, $redis, $pool) {
                $pool->get();
                defer(function () use ($pool, $redis) {
                    $pool->put($redis);
                });

                $payload = $result[1];
                $data = json_decode($payload, true);
                $bestProcessor = $redis->get('best-processor') ?? 'default';

                echo "[Job] Decisão do Health Checker: Usar '{$bestProcessor}'.\n";

                $client = new Client("payment-processor-{$bestProcessor}", 8080);
                $client->setHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]);
                $client->set([
                    'timeout' => 1,
                ]);

                $data['requestedAt'] = gmdate("Y-m-d\TH:i:s.000\Z");

                try {
                    $client->post('/payments', json_encode($data));

                    if ($client->statusCode === 200) {
                        $amount = (int)($data['amount'] * 100);

                        $redis->
                        multi()
                            ->hIncrBy("report:{$bestProcessor}", 'totalRequests', 1)
                            ->hIncrBy("report:{$bestProcessor}", 'totalAmount', $amount)
                            ->exec();

                        var_dump([
                            'status' => $client->statusCode,
                            'body' => $client->body,
                        ]);
                    } elseif ($client->statusCode !== 422) {
                        $redis->rPush('payments-queue', $payload); // Volta para o final da fila
                        echo "[Job] Pagamento recolocado na fila. Status: {$client->statusCode}, body: {$client->body}\n";
                    }
                } catch (Exception $e) {
                    echo "[Job] Erro ao processar pagamento: " . $e->getMessage() . "\n";
                    $redis->rPush('payments-queue', $payload);
                }
            });
        } catch (Exception $exception) {
//            global $redis;
            var_dump("Error: ", $exception->getMessage());
            $pool->put($redis); // Devolve a conexão ao pool
            continue;
        } finally {
            $pool->put($redis); // Devolve a conexão ao pool
        }
    }
});
