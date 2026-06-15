<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore API Gateway',
        'version' => '1.0.0',
        'description' => 'Gateway forwards public BStore API requests to auth, catalog, order, and payment microservices.',
    ],
    'servers' => [
        [
            'url' => $serverUrl,
            'description' => 'API Gateway',
        ],
    ],
    'tags' => [
        ['name' => 'Documentation', 'description' => 'OpenAPI document endpoint.'],
        ['name' => 'Gateway', 'description' => 'Gateway health and dynamic forwarding.'],
    ],
    'paths' => [
        '/docs/openapi.json' => [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Get API Gateway OpenAPI document',
                'operationId' => 'getGatewayOpenApiDocument',
                'responses' => [
                    '200' => [
                        'description' => 'OpenAPI document',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'additionalProperties' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/gateway/health' => [
            'get' => [
                'tags' => ['Gateway'],
                'summary' => 'Check gateway health',
                'operationId' => 'gatewayHealth',
                'responses' => [
                    '200' => [
                        'description' => 'Gateway is available',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/HealthResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/{path}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/ForwardPath'],
            ],
            'get' => [
                'tags' => ['Gateway'],
                'summary' => 'Forward GET request to a microservice',
                'operationId' => 'forwardGetRequest',
                'parameters' => [
                    ['$ref' => '#/components/parameters/ForwardQuery'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '404' => ['$ref' => '#/components/responses/GatewayRouteNotFound'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'post' => [
                'tags' => ['Gateway'],
                'summary' => 'Forward POST request to a microservice',
                'operationId' => 'forwardPostRequest',
                'requestBody' => ['$ref' => '#/components/requestBodies/ForwardPayload'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '201' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '404' => ['$ref' => '#/components/responses/GatewayRouteNotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'put' => [
                'tags' => ['Gateway'],
                'summary' => 'Forward PUT request to a microservice',
                'operationId' => 'forwardPutRequest',
                'requestBody' => ['$ref' => '#/components/requestBodies/ForwardPayload'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '404' => ['$ref' => '#/components/responses/GatewayRouteNotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'patch' => [
                'tags' => ['Gateway'],
                'summary' => 'Forward PATCH request to a microservice',
                'operationId' => 'forwardPatchRequest',
                'requestBody' => ['$ref' => '#/components/requestBodies/ForwardPayload'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '404' => ['$ref' => '#/components/responses/GatewayRouteNotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'delete' => [
                'tags' => ['Gateway'],
                'summary' => 'Forward DELETE request to a microservice',
                'operationId' => 'forwardDeleteRequest',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '404' => ['$ref' => '#/components/responses/GatewayRouteNotFound'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
    ],
    'components' => [
        'parameters' => [
            'ForwardPath' => [
                'name' => 'path',
                'in' => 'path',
                'required' => true,
                'description' => 'Downstream API path. Supported first segments include auth, roles, users, brands, categories, products, uploads, carts, orders, discounts, payments, invoices, and related resource names.',
                'schema' => [
                    'type' => 'string',
                    'example' => 'products',
                ],
            ],
            'ForwardQuery' => [
                'name' => 'query',
                'in' => 'query',
                'required' => false,
                'description' => 'Any query parameters are forwarded to the target service.',
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'style' => 'form',
                'explode' => true,
            ],
        ],
        'requestBodies' => [
            'ForwardPayload' => [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
        ],
        'responses' => [
            'ForwardedResponse' => [
                'description' => 'Response returned by the downstream service',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
            'GatewayRouteNotFound' => [
                'description' => 'Gateway cannot resolve the first path segment',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        'example' => [
                            'success' => false,
                            'message' => 'Gateway khong tim thay service phu hop',
                        ],
                    ],
                ],
            ],
            'ServiceUnavailable' => [
                'description' => 'Target service is unavailable',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        'example' => [
                            'success' => false,
                            'message' => 'Service catalog khong kha dung',
                        ],
                    ],
                ],
            ],
            'ValidationError' => [
                'description' => 'Validation error returned by the downstream service',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                    ],
                ],
            ],
        ],
        'schemas' => [
            'HealthResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'service' => ['type' => 'string', 'example' => 'api-gateway'],
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
