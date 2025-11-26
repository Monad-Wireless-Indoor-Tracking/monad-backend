<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automatically converts ApiException instances to formatted JSON responses
 * with only 'code' and 'message' fields
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Only handle our custom ApiException instances
        if (!$exception instanceof ApiException) {
            return;
        }

        $response = new JsonResponse([
            'code' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
        ], $exception->getStatusCode());

        $event->setResponse($response);
    }
}
