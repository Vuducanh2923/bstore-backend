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
        ['name' => 'Warranty', 'description' => 'Customer and administrator warranty request workflow.'],
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
        '/customer/warranty-requests' => [
            'get' => [
                'tags' => ['Warranty'], 'summary' => 'List current customer warranty requests',
                'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Warranty request list']],
            ],
            'post' => [
                'tags' => ['Warranty'], 'summary' => 'Submit a warranty request',
                'security' => [['bearerAuth' => []]],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WarrantyCreateRequest']]]],
                'responses' => ['201' => ['description' => 'Warranty request created'], '409' => ['description' => 'Active request already exists'], '422' => ['$ref' => '#/components/responses/ValidationError']],
            ],
        ],
        '/customer/warranty-requests/{id}' => [
            'get' => [
                'tags' => ['Warranty'], 'summary' => 'Get own warranty request',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Warranty request detail'], '403' => ['description' => 'Not owner'], '404' => ['$ref' => '#/components/responses/NotFound']],
            ],
        ],
        '/customer/warranty-requests/{id}/cancel' => [
            'put' => [
                'tags' => ['Warranty'], 'summary' => 'Cancel a pending warranty request',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Warranty request cancelled'], '409' => ['description' => 'Invalid status transition']],
            ],
        ],
        '/admin/warranty-requests' => [
            'get' => [
                'tags' => ['Warranty'], 'summary' => 'List all warranty requests',
                'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Paginated warranty request list']],
            ],
        ],
        '/admin/warranty-requests/{id}' => [
            'get' => [
                'tags' => ['Warranty'], 'summary' => 'Get warranty request detail for staff',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Warranty request detail'], '404' => ['$ref' => '#/components/responses/NotFound']],
            ],
        ],
        '/admin/warranty-requests/{id}/approve' => [
            'put' => [
                'tags' => ['Warranty'], 'summary' => 'Approve a pending warranty request',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Approved'], '409' => ['description' => 'Invalid status transition']],
            ],
        ],
        '/admin/warranty-requests/{id}/reject' => [
            'put' => [
                'tags' => ['Warranty'], 'summary' => 'Reject a pending warranty request',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Rejected'], '409' => ['description' => 'Invalid status transition'], '422' => ['$ref' => '#/components/responses/ValidationError']],
            ],
        ],
        '/admin/warranty-requests/{id}/processing' => [
            'put' => [
                'tags' => ['Warranty'], 'summary' => 'Move an approved request to processing',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Processing'], '409' => ['description' => 'Invalid status transition']],
            ],
        ],
        '/admin/warranty-requests/{id}/complete' => [
            'put' => [
                'tags' => ['Warranty'], 'summary' => 'Complete a processing warranty request',
                'security' => [['bearerAuth' => []]], 'parameters' => [['$ref' => '#/components/parameters/Id']],
                'responses' => ['200' => ['description' => 'Completed'], '409' => ['description' => 'Invalid status transition']],
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
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ],
        'parameters' => [
            'Id' => [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
        ],
        'requestBodies' => [
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
            'WarrantyCreateRequest' => [
                'type' => 'object',
                'required' => ['order_id', 'order_item_id', 'reason'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                    'order_item_id' => ['type' => 'integer', 'minimum' => 1],
                    'reason' => ['type' => 'string', 'maxLength' => 1000],
                    'description' => ['type' => 'string', 'nullable' => true, 'maxLength' => 5000],
                ],
            ],
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
                'required' => ['product_variant_id', 'quantity'],
                'properties' => [
                    'product_variant_id' => ['type' => 'integer', 'example' => 1],
                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'OrderCreateRequest' => [
                'type' => 'object',
                'required' => ['receiver_name', 'receiver_phone', 'shipping_address', 'shipping_method', 'payment_method', 'items'],
                'properties' => [
                    'receiver_name' => ['type' => 'string', 'maxLength' => 255],
                    'receiver_phone' => ['type' => 'string', 'maxLength' => 20],
                    'receiver_email' => ['type' => 'string', 'format' => 'email', 'nullable' => true, 'maxLength' => 191],
                    'shipping_address' => ['type' => 'string'],
                    'shipping_method' => ['type' => 'string', 'maxLength' => 50, 'example' => 'standard'],
                    'payment_method' => ['type' => 'string', 'enum' => ['cod', 'vnpay']],
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
                'required' => ['product_variant_id', 'quantity'],
                'properties' => [
                    'product_variant_id' => ['type' => 'integer', 'example' => 1],
                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'OrderDiscountInput' => [
                'type' => 'object',
                'properties' => [
                    'discount_id' => ['type' => 'integer', 'nullable' => true],
                    'discount_code' => ['type' => 'string', 'maxLength' => 191, 'nullable' => true],
                ],
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
