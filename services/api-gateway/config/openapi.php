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
        ['name' => 'Catalog', 'description' => 'Public catalog endpoints forwarded to Catalog Service.'],
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
        '/categories' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'List active categories',
                'operationId' => 'gatewayListActiveCategories',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/HeaderLimitFilter'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/brands' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'List active brands',
                'operationId' => 'gatewayListActiveBrands',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/admin/brands' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'Admin list brands',
                'operationId' => 'gatewayAdminListBrands',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/HeaderLimitFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'post' => [
                'tags' => ['Catalog'],
                'summary' => 'Admin create a brand',
                'operationId' => 'gatewayAdminCreateBrand',
                'requestBody' => ['$ref' => '#/components/requestBodies/BrandPayload'],
                'responses' => [
                    '201' => ['$ref' => '#/components/responses/CatalogRecord'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/admin/brands/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'put' => [
                'tags' => ['Catalog'],
                'summary' => 'Admin update a brand',
                'operationId' => 'gatewayAdminUpdateBrand',
                'requestBody' => ['$ref' => '#/components/requestBodies/BrandPayload'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogRecord'],
                    '404' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
            'delete' => [
                'tags' => ['Catalog'],
                'summary' => 'Admin delete a brand',
                'operationId' => 'gatewayAdminDeleteBrand',
                'description' => 'Catalog Service returns 409 with "Nhãn hàng đang được sử dụng." when the brand still has products.',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogRecord'],
                    '409' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/admin/brands/{id}/toggle-status' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'patch' => [
                'tags' => ['Catalog'],
                'summary' => 'Admin lock or unlock a brand',
                'operationId' => 'gatewayAdminToggleBrandStatus',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogRecord'],
                    '404' => ['$ref' => '#/components/responses/ForwardedResponse'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/products' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'List products',
                'operationId' => 'gatewayListProducts',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/ProductLimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/products/sale' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'List sale products',
                'operationId' => 'gatewayListSaleProducts',
                'description' => 'Returns products with discount_percent greater than 0.',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/ProductLimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
                ],
            ],
        ],
        '/products/new' => [
            'get' => [
                'tags' => ['Catalog'],
                'summary' => 'List newest products',
                'operationId' => 'gatewayListNewestProducts',
                'description' => 'Returns newest products ordered by created_at desc. Page size is capped at 20.',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/NewProductLimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                ],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/CatalogCollection'],
                    '503' => ['$ref' => '#/components/responses/ServiceUnavailable'],
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
            'Id' => [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            'ForwardPath' => [
                'name' => 'path',
                'in' => 'path',
                'required' => true,
                'description' => 'Downstream API path. Supported first segments include auth, roles, users, banners, brands, categories, products, uploads, carts, orders, discounts, payments, invoices, and related resource names.',
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
            'PageFilter' => [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ],
            'HeaderLimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            ],
            'ProductLimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 12],
            ],
            'NewProductLimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 20],
            ],
            'CategoryFilter' => [
                'name' => 'category',
                'in' => 'query',
                'required' => false,
                'description' => 'Category id, slug, or name keyword.',
                'schema' => ['type' => 'string'],
            ],
            'BrandFilter' => [
                'name' => 'brand',
                'in' => 'query',
                'required' => false,
                'description' => 'Brand id, slug, or name keyword.',
                'schema' => ['type' => 'string'],
            ],
            'SearchFilter' => [
                'name' => 'search',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string'],
            ],
            'StatusFilter' => [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string', 'enum' => ['active', 'inactive']],
            ],
        ],
        'requestBodies' => [
            'BrandPayload' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandRequest'],
                    ],
                    'multipart/form-data' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandMultipartRequest'],
                    ],
                ],
            ],
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
            'CatalogCollection' => [
                'description' => 'Catalog collection returned by the downstream Catalog Service',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/CatalogCollectionResponse'],
                    ],
                ],
            ],
            'CatalogRecord' => [
                'description' => 'Catalog record returned by the downstream Catalog Service',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/CatalogRecordResponse'],
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
            'Pagination' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer', 'example' => 1],
                    'limit' => ['type' => 'integer', 'example' => 12],
                    'total' => ['type' => 'integer', 'example' => 120],
                    'totalPages' => ['type' => 'integer', 'example' => 10],
                ],
            ],
            'BrandRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'logo' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'enum' => ['active', 'inactive']],
                ],
            ],
            'BrandMultipartRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'logo' => ['type' => 'string', 'format' => 'binary', 'nullable' => true, 'description' => 'jpg, jpeg, png, webp, or svg. Max 2 MB.'],
                    'logo_url' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'enum' => ['active', 'inactive']],
                ],
            ],
            'CatalogCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Success'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                ],
            ],
            'CatalogRecordResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object', 'additionalProperties' => true],
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
