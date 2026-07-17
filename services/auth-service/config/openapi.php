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
        ['name' => 'Internal', 'description' => 'Service-to-service endpoints.'],
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
                'summary' => 'List users with roles (Admin only)',
                'operationId' => 'listUsers',
                'security' => [['bearerAuth' => []]],
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
                'summary' => 'Update a user (Admin only)',
                'operationId' => 'replaceUser',
                'security' => [['bearerAuth' => []]],
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
                'summary' => 'Partially update a user (Admin only)',
                'operationId' => 'updateUser',
                'security' => [['bearerAuth' => []]],
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
        '/roles' => [
            'get' => [
                'tags' => ['Users'],
                'summary' => 'List roles (Admin only)',
                'operationId' => 'listRoles',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceCollection'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ],
        '/auth/refresh' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Rotate a refresh token and issue a new token pair',
                'operationId' => 'refreshAuthToken',
                'requestBody' => ['$ref' => '#/components/requestBodies/RefreshToken'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/auth/logout' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Revoke the current session',
                'operationId' => 'logoutUser',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ],
        '/internal/auth/introspect' => [
            'post' => [
                'tags' => ['Internal'],
                'summary' => 'Validate an access token and its server-side session',
                'operationId' => 'introspectAuthToken',
                'security' => [['internalService' => []]],
                'requestBody' => ['$ref' => '#/components/requestBodies/IntrospectToken'],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/internal/users/{id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/Id'],
            ],
            'get' => [
                'tags' => ['Internal'],
                'summary' => 'Get the minimal internal user profile',
                'operationId' => 'getInternalUser',
                'security' => [['internalService' => []]],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/ResourceRecord'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
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
            'internalService' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-Internal-Service-Token',
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
            'UserUpdate' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/UserUpdateRequest'],
                    ],
                ],
            ],
            'RefreshToken' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/RefreshTokenRequest'],
                    ],
                ],
            ],
            'IntrospectToken' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/IntrospectTokenRequest'],
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
                    'full_name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Nguyen Van A'],
                    'name' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Alias accepted when full_name is omitted.'],
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191, 'example' => 'customer@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 6, 'maxLength' => 255],
                    'phone' => ['type' => 'string', 'nullable' => true, 'maxLength' => 20],
                    'avatar' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
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
            'RefreshTokenRequest' => [
                'type' => 'object',
                'required' => ['refresh_token'],
                'properties' => [
                    'refresh_token' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 512],
                ],
            ],
            'IntrospectTokenRequest' => [
                'type' => 'object',
                'required' => ['token'],
                'properties' => [
                    'token' => ['type' => 'string', 'maxLength' => 4096],
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
                    'token' => ['type' => 'string', 'description' => 'Short-lived access JWT.'],
                    'refresh_token' => ['type' => 'string', 'description' => 'One-time rotating refresh token.'],
                    'expires_in' => ['type' => 'integer', 'example' => 900],
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
