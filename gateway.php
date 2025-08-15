<?php

declare(strict_types=1);

require_once 'health.php';

use Swoole\Coroutine as Co;
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
        try {
            $redis = $pool->get();
            $result = $redis->brPop('payments-queue', 0);

            if (!$result) {
                continue;
            }

            $payload = $result[1];

            go(function () use ($payload) {
                try {
                    $data = json_decode($payload, true);

                    $data['requestedAt'] = date('Y-m-d H:i:s');

                    print_r($data);
                } catch (\Throwable $th) {
                    var_dump("Error: ", $th);
                }
            });
        } catch
        (Exception $exception) {
            var_dump("Error: ", $exception->getMessage());
        }
    }
});


//go(static function () {
//    global $default;
//    while (true) {
//        $default = getServiceHealth();
//        var_dump($default);
//        Coroutine::sleep(5); // sleep não bloqueante
//    }
//});

//salvar pagamentos - simular db
//$payments = [];
//$processedPayments = [];
//$processors = ['default' => 0, 'fallback' => 0];
//$totalAmount = ['default' => 0, 'fallback' => 0];

// primeiro vamos mandar a requisicao para o gateway padrao, e aguardar no maximo 20 ms;
// se retornar 200, vamos salvar no banco de dados
// e incrementar a quantidade de pagamentos enviados para o default;
// se retornar qualquer status diferente de 200,
// vamos verificar o $default[failing], se for true, mandamos para o gateway de fallback
// salva o amount e incrementa a quantidade de pagamentos enviados para o fallback;
// se for false, vamos para o inicio da logica;

function processPayment(array $data): bool
{
    global $default, $processors, $totalAmount, $payments, $processedPayments;

    $amount = (float)$data['amount'];

    $defaultUrl = "payment-processor-default:8080/payments";
    $fallbackUrl = "payment-processor-fallback:8080/payments";

    try {
        $defaultProcessor = tryGateway($defaultUrl, $data, 20);
        if ($defaultProcessor['status'] == 200) {
            savePayment($amount, 'default');
            saveProcessedPayment($data, 'default');
            $processors['default']++;
            $totalAmount['default'] += $amount;
            return true;
        }

        if ($default['failing'] === true) {
            $response = tryGateway($fallbackUrl, $data, 10);
            if ($response['status'] == 200) {
                savePayment($amount, 'fallback');
                saveProcessedPayment($data, 'fallback');
                $processors['fallback']++;
                $totalAmount['fallback'] += $amount;
                return true;
            }
        }

        $response = tryGateway($fallbackUrl, $data, 30);
        if ($response['status'] == 200) {
            savePayment($amount, 'fallback');
            saveProcessedPayment($data, 'fallback');
            $processors['fallback']++;
            $totalAmount['fallback'] += $amount;
            return true;
        }

        return false;
    } catch (\Throwable $th) {
        var_dump("Error: ", $th);
        return false;
    }
}

function savePayment(float $amount, string $gateway): void
{
    global $payments;

    //talvez seja melhor receber o body do tryGateway, para salvar o pagamento que foi processado
    $payments[] = [
        'correlationId' => uniqid('', true),
        'amount' => $amount,
        'gateway' => $gateway,
        'createAt' => date('Y-m-d H:i:s')
    ];

    var_dump($payments);
}

function saveProcessedPayment(array $data, string $gateway): void
{
    global $processedPayments;

    //talvez seja melhor receber o body do tryGateway, para salvar o pagamento que foi processado
    $processedPayments[] = [
        'correlationId' => $data['correlationId'],
        'amount' => $data['amount'],
        'gateway' => $gateway,
        'createAt' => date('Y-m-d H:i:s')
    ];

    var_dump($processedPayments);
}

/*
 * formato de request e response do payment processor:
 *
 * POST /payments
 * {
 *     "correlationId": "4a7901b8-7d26-4d9d-aa19-4dc1c7cf60b3",
 *     "amount": 19.90,
 *     "requestedAt" : "2025-07-15T12:34:56.000Z"
 * }
 *
 * HTTP 200 - Ok
 * {
 *     "message": "payment processed successfully"
 * }
 * */
function tryGateway(string $url, array $data, int $timeoutMs): array
{
    $data['amount'] = number_format($data['amount'], 2);
    $data['requestedAt'] = date('Y-m-d H:i:s');
    unset($data['gateway']);
    unset($data['createAt']);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (array)json_decode($response, true)];
}


/* TESTE */
$mockPayment = [
    'correlationId' => uniqid('', true),
    "amount" => 10.00,
    'createAt' => date('Y-m-d H:i:s')
];

//$result = processPayment($mockPayment);
//var_dump($result);

//go(function () {
//    global $default, $processors, $totalAmount, $payments, $processedPayments;
//
//    $mockPayment = [
//        'correlationId' => uniqid('', true),
//        "amount" => 10.00,
//        'createAt' => date('Y-m-d H:i:s')
//    ];
//
//    while (true) {
//        $result = processPayment($mockPayment);
//        var_dump($result);
//        var_dump($processors, $totalAmount, $payments, $processedPayments);
//        Coroutine::sleep(1);
//    }
//});
