<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8002'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore Catalog Service',
        'version' => '1.0.0',
        'description' => 'Catalog APIs for products, banners, brands, categories, inventory, images, uploads, and warranty policies.',
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
        ['name' => 'Banners', 'description' => 'Banner management endpoints.'],
        ['name' => 'Categories', 'description' => 'Public active category endpoints.'],
        ['name' => 'Brands', 'description' => 'Public active brand endpoints.'],
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
                'summary' => 'Upload a product image to Cloudinary',
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
                                        'description' => 'jpg, jpeg, png, or webp. Max 5 MB.',
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
        '/banners' => [
            'get' => [
                'tags' => ['Banners'],
                'summary' => 'List banners',
                'operationId' => 'listBanners',
                'parameters' => [
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Banner list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BannerCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Banners'],
                'summary' => 'Create a banner',
                'operationId' => 'createBanner',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/BannerRequest'],
                        ],
                        'multipart/form-data' => [
                            'schema' => ['$ref' => '#/components/schemas/BannerRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Banner created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BannerResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/banners/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Banners'],
                'summary' => 'Show a banner',
                'operationId' => 'showBanner',
                'responses' => [
                    '200' => [
                        'description' => 'Banner returned',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BannerResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Banners'],
                'summary' => 'Replace a banner',
                'operationId' => 'replaceBanner',
                'requestBody' => ['$ref' => '#/components/requestBodies/BannerUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'Banner updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BannerResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'patch' => [
                'tags' => ['Banners'],
                'summary' => 'Partially update a banner',
                'operationId' => 'updateBanner',
                'requestBody' => ['$ref' => '#/components/requestBodies/BannerUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'Banner updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BannerResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Banners'],
                'summary' => 'Delete a banner',
                'operationId' => 'deleteBanner',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/DeleteResponse'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
        '/categories' => [
            'get' => [
                'tags' => ['Categories'],
                'summary' => 'List active categories',
                'operationId' => 'listActiveCategories',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/HeaderLimitFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Active category list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CategoryCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/brands' => [
            'get' => [
                'tags' => ['Brands'],
                'summary' => 'List active brands',
                'operationId' => 'listActiveBrands',
                'responses' => [
                    '200' => [
                        'description' => 'Active brand list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BrandCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/admin/brands' => [
            'get' => [
                'tags' => ['Brands'],
                'summary' => 'Admin list brands',
                'operationId' => 'adminListBrands',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/HeaderLimitFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Paginated brand list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/AdminBrandCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Brands'],
                'summary' => 'Admin create a brand',
                'operationId' => 'adminCreateBrand',
                'requestBody' => ['$ref' => '#/components/requestBodies/BrandCreate'],
                'responses' => [
                    '201' => [
                        'description' => 'Brand created',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BrandResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '502' => ['$ref' => '#/components/responses/UploadError'],
                ],
            ],
        ],
        '/admin/brands/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'put' => [
                'tags' => ['Brands'],
                'summary' => 'Admin update a brand',
                'operationId' => 'adminUpdateBrand',
                'requestBody' => ['$ref' => '#/components/requestBodies/BrandUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'Brand updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BrandResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '502' => ['$ref' => '#/components/responses/UploadError'],
                ],
            ],
            'delete' => [
                'tags' => ['Brands'],
                'summary' => 'Admin delete a brand',
                'operationId' => 'adminDeleteBrand',
                'description' => 'Brands that still have products cannot be deleted; lock them with toggle-status instead.',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/DeleteResponse'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '409' => ['$ref' => '#/components/responses/BrandInUse'],
                ],
            ],
        ],
        '/admin/brands/{id}/toggle-status' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'patch' => [
                'tags' => ['Brands'],
                'summary' => 'Admin lock or unlock a brand',
                'operationId' => 'adminToggleBrandStatus',
                'responses' => [
                    '200' => [
                        'description' => 'Brand status toggled',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BrandResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
        '/products' => [
            'get' => [
                'tags' => ['Products'],
                'summary' => 'List products',
                'operationId' => 'listProducts',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/LimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryIdFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandIdFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/KeywordFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                    ['$ref' => '#/components/parameters/ProductSort'],
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
        '/products/sale' => [
            'get' => [
                'tags' => ['Products'],
                'summary' => 'List sale products',
                'operationId' => 'listSaleProducts',
                'description' => 'Returns only products with discount_percent greater than 0. sale_percent is still accepted as a backward-compatible alias.',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/LimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryIdFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandIdFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/KeywordFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Sale product list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/products/new' => [
            'get' => [
                'tags' => ['Products'],
                'summary' => 'List newest products',
                'operationId' => 'listNewestProducts',
                'description' => 'Returns the latest products ordered by created_at desc. The page size is capped at 20.',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageFilter'],
                    ['$ref' => '#/components/parameters/NewProductLimitFilter'],
                    ['$ref' => '#/components/parameters/CategoryIdFilter'],
                    ['$ref' => '#/components/parameters/CategoryFilter'],
                    ['$ref' => '#/components/parameters/BrandIdFilter'],
                    ['$ref' => '#/components/parameters/BrandFilter'],
                    ['$ref' => '#/components/parameters/KeywordFilter'],
                    ['$ref' => '#/components/parameters/SearchFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Newest product list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ProductCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/products/{slug}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/ProductSlug'],
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
        ],
        '/products/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Products'],
                'summary' => 'Show a product by id',
                'operationId' => 'showProductById',
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
            'ProductSlug' => [
                'name' => 'slug',
                'in' => 'path',
                'required' => true,
                'description' => 'Unique product slug generated from the product name.',
                'schema' => ['type' => 'string', 'example' => 'lenovo-tab-p12-special-edition'],
            ],
            'CatalogResource' => [
                'name' => 'resource',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        'banners',
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
            'PageFilter' => [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ],
            'LimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'description' => 'Number of products per page. Maximum 30.',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 12],
            ],
            'HeaderLimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'description' => 'Number of records per page. Maximum 100.',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            ],
            'NewProductLimitFilter' => [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'description' => 'Number of newest products per page. Maximum 20.',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 20],
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
                'description' => 'Category id, slug, or category name keyword. Locked categories are not returned.',
                'schema' => ['type' => 'string'],
            ],
            'BrandIdFilter' => [
                'name' => 'brand_id',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer'],
            ],
            'BrandFilter' => [
                'name' => 'brand',
                'in' => 'query',
                'required' => false,
                'description' => 'Brand id, slug, or brand name keyword. Locked brands are not returned.',
                'schema' => ['type' => 'string'],
            ],
            'KeywordFilter' => [
                'name' => 'keyword',
                'in' => 'query',
                'required' => false,
                'description' => 'Search product name, slug, brand name, or category name.',
                'schema' => ['type' => 'string'],
            ],
            'SearchFilter' => [
                'name' => 'search',
                'in' => 'query',
                'required' => false,
                'description' => 'Search keyword. For products this is an alias of keyword.',
                'schema' => ['type' => 'string'],
            ],
            'StatusFilter' => [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string', 'example' => 'active'],
            ],
            'ProductSort' => [
                'name' => 'sort',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        'price_asc',
                        'price_desc',
                        'name_asc',
                        'name_desc',
                        'oldest',
                        'created_at_asc',
                        'latest',
                        'newest',
                        'created_at_desc',
                    ],
                    'example' => 'price_asc',
                ],
            ],
        ],
        'requestBodies' => [
            'BannerUpdate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/BannerRequest'],
                    ],
                    'multipart/form-data' => [
                        'schema' => ['$ref' => '#/components/schemas/BannerRequest'],
                    ],
                ],
            ],
            'BrandCreate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandCreateRequest'],
                    ],
                    'multipart/form-data' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandMultipartUpdateRequest'],
                    ],
                ],
            ],
            'BrandUpdate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandUpdateRequest'],
                    ],
                    'multipart/form-data' => [
                        'schema' => ['$ref' => '#/components/schemas/BrandMultipartRequest'],
                    ],
                ],
            ],
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
            'BrandInUse' => [
                'description' => 'Brand still has products',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        'example' => [
                            'success' => false,
                            'message' => 'Nhãn hàng đang được sử dụng.',
                        ],
                    ],
                ],
            ],
            'UploadError' => [
                'description' => 'Logo upload failed',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        'example' => [
                            'success' => false,
                            'message' => 'Upload logo that bai',
                        ],
                    ],
                ],
            ],
        ],
        'schemas' => [
            'BannerRequest' => [
                'type' => 'object',
                'description' => 'Use image for Cloudinary upload to bstore/banners, or image_url for a manually entered full image URL.',
                'properties' => [
                    'title' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'subtitle' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'button_text' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'button_link' => ['type' => 'string', 'nullable' => true, 'maxLength' => 500],
                    'image' => [
                        'type' => 'string',
                        'format' => 'binary',
                        'nullable' => true,
                        'description' => 'jpg, jpeg, png, or webp. Max 5 MB.',
                    ],
                    'image_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true, 'maxLength' => 500, 'example' => 'https://res.cloudinary.com/demo/image/upload/v123/bstore/banners/sale.webp'],
                    'product_image_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Existing product_images.id. When provided, image_url is copied from that image.'],
                    'image_source' => ['type' => 'string', 'nullable' => true, 'enum' => ['url', 'database'], 'example' => 'url'],
                    'route' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255, 'example' => '/products'],
                    'display_slot' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3, 'nullable' => true, 'description' => 'Home banner frame: 1 large, 2 top-right, 3 bottom-right.'],
                    'sort_order' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true],
                    'status' => ['type' => 'boolean', 'nullable' => true],
                ],
            ],
            'BrandCreateRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191, 'description' => 'Generated from name when omitted or blank.', 'example' => 'apple'],
                    'logo' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500, 'description' => 'Logo URL. Use multipart/form-data logo for file upload.', 'example' => 'https://cdn.example.test/brands/apple.svg'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'enum' => ['active', 'inactive'], 'default' => 'active'],
                ],
            ],
            'BrandUpdateRequest' => [
                'type' => 'object',
                'description' => 'All fields are optional on update.',
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191, 'description' => 'Generated from name when sent blank.', 'example' => 'apple'],
                    'logo' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500, 'description' => 'Logo URL. Use multipart/form-data logo for file upload. Send null to clear logo.'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                ],
            ],
            'BrandMultipartRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'logo' => [
                        'type' => 'string',
                        'format' => 'binary',
                        'nullable' => true,
                        'description' => 'jpg, jpeg, png, webp, or svg. Max 2 MB.',
                    ],
                    'logo_url' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500, 'description' => 'Alternative URL field when not uploading a file.'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'enum' => ['active', 'inactive'], 'default' => 'active'],
                ],
            ],
            'BrandMultipartUpdateRequest' => [
                'type' => 'object',
                'description' => 'All fields are optional on update.',
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Apple'],
                    'slug' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'logo' => [
                        'type' => 'string',
                        'format' => 'binary',
                        'nullable' => true,
                        'description' => 'jpg, jpeg, png, webp, or svg. Max 2 MB.',
                    ],
                    'logo_url' => ['type' => 'string', 'nullable' => true, 'format' => 'uri', 'maxLength' => 500, 'description' => 'Alternative URL field when not uploading a file.'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'enum' => ['active', 'inactive']],
                ],
            ],
            'ProductCreateRequest' => [
                'type' => 'object',
                'required' => ['category_id', 'brand_id', 'name', 'price'],
                'properties' => [
                    'category_id' => ['type' => 'integer', 'example' => 1],
                    'brand_id' => ['type' => 'integer', 'example' => 1],
                    'warranty_policy_id' => ['type' => 'integer', 'nullable' => true],
                    'name' => ['type' => 'string', 'maxLength' => 255, 'example' => 'iPhone 15 Pro Max'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'specifications' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'example' => 29990000],
                    'sale_percent' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 100, 'nullable' => true, 'example' => 10],
                    'discount_percent' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 100, 'nullable' => true, 'example' => 10],
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
                    'description' => ['type' => 'string', 'nullable' => true],
                    'specifications' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'example' => 29990000],
                    'sale_percent' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 100, 'nullable' => true, 'example' => 10],
                    'discount_percent' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 100, 'nullable' => true, 'example' => 10],
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
                    'image_url' => ['type' => 'string', 'maxLength' => 500, 'example' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/example.webp'],
                    'public_id' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255, 'example' => 'bstore/products/example'],
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
                'description' => 'Payload is filtered by the target model fillable fields. Use the resource-specific model fields from banners, brands, categories, products, product variants, inventories, inventory transactions, product images, or warranty policies.',
                'additionalProperties' => true,
            ],
            'Banner' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string', 'nullable' => true],
                    'subtitle' => ['type' => 'string', 'nullable' => true],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'button_text' => ['type' => 'string', 'nullable' => true],
                    'button_link' => ['type' => 'string', 'nullable' => true],
                    'image_url' => ['type' => 'string'],
                    'public_id' => ['type' => 'string', 'nullable' => true, 'example' => 'bstore/banners/sale'],
                    'product_image_id' => ['type' => 'integer', 'nullable' => true],
                    'image_source' => ['type' => 'string', 'enum' => ['url', 'database']],
                    'route' => ['type' => 'string', 'nullable' => true],
                    'display_slot' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3],
                    'sort_order' => ['type' => 'integer'],
                    'status' => ['type' => 'boolean', 'nullable' => true],
                    'product_image' => ['$ref' => '#/components/schemas/ProductImage'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'Brand' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'logo' => ['type' => 'string', 'nullable' => true, 'example' => 'https://cdn.example.test/brands/lenovo.svg'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'HeaderBrand' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'logo' => ['type' => 'string', 'nullable' => true],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'example' => 'active'],
                ],
            ],
            'ProductBrand' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'logo' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'Category' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'icon' => ['type' => 'string', 'nullable' => true, 'example' => 'uploads/categories/laptop.svg'],
                    'status' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'HeaderCategory' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'example' => 'active'],
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
                    'public_id' => ['type' => 'string', 'nullable' => true, 'example' => 'bstore/products/example'],
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
                    'sale_percent' => ['type' => 'string', 'nullable' => true, 'example' => '10.00'],
                    'discount_percent' => ['type' => 'string', 'nullable' => true, 'example' => '10.00'],
                    'sale_price' => ['type' => 'string', 'nullable' => true, 'example' => '26991000.00'],
                    'is_sale' => ['type' => 'boolean', 'example' => true],
                    'status' => ['type' => 'string', 'nullable' => true],
                    'category' => ['$ref' => '#/components/schemas/Category'],
                    'brand' => ['$ref' => '#/components/schemas/ProductBrand'],
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
            'ProductListItem' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'price' => ['type' => 'string', 'example' => '29990000.00'],
                    'original_price' => ['type' => 'string', 'example' => '29990000.00'],
                    'sale_percent' => ['type' => 'string', 'nullable' => true, 'example' => '10.00'],
                    'discount_percent' => ['type' => 'string', 'nullable' => true, 'example' => '10.00'],
                    'sale_price' => ['type' => 'string', 'nullable' => true, 'example' => '27990000.00'],
                    'discounted_price' => ['type' => 'string', 'nullable' => true, 'example' => '27990000.00'],
                    'is_sale' => ['type' => 'boolean', 'example' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'example' => 'active'],
                    'thumbnail' => ['type' => 'string', 'nullable' => true],
                    'category_id' => ['type' => 'integer', 'nullable' => true],
                    'category_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Phone'],
                    'brand_id' => ['type' => 'integer', 'nullable' => true],
                    'brand_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Lenovo'],
                    'brand' => ['$ref' => '#/components/schemas/ProductBrand'],
                    'rating' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'example' => 4.8],
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
            'UploadResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Upload anh thanh cong'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'image_url' => ['type' => 'string', 'example' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/example.webp'],
                            'url' => ['type' => 'string', 'format' => 'uri'],
                            'public_id' => ['type' => 'string', 'nullable' => true, 'example' => 'bstore/products/example'],
                        ],
                    ],
                ],
            ],
            'BannerResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Banner'],
                ],
            ],
            'BannerCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Banner'],
                    ],
                ],
            ],
            'CategoryCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Success'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/HeaderCategory'],
                    ],
                    'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                ],
            ],
            'BrandCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Success'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/HeaderBrand'],
                    ],
                ],
            ],
            'AdminBrandCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Success'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Brand'],
                    ],
                    'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                ],
            ],
            'BrandResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/Brand'],
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
                    'message' => ['type' => 'string', 'example' => 'Success'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ProductListItem'],
                    ],
                    'pagination' => ['$ref' => '#/components/schemas/Pagination'],
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
