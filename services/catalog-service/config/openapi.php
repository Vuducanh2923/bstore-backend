<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8002'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore Catalog Service',
        'version' => '1.0.0',
        'description' => 'Catalog APIs for products, brands, categories, inventory, images, uploads, and warranty policies.',
    ],
    'servers' => [
        [
            'url' => $serverUrl,
            'description' => 'Catalog Service',
        ],
    ],
    'tags' => [
        ['name' => 'Documentation', 'description' => 'OpenAPI document endpoint.'],
        ['name' => 'Uploads', 'description' => 'Image upload endpoints.'],
        ['name' => 'Products', 'description' => 'Product-specific endpoints.'],
        ['name' => 'Resources', 'description' => 'Generic CRUD endpoints for supported catalog resources.'],
    ],
    'paths' => [
        '/docs/openapi.json' => [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Get Catalog Service OpenAPI document',
                'operationId' => 'getCatalogOpenApiDocument',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/OpenApiDocument'],
                ],
            ],
        ],
        '/uploads/images' => [
            'post' => [
                'tags' => ['Uploads'],
                'summary' => 'Upload a product image',
                'operationId' => 'uploadProductImage',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['image'],
                                'properties' => [
                                    'image' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'jpg, jpeg, png, webp, or gif. Max 4 MB.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Image uploaded',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UploadResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/products' => [
            'get' => [
                'tags' => ['Products'],
                'summary' => 'List products',
                'operationId' => 'listProducts',
                'parameters' => [
                    ['$ref' => '#/components/parameters/CategoryIdFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/KeywordFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Product list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Products'],
                'summary' => 'Create a product',
                'operationId' => 'createProduct',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ProductCreateRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Product created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/products/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Products'],
                'summary' => 'Show a product',
                'operationId' => 'showProduct',
                'responses' => [
                    '200' => [
                        'description' => 'Product returned',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Products'],
                'summary' => 'Replace a product',
                'operationId' => 'replaceProduct',
                'requestBody' => ['$ref' => '#/components/requestBodies/ProductUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'Product updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'patch' => [
                'tags' => ['Products'],
                'summary' => 'Partially update a product',
                'operationId' => 'updateProduct',
                'requestBody' => ['$ref' => '#/components/requestBodies/ProductUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'Product updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Products'],
                'summary' => 'Delete a product',
                'operationId' => 'deleteProduct',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/DeleteResponse'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
        '/{resource}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/CatalogResource'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'List records for a supported resource',
                'operationId' => 'listCatalogResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceCollection'],
                    '404' => ['$ref' => '#/components/responses/UnsupportedResource'],
                ],
            ],
            'post' => [
                'tags' => ['Resources'],
                'summary' => 'Create a record for a supported resource',
                'operationId' => 'createCatalogResource',
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
                ['$ref' => '#/components/parameters/CatalogResource'],
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'Show a record for a supported resource',
                'operationId' => 'showCatalogResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Resources'],
                'summary' => 'Replace a record for a supported resource',
                'operationId' => 'replaceCatalogResource',
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
                'operationId' => 'updateCatalogResource',
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
                'operationId' => 'deleteCatalogResource',
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
            'CatalogResource' => [
                'name' => 'resource',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        'brands',
                        'categories',
                        'products',
                        'product-variants',
                        'product_variants',
                        'inventories',
                        'inventory-transactions',
                        'inventory_transactions',
                        'product-images',
                        'product_images',
                        'warranty-policies',
                        'warranty_policies',
                    ],
                ],
            ],
            'CategoryIdFilter' => [
                'name' => 'category_id',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer'],
            ],
            'CategoryFilter' => [
                'name' => 'category',
                'in' => 'query',
                'required' => false,
                'description' => 'Category slug or category name keyword.',
                'schema' => ['type' => 'string'],
            ],
            'KeywordFilter' => [
                'name' => 'keyword',
                'in' => 'query',
                'required' => false,
                'description' => 'Search product name, slug, description, brand name, or category name.',
                'schema' => ['type' => 'string'],
            ],
            'StatusFilter' => [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string', 'example' => 'active'],
            ],
        ],
        'requestBodies' => [
            'ProductUpdate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ProductUpdateRequest'],
                    ],
                ],
            ],
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
            'ProductCreateRequest' => [
                'type' => 'object',
                'required' => ['category_id', 'brand_id', 'name', 'slug', 'price'],
                'properties' => [
                    'category_id' => ['type' => 'integer', 'example' => 1],
                    'brand_id' => ['type' => 'integer', 'example' => 1],
                    'warranty_policy_id' => ['type' => 'integer', 'nullable' => true],
                    'name' => ['type' => 'string', 'maxLength' => 255, 'example' => 'iPhone 15 Pro Max'],
                    'slug' => ['type' => 'string', 'maxLength' => 191, 'example' => 'iphone-15-pro-max'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'specifications' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'example' => 29990000],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'active'],
                    'variants' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductVariantInput'],
                    ],
                    'images' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductImageInput'],
                    ],
                    'warranty_policy' => ['$ref' => '#/components/schemas/WarrantyPolicyInput'],
                ],
            ],
            'ProductUpdateRequest' => [
                'type' => 'object',
                'description' => 'All product fields are optional on update.',
                'properties' => [
                    'category_id' => ['type' => 'integer', 'example' => 1],
                    'brand_id' => ['type' => 'integer', 'example' => 1],
                    'warranty_policy_id' => ['type' => 'integer', 'nullable' => true],
                    'name' => ['type' => 'string', 'maxLength' => 255, 'example' => 'iPhone 15 Pro Max'],
                    'slug' => ['type' => 'string', 'maxLength' => 191, 'example' => 'iphone-15-pro-max'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'specifications' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'example' => 29990000],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'active'],
                    'variants' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductVariantInput'],
                    ],
                    'images' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductImageInput'],
                    ],
                    'warranty_policy' => ['$ref' => '#/components/schemas/WarrantyPolicyInput'],
                ],
            ],
            'ProductVariantInput' => [
                'type' => 'object',
                'required' => ['price', 'sku'],
                'properties' => [
                    'color' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50, 'example' => 'Natural Titanium'],
                    'ram' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50, 'example' => '8GB'],
                    'storage' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50, 'example' => '256GB'],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                    'sku' => ['type' => 'string', 'maxLength' => 191],
                    'barcode' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20],
                ],
            ],
            'ProductImageInput' => [
                'type' => 'object',
                'required' => ['image_url'],
                'properties' => [
                    'product_variant_id' => ['type' => 'integer', 'nullable' => true],
                    'image_url' => ['type' => 'string', 'maxLength' => 255, 'example' => 'uploads/products/example.webp'],
                    'is_thumbnail' => ['type' => 'boolean', 'nullable' => true],
                ],
            ],
            'WarrantyPolicyInput' => [
                'type' => 'object',
                'nullable' => true,
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'duration_months' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true],
                    'warranty_months' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true, 'description' => 'Alias accepted and normalized to duration_months.'],
                    'return_days' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true],
                    'exchange_days' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true],
                    'repair_support' => ['type' => 'boolean', 'nullable' => true],
                    'repair_supported' => ['type' => 'boolean', 'nullable' => true, 'description' => 'Alias accepted and normalized to repair_support.'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20],
                ],
            ],
            'GenericResourceRequest' => [
                'type' => 'object',
                'description' => 'Payload is filtered by the target model fillable fields. Use the resource-specific model fields from brands, categories, products, product variants, inventories, inventory transactions, product images, or warranty policies.',
                'additionalProperties' => true,
            ],
            'Brand' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'Category' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'WarrantyPolicy' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'duration_months' => ['type' => 'integer'],
                    'return_days' => ['type' => 'integer'],
                    'exchange_days' => ['type' => 'integer'],
                    'repair_support' => ['type' => 'boolean'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'ProductVariant' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'product_id' => ['type' => 'integer'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'ram' => ['type' => 'string', 'nullable' => true],
                    'storage' => ['type' => 'string', 'nullable' => true],
                    'price' => ['type' => 'string', 'example' => '29990000.00'],
                    'sku' => ['type' => 'string'],
                    'barcode' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'ProductImage' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'product_id' => ['type' => 'integer'],
                    'product_variant_id' => ['type' => 'integer', 'nullable' => true],
                    'image_url' => ['type' => 'string'],
                    'is_thumbnail' => ['type' => 'boolean'],
                ],
            ],
            'Product' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'category_id' => ['type' => 'integer'],
                    'brand_id' => ['type' => 'integer'],
                    'warranty_policy_id' => ['type' => 'integer', 'nullable' => true],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'specifications' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'price' => ['type' => 'string', 'example' => '29990000.00'],
                    'status' => ['type' => 'string', 'nullable' => true],
                    'category' => ['$ref' => '#/components/schemas/Category'],
                    'brand' => ['$ref' => '#/components/schemas/Brand'],
                    'variants' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductVariant'],
                    ],
                    'images' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductImage'],
                    ],
                    'warranty_policy' => ['$ref' => '#/components/schemas/WarrantyPolicy'],
                ],
            ],
            'UploadResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Upload anh thanh cong'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'image_url' => ['type' => 'string', 'example' => 'uploads/products/20260615120000-abc.webp'],
                            'url' => ['type' => 'string', 'format' => 'uri'],
                        ],
                    ],
                ],
            ],
            'ProductResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Product'],
                ],
            ],
            'ProductCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Product'],
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
