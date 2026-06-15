<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8003'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore Order Service',
        'version' => '1.0.0',
        'description' => 'Order, cart, discount, and warranty request APIs for BStore.',
    ],
    'servers' => [
        [
            'url' => $serverUrl,
            'description' => 'Order Service',
        ],
    ],
    'tags' => [
        ['name' => 'Documentation', 'description' => 'OpenAPI document endpoint.'],
        ['name' => 'Carts', 'description' => 'Cart-specific endpoints.'],
        ['name' => 'Orders', 'description' => 'Order-specific endpoints.'],
        ['name' => 'Resources', 'description' => 'Generic CRUD endpoints for supported order resources.'],
    ],
    'paths' => [
        '/docs/openapi.json' => [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Get Order Service OpenAPI document',
                'operationId' => 'getOrderOpenApiDocument',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/OpenApiDocument'],
                ],
            ],
        ],
        '/carts' => [
            'post' => [
                'tags' => ['Carts'],
                'summary' => 'Create a cart',
                'operationId' => 'createCart',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/CartCreateRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Cart created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CartResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/orders' => [
            'get' => [
                'tags' => ['Orders'],
                'summary' => 'List orders',
                'operationId' => 'listOrders',
                'responses' => [
                    '200' => [
                        'description' => 'Order list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/OrderCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Orders'],
                'summary' => 'Create an order',
                'operationId' => 'createOrder',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/OrderCreateRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Order created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/OrderResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/{resource}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/OrderResource'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'List records for a supported resource',
                'operationId' => 'listOrderResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceCollection'],
                    '404' => ['$ref' => '#/components/responses/UnsupportedResource'],
                ],
            ],
            'post' => [
                'tags' => ['Resources'],
                'summary' => 'Create a record for a supported resource',
                'operationId' => 'createOrderResource',
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
                ['$ref' => '#/components/parameters/OrderResource'],
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'Show a record for a supported resource',
                'operationId' => 'showOrderResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Resources'],
                'summary' => 'Replace a record for a supported resource',
                'operationId' => 'replaceOrderResource',
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
                'operationId' => 'updateOrderResource',
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
                'operationId' => 'deleteOrderResource',
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
            'OrderResource' => [
                'name' => 'resource',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        'carts',
                        'cart-items',
                        'cart_items',
                        'orders',
                        'order-items',
                        'order_items',
                        'discounts',
                        'order-discounts',
                        'order_discounts',
                        'warranty-requests',
                        'warranty_requests',
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
            'CartCreateRequest' => [
                'type' => 'object',
                'required' => ['user_id'],
                'properties' => [
                    'user_id' => ['type' => 'integer', 'example' => 1],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'active'],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CartItemInput'],
                    ],
                ],
            ],
            'CartItemInput' => [
                'type' => 'object',
                'required' => ['product_variant_id', 'product_name', 'price', 'quantity'],
                'properties' => [
                    'product_variant_id' => ['type' => 'integer', 'example' => 1],
                    'product_name' => ['type' => 'string', 'maxLength' => 255],
                    'color' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'ram' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'storage' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                    'subtotal' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'nullable' => true],
                ],
            ],
            'OrderCreateRequest' => [
                'type' => 'object',
                'required' => ['user_id', 'receiver_name', 'receiver_phone', 'shipping_address', 'shipping_method'],
                'properties' => [
                    'user_id' => ['type' => 'integer', 'example' => 1],
                    'order_code' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'receiver_name' => ['type' => 'string', 'maxLength' => 255],
                    'receiver_phone' => ['type' => 'string', 'maxLength' => 20],
                    'receiver_email' => ['type' => 'string', 'format' => 'email', 'nullable' => true, 'maxLength' => 191],
                    'shipping_address' => ['type' => 'string'],
                    'shipping_method' => ['type' => 'string', 'maxLength' => 50, 'example' => 'standard'],
                    'total_amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'nullable' => true],
                    'discount_amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'nullable' => true],
                    'final_amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'pending'],
                    'payment_status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'unpaid'],
                    'cancel_reason' => ['type' => 'string', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/OrderItemInput'],
                    ],
                    'discounts' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/OrderDiscountInput'],
                    ],
                ],
            ],
            'OrderItemInput' => [
                'type' => 'object',
                'required' => ['product_variant_id', 'product_name', 'price', 'quantity'],
                'properties' => [
                    'product_variant_id' => ['type' => 'integer', 'example' => 1],
                    'product_name' => ['type' => 'string', 'maxLength' => 255],
                    'color' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'ram' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'storage' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                    'subtotal' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'nullable' => true],
                ],
            ],
            'OrderDiscountInput' => [
                'type' => 'object',
                'required' => ['discount_id', 'discount_code', 'discount_amount'],
                'properties' => [
                    'discount_id' => ['type' => 'integer'],
                    'discount_code' => ['type' => 'string', 'maxLength' => 191],
                    'discount_amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                ],
            ],
            'GenericResourceRequest' => [
                'type' => 'object',
                'description' => 'Payload is filtered by the target model fillable fields. Supported resources include carts, cart items, orders, order items, discounts, order discounts, and warranty requests.',
                'additionalProperties' => true,
            ],
            'CartItem' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'cart_id' => ['type' => 'integer'],
                    'product_variant_id' => ['type' => 'integer'],
                    'product_name' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'ram' => ['type' => 'string', 'nullable' => true],
                    'storage' => ['type' => 'string', 'nullable' => true],
                    'price' => ['type' => 'string', 'example' => '29990000.00'],
                    'quantity' => ['type' => 'integer'],
                    'subtotal' => ['type' => 'string', 'example' => '29990000.00'],
                ],
            ],
            'Cart' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'nullable' => true],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CartItem'],
                    ],
                ],
            ],
            'OrderItem' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'order_id' => ['type' => 'integer'],
                    'product_variant_id' => ['type' => 'integer'],
                    'product_name' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'ram' => ['type' => 'string', 'nullable' => true],
                    'storage' => ['type' => 'string', 'nullable' => true],
                    'price' => ['type' => 'string', 'example' => '29990000.00'],
                    'quantity' => ['type' => 'integer'],
                    'subtotal' => ['type' => 'string', 'example' => '29990000.00'],
                ],
            ],
            'OrderDiscount' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'order_id' => ['type' => 'integer'],
                    'discount_id' => ['type' => 'integer'],
                    'discount_code' => ['type' => 'string'],
                    'discount_amount' => ['type' => 'string', 'example' => '100000.00'],
                ],
            ],
            'Order' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'order_code' => ['type' => 'string', 'nullable' => true],
                    'receiver_name' => ['type' => 'string'],
                    'receiver_phone' => ['type' => 'string'],
                    'receiver_email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'shipping_address' => ['type' => 'string'],
                    'shipping_method' => ['type' => 'string'],
                    'total_amount' => ['type' => 'string', 'example' => '30000000.00'],
                    'discount_amount' => ['type' => 'string', 'example' => '100000.00'],
                    'final_amount' => ['type' => 'string', 'example' => '29900000.00'],
                    'status' => ['type' => 'string', 'nullable' => true],
                    'payment_status' => ['type' => 'string', 'nullable' => true],
                    'cancel_reason' => ['type' => 'string', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/OrderItem'],
                    ],
                    'discounts' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/OrderDiscount'],
                    ],
                ],
            ],
            'CartResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Cart'],
                ],
            ],
            'OrderResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Order'],
                ],
            ],
            'OrderCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Order'],
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
