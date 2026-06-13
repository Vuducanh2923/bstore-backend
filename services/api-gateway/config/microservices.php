<?php

return [
    'timeout' => (int) env('MICROSERVICE_TIMEOUT', 10),

    'services' => [
        'auth' => [
            'url' => env('AUTH_SERVICE_URL', 'http://127.0.0.1:8001'),
        ],
        'catalog' => [
            'url' => env('CATALOG_SERVICE_URL', 'http://127.0.0.1:8002'),
        ],
        'order' => [
            'url' => env('ORDER_SERVICE_URL', 'http://127.0.0.1:8003'),
        ],
        'payment' => [
            'url' => env('PAYMENT_SERVICE_URL', 'http://127.0.0.1:8004'),
        ],
    ],
];
