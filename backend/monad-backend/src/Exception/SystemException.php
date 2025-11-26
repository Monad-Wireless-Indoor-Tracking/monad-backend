<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for system/server errors
 *
 * Usage:
 *   throw new SystemException(ErrorCode::SYSTEM_INTERNAL_ERROR);
 *   throw new SystemException(ErrorCode::SYSTEM_SERVICE_UNAVAILABLE, Response::HTTP_SERVICE_UNAVAILABLE);
 */
class SystemException extends ApiException
{
    protected function getDefaultStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
