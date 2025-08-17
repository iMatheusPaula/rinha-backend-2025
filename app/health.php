<?php

declare(strict_types=1);

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

run(static function () {
    try {
        $redis = new Redis();
        $redis->connect('redis');
    } catch (\RedisException $e) {
        echo "[HealthChecker][Error] connecting to Redis: " . $e->getMessage() . "\n";
        return;
    }

    echo "[HealthChecker] Started.\n";

    while (true) {
        $waitGroup = new WaitGroup();
        $response = [];

        foreach (['default', 'fallback'] as $processor) {
            $waitGroup->add();
            go(static function () use ($processor, $waitGroup, &$response) {
                try {
                    $client = new Client("payment-processor-{$processor}", 8080);
                    $client->set(['timeout' => 3]);
                    $client->get('/payments/service-health');
                    $client->close();

                    if ($client->statusCode !== 200) {
                        throw new \Exception("{$processor} failed with status code {$client->statusCode}");
                    }

                    $response[$processor] = json_decode($client->body, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $exception) {
                    $response[$processor] = ["failing" => true, "minResponseTime" => INF];
                    echo "[HealthChecker]" . $exception->getMessage() . PHP_EOL;
                } finally {
                    if (isset($client)) {
                        $client->close();
                    }
                    $waitGroup->done();
                }
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

        try {
            $redis->setex('processor', 8, $bestProcessor);
        } catch (\RedisException $e) {
            echo "[HealthChecker] Error saving to Redis: " . $e->getMessage() . "\n";
        }

        Coroutine::sleep(5);
    }
});
