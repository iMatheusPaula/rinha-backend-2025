<?php

namespace App;

use Swoole\Http\Request;
use Swoole\Http\Response;

class PaymentService
{
    public static function handler(Request $request, Response $response): void
    {
        $data = json_decode($request->rawContent());

        $response->header('Content-Type', 'application/json');

        $response->end(json_encode([
            'message' => 'Testando',
            'data' => $data,
        ]));
    }

    /*
     * POST /payments
     * HTTP 2XX
     * {
     *     "correlationId": "4a7901b8-7d26-4d9d-aa19-4dc1c7cf60b3",
     *     "amount": 19.90
     * }
     * */
    public static function processPayment(array $data): array
    {
        return [
            "correlationId" => "",
            "amount" => 0.00,
        ];
    }

    /*
     * GET /payments-summary?from=2020-07-10T12:34:56.000Z&to=2020-07-10T12:35:56.000Z
     * HTTP 200 - Ok
     * {
     *     "default" : {
     *         "totalRequests": 43236,
     *         "totalAmount": 415542345.98
     *     },
     *     "fallback" : {
     *         "totalRequests": 423545,
     *         "totalAmount": 329347.34
     *     }
     * }
     * */
    public static function getPaymentsSummary(Request $request, Response $response): array
    {
        return [
            "default" => [
                "totalRequests" => 0,
                "totalAmount" => 0.00,
            ],
            "fallback" => [
                "totalRequests" => 0,
                "totalAmount" => 0.00,
            ]
        ];
    }

}
