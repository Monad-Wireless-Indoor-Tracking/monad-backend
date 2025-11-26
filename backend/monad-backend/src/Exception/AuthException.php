<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for authentication/authorization errors
 *
 * Usage:
 *   throw new AuthException(ErrorCode::AUTH_INVALID_CREDENTIALS);
 *   throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
 *   throw new AuthException(ErrorCode::AUTH_ACCOUNT_DISABLED, Response::HTTP_FORBIDDEN);
 */
class AuthException extends ApiException
{
    protected function getDefaultStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
