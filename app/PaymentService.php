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
            'dados' => $data,
        ]));
    }
}
