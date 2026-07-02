<?php

return [
    'connect_timeout' => (int) env('MICROSERVICE_CONNECT_TIMEOUT', 2),

    'timeout' => (int) env('MICROSERVICE_TIMEOUT', 5),

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
