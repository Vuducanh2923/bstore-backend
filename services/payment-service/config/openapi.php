<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8004'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore Payment Service',
        'version' => '1.0.0',
        'description' => 'Payment, payment transaction, and invoice APIs for BStore.',
    ],
    'servers' => [
        [
            'url' => $serverUrl,
            'description' => 'Payment Service',
        ],
    ],
    'tags' => [
        ['name' => 'Documentation', 'description' => 'OpenAPI document endpoint.'],
        ['name' => 'Payments', 'description' => 'Payment-specific endpoints.'],
        ['name' => 'Resources', 'description' => 'Generic CRUD endpoints for supported payment resources.'],
    ],
    'paths' => [
        '/docs/openapi.json' => [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Get Payment Service OpenAPI document',
                'operationId' => 'getPaymentOpenApiDocument',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/OpenApiDocument'],
                ],
            ],
        ],
        '/payments' => [
            'get' => [
                'tags' => ['Payments'],
                'summary' => 'List payments',
                'operationId' => 'listPayments',
                'responses' => [
                    '200' => [
                        'description' => 'Payment list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/PaymentCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Payments'],
                'summary' => 'Create a payment',
                'operationId' => 'createPayment',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaymentCreateRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Payment created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/PaymentResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/{resource}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/PaymentResource'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'List records for a supported resource',
                'operationId' => 'listPaymentResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceCollection'],
                    '404' => ['$ref' => '#/components/responses/UnsupportedResource'],
                ],
            ],
            'post' => [
                'tags' => ['Resources'],
                'summary' => 'Create a record for a supported resource',
                'operationId' => 'createPaymentResource',
                'requestBody' => ['$ref' => '#/components/requestBodies/GenericResource'],
                'responses' => [
                    '201' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/UnsupportedResource'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/{resource}/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/PaymentResource'],
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'Show a record for a supported resource',
                'operationId' => 'showPaymentResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Resources'],
                'summary' => 'Replace a record for a supported resource',
                'operationId' => 'replacePaymentResource',
                'requestBody' => ['$ref' => '#/components/requestBodies/GenericResource'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'patch' => [
                'tags' => ['Resources'],
                'summary' => 'Partially update a record for a supported resource',
                'operationId' => 'updatePaymentResource',
                'requestBody' => ['$ref' => '#/components/requestBodies/GenericResource'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Resources'],
                'summary' => 'Delete a record for a supported resource',
                'operationId' => 'deletePaymentResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/DeleteResponse'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
    ],
    'components' => [
        'parameters' => [
            'Id' => [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            'PaymentResource' => [
                'name' => 'resource',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        'payments',
                        'payment-transactions',
                        'payment_transactions',
                        'invoices',
                    ],
                ],
            ],
        ],
        'requestBodies' => [
            'GenericResource' => [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/GenericResourceRequest'],
                    ],
                ],
            ],
        ],
        'responses' => [
            'OpenApiDocument' => [
                'description' => 'OpenAPI document',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ],
            'ResourceCollection' => [
                'description' => 'Records returned',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ResourceCollectionResponse'],
                    ],
                ],
            ],
            'ResourceRecord' => [
                'description' => 'Record returned',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ResourceRecordResponse'],
                    ],
                ],
            ],
            'DeleteResponse' => [
                'description' => 'Record deleted',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/DeleteResponse'],
                    ],
                ],
            ],
            'NotFound' => [
                'description' => 'Record not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'UnsupportedResource' => [
                'description' => 'Resource is not supported',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'ValidationError' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                    ],
                ],
            ],
        ],
        'schemas' => [
            'PaymentCreateRequest' => [
                'type' => 'object',
                'required' => ['order_id', 'payment_method', 'amount'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'example' => 1],
                    'payment_method' => ['type' => 'string', 'maxLength' => 50, 'example' => 'cod'],
                    'payment_provider' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50, 'example' => 'vnpay'],
                    'transaction_code' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'example' => 29990000],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'pending'],
                    'paid_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'transactions' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/PaymentTransactionInput'],
                    ],
                ],
            ],
            'PaymentTransactionInput' => [
                'type' => 'object',
                'required' => ['transaction_code', 'provider', 'amount', 'status'],
                'properties' => [
                    'transaction_code' => ['type' => 'string', 'maxLength' => 191],
                    'provider' => ['type' => 'string', 'maxLength' => 100, 'example' => 'vnpay'],
                    'amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                    'status' => ['type' => 'string', 'maxLength' => 20, 'example' => 'success'],
                    'response_data' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                ],
            ],
            'GenericResourceRequest' => [
                'type' => 'object',
                'description' => 'Payload is filtered by the target model fillable fields. Supported resources include payments, payment transactions, and invoices.',
                'additionalProperties' => true,
            ],
            'PaymentTransaction' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'payment_id' => ['type' => 'integer'],
                    'transaction_code' => ['type' => 'string'],
                    'provider' => ['type' => 'string'],
                    'amount' => ['type' => 'string', 'example' => '29990000.00'],
                    'status' => ['type' => 'string'],
                    'response_data' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                ],
            ],
            'Invoice' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'payment_id' => ['type' => 'integer'],
                    'order_id' => ['type' => 'integer'],
                    'invoice_code' => ['type' => 'string'],
                    'total_amount' => ['type' => 'string', 'example' => '29990000.00'],
                ],
            ],
            'Payment' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'order_id' => ['type' => 'integer'],
                    'payment_method' => ['type' => 'string'],
                    'payment_provider' => ['type' => 'string', 'nullable' => true],
                    'transaction_code' => ['type' => 'string', 'nullable' => true],
                    'amount' => ['type' => 'string', 'example' => '29990000.00'],
                    'status' => ['type' => 'string', 'nullable' => true],
                    'paid_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'transactions' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/PaymentTransaction'],
                    ],
                    'invoices' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Invoice'],
                    ],
                ],
            ],
            'PaymentResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Payment'],
                ],
            ],
            'PaymentCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Payment'],
                    ],
                ],
            ],
            'ResourceRecordResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'ResourceCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ],
            'DeleteResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Xoa du lieu thanh cong'],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                ],
            ],
            'ValidationErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
