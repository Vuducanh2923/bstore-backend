<?php

return [
    'shipping' => [
        'flat_fee' => (float) env('ORDER_SHIPPING_FLAT_FEE', 30000),
        'free_threshold' => (float) env('ORDER_FREE_SHIPPING_THRESHOLD', 20000000),
        'methods' => [
            'standard' => 'standard',
        ],
    ],

    'payment_methods' => [
        'cod',
        'vnpay',
    ],
];
