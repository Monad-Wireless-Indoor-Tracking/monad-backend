<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for resource-related errors
 *
 * Usage:
 *   throw new ResourceException(ErrorCode::RESOURCE_NOT_FOUND);
 *   throw new ResourceException(ErrorCode::RESOURCE_ALREADY_EXISTS, Response::HTTP_CONFLICT);
 */
class ResourceException extends ApiException
{
    protected function getDefaultStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
