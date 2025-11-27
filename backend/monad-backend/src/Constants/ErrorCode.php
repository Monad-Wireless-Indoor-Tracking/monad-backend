<?php

namespace App\Constants;

/**
 * Error codes for API responses
 * Format: CATEGORY_XXX where XXX is a 3-digit number
 *
 * Categories:
 * - AUTH: Authentication and authorization errors (001-099)
 * - VALIDATION: Input validation errors (100-199)
 * - RESOURCE: Resource-related errors (200-299)
 * - SYSTEM: System and server errors (900-999)
 */
class ErrorCode
{
    // Authentication & Authorization (001-099)
    public const AUTH_INVALID_CREDENTIALS = 'AUTH_001';
    public const AUTH_EMAIL_NOT_FOUND = 'AUTH_002';
    public const AUTH_ACCOUNT_DISABLED = 'AUTH_003';
    public const AUTH_TOKEN_EXPIRED = 'AUTH_004';
    public const AUTH_TOKEN_INVALID = 'AUTH_005';
    public const AUTH_UNAUTHORIZED = 'AUTH_006';
    public const AUTH_EMAIL_ALREADY_EXISTS = 'AUTH_007';

    // Validation Errors (100-199)
    public const VALIDATION_EMAIL_REQUIRED = 'VALIDATION_100';
    public const VALIDATION_EMAIL_INVALID = 'VALIDATION_101';
    public const VALIDATION_EMAIL_TOO_LONG = 'VALIDATION_102';
    public const VALIDATION_PASSWORD_REQUIRED = 'VALIDATION_103';
    public const VALIDATION_PASSWORD_EMPTY = 'VALIDATION_104';
    public const VALIDATION_PASSWORD_TOO_SHORT = 'VALIDATION_105';
    public const VALIDATION_PASSWORD_TOO_LONG = 'VALIDATION_106';
    public const VALIDATION_NAME_TOO_LONG = 'VALIDATION_107';
    public const VALIDATION_FAILED = 'VALIDATION_199';

    // Resource Errors (200-299)
    public const RESOURCE_NOT_FOUND = 'RESOURCE_200';
    public const RESOURCE_ALREADY_EXISTS = 'RESOURCE_201';
    public const RESOURCE_FORBIDDEN = 'RESOURCE_202';

    // Storage Errors (300-399)
    public const STORAGE_UPLOAD_FAILED = 'STORAGE_300';
    public const STORAGE_FILE_TOO_LARGE = 'STORAGE_301';
    public const STORAGE_INVALID_FILE_TYPE = 'STORAGE_302';
    public const STORAGE_FILENAME_REQUIRED = 'STORAGE_303';
    public const STORAGE_S3_UNAVAILABLE = 'STORAGE_304';

    // System Errors (900-999)
    public const SYSTEM_INTERNAL_ERROR = 'SYSTEM_900';
    public const SYSTEM_DATABASE_ERROR = 'SYSTEM_901';
    public const SYSTEM_SERVICE_UNAVAILABLE = 'SYSTEM_902';

    /**
     * Get human-readable description for error code (for development/logging)
     */
    public static function getDescription(string $code): string
    {
        return match ($code) {
            self::AUTH_INVALID_CREDENTIALS => 'Invalid email or password',
            self::AUTH_EMAIL_NOT_FOUND => 'Email address not found',
            self::AUTH_ACCOUNT_DISABLED => 'Account has been disabled',
            self::AUTH_TOKEN_EXPIRED => 'Authentication token has expired',
            self::AUTH_TOKEN_INVALID => 'Authentication token is invalid',
            self::AUTH_UNAUTHORIZED => 'Authentication required',
            self::AUTH_EMAIL_ALREADY_EXISTS => 'Email address already registered',

            self::VALIDATION_EMAIL_REQUIRED => 'Email address is required',
            self::VALIDATION_EMAIL_INVALID => 'Email address format is invalid',
            self::VALIDATION_EMAIL_TOO_LONG => 'Email address is too long (max 180 characters)',
            self::VALIDATION_PASSWORD_REQUIRED => 'Password is required',
            self::VALIDATION_PASSWORD_EMPTY => 'Password cannot be empty',
            self::VALIDATION_PASSWORD_TOO_SHORT => 'Password is too short (min 8 characters)',
            self::VALIDATION_PASSWORD_TOO_LONG => 'Password is too long (max 255 characters)',
            self::VALIDATION_NAME_TOO_LONG => 'Name is too long (max 255 characters)',
            self::VALIDATION_FAILED => 'Validation failed',

            self::RESOURCE_NOT_FOUND => 'Requested resource not found',
            self::RESOURCE_ALREADY_EXISTS => 'Resource already exists',
            self::RESOURCE_FORBIDDEN => 'Access to resource is forbidden',

            self::STORAGE_UPLOAD_FAILED => 'File upload failed',
            self::STORAGE_FILE_TOO_LARGE => 'File size exceeds maximum allowed (50 MB)',
            self::STORAGE_INVALID_FILE_TYPE => 'File type is not allowed',
            self::STORAGE_FILENAME_REQUIRED => 'Filename is required',
            self::STORAGE_S3_UNAVAILABLE => 'Storage service is temporarily unavailable',

            self::SYSTEM_INTERNAL_ERROR => 'Internal server error',
            self::SYSTEM_DATABASE_ERROR => 'Database operation failed',
            self::SYSTEM_SERVICE_UNAVAILABLE => 'Service temporarily unavailable',

            default => 'Unknown error',
        };
    }
}
