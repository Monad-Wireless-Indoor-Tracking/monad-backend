<?php

namespace App\Exception;

use App\Constants\ErrorCode;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base exception for API errors
 * Automatically formats error responses with code and message
 *
 * Usage:
 *   throw new ValidationException(ErrorCode::VALIDATION_EMAIL_REQUIRED);
 *   throw new AuthException(ErrorCode::AUTH_INVALID_CREDENTIALS);
 */
abstract class ApiException extends HttpException
{
    protected string $errorCode;

    public function __construct(
        string $errorCode,
        ?int $statusCode = null,
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->errorCode = $errorCode;
        $message = ErrorCode::getDescription($errorCode);

        parent::__construct(
            $statusCode ?? $this->getDefaultStatusCode(),
            $message,
            $previous,
            $headers
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Default HTTP status code for this exception type
     */
    abstract protected function getDefaultStatusCode(): int;
}
