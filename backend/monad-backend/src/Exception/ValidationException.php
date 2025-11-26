<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for validation errors (400 Bad Request)
 *
 * Usage:
 *   throw new ValidationException(ErrorCode::VALIDATION_EMAIL_REQUIRED);
 *   throw new ValidationException(ErrorCode::VALIDATION_EMAIL_INVALID);
 */
class ValidationException extends ApiException
{
    protected function getDefaultStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
