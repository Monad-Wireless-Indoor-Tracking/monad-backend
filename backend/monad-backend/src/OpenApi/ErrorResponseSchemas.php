<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Reusable error response schemas for OpenAPI documentation
 */
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Standard error response format',
    required: ['code', 'message'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'Error code constant (e.g., VALIDATION_101, AUTH_001)',
            example: 'VALIDATION_101'
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable error message',
            example: 'Email address format is invalid'
        )
    ],
    type: 'object'
)]
class ErrorResponse {}

#[OA\Schema(
    schema: 'ValidationErrorResponse',
    description: 'Validation error response (400)',
    required: ['code', 'message'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'Validation error code',
            example: 'VALIDATION_101',
            enum: [
                'VALIDATION_100', // Email required
                'VALIDATION_101', // Email invalid
                'VALIDATION_102', // Email too long
                'VALIDATION_103', // Password required
                'VALIDATION_104', // Password empty
                'VALIDATION_105', // Password too short
                'VALIDATION_106', // Password too long
                'VALIDATION_107', // Name too long
                'VALIDATION_199', // Validation failed
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable error message',
            example: 'Email address format is invalid'
        )
    ],
    type: 'object'
)]
class ValidationErrorResponse {}

#[OA\Schema(
    schema: 'AuthErrorResponse',
    description: 'Authentication/Authorization error response (401/403)',
    required: ['code', 'message'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'Authentication error code',
            example: 'AUTH_001',
            enum: [
                'AUTH_001', // Invalid credentials
                'AUTH_002', // Email not found
                'AUTH_003', // Account disabled
                'AUTH_004', // Token expired
                'AUTH_005', // Token invalid
                'AUTH_006', // Unauthorized
                'AUTH_007', // Email already exists
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable error message',
            example: 'Invalid email or password'
        )
    ],
    type: 'object'
)]
class AuthErrorResponse {}

#[OA\Schema(
    schema: 'ResourceErrorResponse',
    description: 'Resource error response (404/409/403)',
    required: ['code', 'message'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'Resource error code',
            example: 'RESOURCE_200',
            enum: [
                'RESOURCE_200', // Not found
                'RESOURCE_201', // Already exists
                'RESOURCE_202', // Forbidden
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable error message',
            example: 'Requested resource not found'
        )
    ],
    type: 'object'
)]
class ResourceErrorResponse {}

#[OA\Schema(
    schema: 'SystemErrorResponse',
    description: 'System error response (500/503)',
    required: ['code', 'message'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'System error code',
            example: 'SYSTEM_900',
            enum: [
                'SYSTEM_900', // Internal error
                'SYSTEM_901', // Database error
                'SYSTEM_902', // Service unavailable
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable error message',
            example: 'Internal server error'
        )
    ],
    type: 'object'
)]
class SystemErrorResponse {}
