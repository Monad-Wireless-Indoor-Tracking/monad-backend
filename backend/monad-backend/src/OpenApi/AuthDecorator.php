<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model\Tag;

/**
 * OpenAPI decorator for Monad authentication endpoints
 */
readonly class AuthDecorator implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        // Add /api/auth/register POST - User registration
        $registerPath = new PathItem(
            post: new Operation(
                tags: ['Authentication'],
                summary: 'Register a new user',
                description: 'Creates a new user account with email and password',
                requestBody: new RequestBody(
                    description: 'User registration data',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address (must be unique)',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                        'example' => 'securePassword123',
                                        'description' => 'User password',
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                        'example' => 'John Doe',
                                        'description' => 'User full name (optional)',
                                        'nullable' => true,
                                    ],
                                ],
                            ])
                        ),
                    ])
                ),
                responses: [
                    '201' => new Response(
                        description: 'User successfully registered',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'User registered successfully'],
                                        'token' => ['type' => 'string', 'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...', 'description' => 'JWT authentication token'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string', 'format' => 'uuid', 'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9'],
                                                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                                'name' => ['type' => 'string', 'example' => 'John Doe', 'nullable' => true],
                                                'createdAt' => ['type' => 'string', 'format' => 'date-time', 'example' => '2025-11-11 15:28:44'],
                                            ],
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - validation errors',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => ['type' => 'string', 'example' => 'Validation failed'],
                                        'details' => ['type' => 'object'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '500' => new Response(description: 'Internal server error'),
                ],
                security: []
            )
        );
        $paths->addPath('/api/auth/register', $registerPath);

        // Add /api/auth/login POST - User login
        $loginPath = new PathItem(
            post: new Operation(
                tags: ['Authentication'],
                summary: 'Login',
                description: 'Login with email and password',
                requestBody: new RequestBody(
                    description: 'Login credentials',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                        'example' => 'securePassword123',
                                        'description' => 'User password',
                                    ],
                                ],
                            ])
                        ),
                    ])
                ),
                responses: [
                    '200' => new Response(
                        description: 'Successfully authenticated',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...',
                                            'description' => 'JWT authentication token',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - invalid credentials',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => ['type' => 'integer', 'example' => 401],
                                        'message' => ['type' => 'string', 'example' => 'Invalid credentials'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: []
            )
        );
        $paths->addPath('/api/auth/login', $loginPath);

        // Add /api/auth/me GET - Get current user information
        $mePath = new PathItem(
            get: new Operation(
                tags: ['User'],
                summary: 'Get current user information',
                description: 'Returns the authenticated user profile information',
                responses: [
                    '200' => new Response(
                        description: 'User information retrieved successfully',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'string',
                                                    'format' => 'uuid',
                                                    'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                                                    'description' => 'Unique user identifier',
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'format' => 'email',
                                                    'example' => 'user@example.com',
                                                    'description' => 'User email address',
                                                ],
                                                'name' => [
                                                    'type' => 'string',
                                                    'example' => 'John Doe',
                                                    'nullable' => true,
                                                    'description' => 'User full name',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                    'description' => 'User roles',
                                                ],
                                                'createdAt' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'example' => '2025-11-11 15:28:44',
                                                    'description' => 'Account creation timestamp',
                                                ],
                                            ],
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - missing or invalid token',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => ['type' => 'string', 'example' => 'Not authenticated'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: [['Bearer' => []]]
            )
        );
        $paths->addPath('/api/auth/me', $mePath);

        // Ensure tags exist
        $rootTags = $openApi->getTags();
        $hasAuth = false;
        $hasUser = false;

        foreach ($rootTags as $tag) {
            if ($tag->getName() === 'Authentication') {
                $hasAuth = true;
            }
            if ($tag->getName() === 'User') {
                $hasUser = true;
            }
        }

        $newTags = $rootTags;
        if (!$hasAuth) {
            $newTags[] = new Tag('Authentication', 'User authentication and registration endpoints');
        }
        if (!$hasUser) {
            $newTags[] = new Tag('User', 'User management endpoints');
        }

        if (!$hasAuth || !$hasUser) {
            $openApi = $openApi->withTags($newTags);
        }

        return $openApi;
    }
}
