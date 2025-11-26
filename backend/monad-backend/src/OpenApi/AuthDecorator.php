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
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                        'name' => ['type' => 'string', 'example' => 'John Doe', 'nullable' => true],
                                        'token' => ['type' => 'string', 'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...', 'description' => 'JWT authentication token'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - validation errors

<table>
  <thead>
    <tr>
      <th>Error Code</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>VALIDATION_100</code></td>
      <td>Email address is required</td>
    </tr>
    <tr>
      <td><code>VALIDATION_101</code></td>
      <td>Email address format is invalid</td>
    </tr>
    <tr>
      <td><code>VALIDATION_103</code></td>
      <td>Password is required</td>
    </tr>
    <tr>
      <td><code>VALIDATION_107</code></td>
      <td>Name is too long (max 255 characters)</td>
    </tr>
    <tr>
      <td><code>AUTH_007</code></td>
      <td>Email address already registered</td>
    </tr>
  </tbody>
</table>',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => [
                                            'type' => 'string',
                                            'example' => 'VALIDATION_101',
                                            'description' => 'Error code',
                                            'enum' => ['VALIDATION_100', 'VALIDATION_101', 'VALIDATION_103', 'VALIDATION_107', 'AUTH_007']
                                        ],
                                        'message' => ['type' => 'string', 'example' => 'Email address format is invalid', 'description' => 'Human-readable error message'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '500' => new Response(
                        description: 'Internal server error

<table>
  <thead>
    <tr>
      <th>Error Code</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>SYSTEM_900</code></td>
      <td>Internal server error</td>
    </tr>
  </tbody>
</table>',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => [
                                            'type' => 'string',
                                            'example' => 'SYSTEM_900',
                                            'description' => 'Error code',
                                            'enum' => ['SYSTEM_900']
                                        ],
                                        'message' => ['type' => 'string', 'example' => 'Internal server error', 'description' => 'Human-readable error message'],
                                    ],
                                ])
                            ),
                        ])
                    ),
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
                                        'example' => 'user1@monad.sk',
                                        'description' => 'User email address',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                        'example' => 'password123',
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
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                        'name' => ['type' => 'string', 'example' => 'John Doe', 'nullable' => true],
                                        'token' => ['type' => 'string', 'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...', 'description' => 'JWT authentication token'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - invalid credentials

<table>
  <thead>
    <tr>
      <th>Error Code</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>AUTH_001</code></td>
      <td>Invalid email or password</td>
    </tr>
  </tbody>
</table>',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => [
                                            'type' => 'string',
                                            'example' => 'AUTH_001',
                                            'description' => 'Error code',
                                            'enum' => ['AUTH_001']
                                        ],
                                        'message' => ['type' => 'string', 'example' => 'Invalid email or password', 'description' => 'Human-readable error message'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - validation errors

<table>
  <thead>
    <tr>
      <th>Error Code</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>VALIDATION_100</code></td>
      <td>Email address is required</td>
    </tr>
    <tr>
      <td><code>VALIDATION_101</code></td>
      <td>Email address format is invalid</td>
    </tr>
    <tr>
      <td><code>VALIDATION_102</code></td>
      <td>Email address is too long (max 180 characters)</td>
    </tr>
    <tr>
      <td><code>VALIDATION_103</code></td>
      <td>Password is required</td>
    </tr>
    <tr>
      <td><code>VALIDATION_104</code></td>
      <td>Password cannot be empty</td>
    </tr>
  </tbody>
</table>',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => [
                                            'type' => 'string',
                                            'example' => 'VALIDATION_100',
                                            'description' => 'Error code',
                                            'enum' => ['VALIDATION_100', 'VALIDATION_101', 'VALIDATION_102', 'VALIDATION_103', 'VALIDATION_104']
                                        ],
                                        'message' => ['type' => 'string', 'example' => 'Email address is required', 'description' => 'Human-readable error message'],
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
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com', 'description' => 'User email address'],
                                        'name' => ['type' => 'string', 'example' => 'John Doe', 'nullable' => true, 'description' => 'User full name'],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - missing or invalid token

<table>
  <thead>
    <tr>
      <th>Error Code</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>AUTH_006</code></td>
      <td>Authentication required</td>
    </tr>
    <tr>
      <td><code>AUTH_004</code></td>
      <td>Authentication token has expired</td>
    </tr>
    <tr>
      <td><code>AUTH_005</code></td>
      <td>Authentication token is invalid</td>
    </tr>
  </tbody>
</table>',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => [
                                            'type' => 'string',
                                            'example' => 'AUTH_006',
                                            'description' => 'Error code',
                                            'enum' => ['AUTH_006', 'AUTH_004', 'AUTH_005']
                                        ],
                                        'message' => ['type' => 'string', 'example' => 'Authentication required', 'description' => 'Human-readable error message'],
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
