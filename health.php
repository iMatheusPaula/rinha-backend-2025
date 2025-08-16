<?php

declare(strict_types=1);

use Swoole\Coroutine as Co;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Runtime;

const PROCESSORS = [
    'default',
    'fallback'
];

Runtime::enableCoroutine();

Co\run(function () {
    $redis = new Redis();
    $redis->connect('redis');

    echo "[HealthChecker] Iniciado. Verificando a cada 5s.\n";

    while (true) {
        $waitGroup = new WaitGroup();
        $response = [];

        foreach (PROCESSORS as $name) {
            $waitGroup->add();

            go(static function () use ($name, $waitGroup, &$response) {
                $client = new Client("payment-processor-{$name}", 8080);
                $client->get('/payments/service-health');
                $client->close();

                $body = json_decode($client->body, true, 512, JSON_THROW_ON_ERROR);

                if ($client->statusCode === 200) {
                    $response[$name] = $body;
                }

                var_dump([
                    'status' => $client->statusCode,
                    'body' => $client->body,
                ]);

                $waitGroup->done();
            });
        }
        $waitGroup->wait();

        $default = $response['default'];
        $fallback = $response['fallback'];

        $bestProcessor = 'default';

        if ($default['failing'] === true && $fallback['failing'] === false) {
            $bestProcessor = 'fallback';
        } elseif ($fallback['failing'] === false) {
            if ($default['minResponseTime'] > ($fallback['minResponseTime'] * 1.5)) {
                $bestProcessor = 'fallback';
            }
        }

        echo "[HealthChecker] Default (Failing: " . ($default['failing'] ? 'Yes' : 'No') . ", Time: {$default['minResponseTime']}ms), ";
        echo "Fallback (Failing: " . ($fallback['failing'] ? 'Yes' : 'No') . ", Time: {$fallback['minResponseTime']}ms). ";
        echo "Best Processor: '{$bestProcessor}'.\n";

        $redis->set('best-processor', $bestProcessor);

        // melhorar esse tempo talvez salvando o tempo + 5s
        Co::sleep(1);
    }
});
