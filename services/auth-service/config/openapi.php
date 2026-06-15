<?php

$serverUrl = rtrim((string) env('APP_URL', 'http://localhost:8001'), '/').'/api';

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'BStore Auth Service',
        'version' => '1.0.0',
        'description' => 'Authentication, users, and role APIs for BStore.',
    ],
    'servers' => [
        [
            'url' => $serverUrl,
            'description' => 'Auth Service',
        ],
    ],
    'tags' => [
        ['name' => 'Documentation', 'description' => 'OpenAPI document endpoint.'],
        ['name' => 'Auth', 'description' => 'Registration and login.'],
        ['name' => 'Users', 'description' => 'User-specific endpoints.'],
        ['name' => 'Resources', 'description' => 'Generic CRUD endpoints for supported auth resources.'],
    ],
    'paths' => [
        '/docs/openapi.json' => [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Get Auth Service OpenAPI document',
                'operationId' => 'getAuthOpenApiDocument',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/OpenApiDocument'],
                ],
            ],
        ],
        '/auth/register' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Register a user',
                'operationId' => 'registerUser',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/RegisterRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'User registered',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UserResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/auth/login' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Login with email and password',
                'operationId' => 'loginUser',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/LoginRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Login successful',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UserResponse'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/users' => [
            'get' => [
                'tags' => ['Users'],
                'summary' => 'List users with roles',
                'operationId' => 'listUsers',
                'responses' => [
                    '200' => [
                        'description' => 'User list',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UserCollectionResponse'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/users/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'put' => [
                'tags' => ['Users'],
                'summary' => 'Update a user',
                'operationId' => 'replaceUser',
                'requestBody' => ['$ref' => '#/components/requestBodies/UserUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'User updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UserResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'patch' => [
                'tags' => ['Users'],
                'summary' => 'Partially update a user',
                'operationId' => 'updateUser',
                'requestBody' => ['$ref' => '#/components/requestBodies/UserUpdate'],
                'responses' => [
                    '200' => [
                        'description' => 'User updated',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UserResponse'],
                            ],
                        ],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/{resource}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/AuthResource'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'List records for a supported resource',
                'operationId' => 'listAuthResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceCollection'],
                    '404' => ['$ref' => '#/components/responses/UnsupportedResource'],
                ],
            ],
            'post' => [
                'tags' => ['Resources'],
                'summary' => 'Create a record for a supported resource',
                'operationId' => 'createAuthResource',
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
                ['$ref' => '#/components/parameters/AuthResource'],
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Resources'],
                'summary' => 'Show a record for a supported resource',
                'operationId' => 'showAuthResource',
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Resources'],
                'summary' => 'Replace a record for a supported resource',
                'operationId' => 'replaceAuthResource',
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
                'operationId' => 'updateAuthResource',
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
                'operationId' => 'deleteAuthResource',
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
            'AuthResource' => [
                'name' => 'resource',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'enum' => ['roles', 'users'],
                ],
            ],
        ],
        'requestBodies' => [
            'UserUpdate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/UserUpdateRequest'],
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
            'Unauthorized' => [
                'description' => 'Invalid credentials',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
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
            'RegisterRequest' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'anyOf' => [
                    ['required' => ['full_name']],
                    ['required' => ['name']],
                ],
                'properties' => [
                    'role_id' => ['type' => 'integer', 'nullable' => true, 'example' => 3],
                    'full_name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Nguyen Van A'],
                    'name' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Alias accepted when full_name is omitted.'],
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191, 'example' => 'customer@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 6, 'maxLength' => 255],
                    'phone' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20],
                    'avatar' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20, 'example' => 'active'],
                ],
            ],
            'LoginRequest' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'customer@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password'],
                ],
            ],
            'UserUpdateRequest' => [
                'type' => 'object',
                'properties' => [
                    'role_id' => ['type' => 'integer', 'example' => 2],
                    'full_name' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'name' => ['type' => 'string', 'nullable' => true, 'maxLength' => 191],
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191],
                    'password' => ['type' => 'string', 'format' => 'password', 'nullable' => true, 'minLength' => 6],
                    'phone' => ['type' => 'string', 'nullable' => true, 'maxLength' => 30],
                    'avatar' => ['type' => 'string', 'nullable' => true, 'maxLength' => 500],
                    'status' => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                ],
            ],
            'GenericResourceRequest' => [
                'type' => 'object',
                'description' => 'Payload is filtered by the target model fillable fields. roles accepts name and description; users accepts role_id, full_name/name, email, password, phone, avatar, and status.',
                'additionalProperties' => true,
            ],
            'Role' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string', 'example' => 'admin'],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'role_id' => ['type' => 'integer', 'nullable' => true],
                    'full_name' => ['type' => 'string', 'example' => 'Nguyen Van A'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'avatar' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'nullable' => true, 'example' => 'active'],
                    'role' => ['$ref' => '#/components/schemas/Role'],
                ],
            ],
            'UserResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'UserCollectionResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/User'],
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
