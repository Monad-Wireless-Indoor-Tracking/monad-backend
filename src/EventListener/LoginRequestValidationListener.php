<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
        if (!isset($data['email']) || !isset($data['password'])) {
            $event->setResponse(new JsonResponse([
                'error' => 'Email and password are required'
            ], Response::HTTP_BAD_REQUEST));
            return;
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $event->setResponse(new JsonResponse([
                'error' => 'Invalid email format'
            ], Response::HTTP_BAD_REQUEST));
            return;
        }

        // Validate email length
        if (strlen($data['email']) > 180) {
            $event->setResponse(new JsonResponse([
                'error' => 'Email cannot be longer than 180 characters'
            ], Response::HTTP_BAD_REQUEST));
            return;
        }

        // Validate password is not empty
        if (empty(trim($data['password']))) {
            $event->setResponse(new JsonResponse([
                'error' => 'Password cannot be empty'
            ], Response::HTTP_BAD_REQUEST));
            return;
        }
    }
}
