<?php

namespace App\EventListener;

use App\Constants\ErrorCode;
use App\Exception\ValidationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class LoginRequestValidationListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only validate login endpoint
        if ($request->getPathInfo() !== '/api/auth/login' || $request->getMethod() !== 'POST') {
            return;
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['email'])) {
            throw new ValidationException(ErrorCode::VALIDATION_EMAIL_REQUIRED);
        }

        if (!isset($data['password'])) {
            throw new ValidationException(ErrorCode::VALIDATION_PASSWORD_REQUIRED);
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(ErrorCode::VALIDATION_EMAIL_INVALID);
        }

        // Validate email length
        if (strlen($data['email']) > 180) {
            throw new ValidationException(ErrorCode::VALIDATION_EMAIL_TOO_LONG);
        }

        // Validate password is not empty
        if (empty(trim($data['password']))) {
            throw new ValidationException(ErrorCode::VALIDATION_PASSWORD_EMPTY);
        }
    }
}
