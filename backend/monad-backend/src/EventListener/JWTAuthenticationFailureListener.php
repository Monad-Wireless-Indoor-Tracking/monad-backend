<?php

namespace App\EventListener;

use App\Constants\ErrorCode;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure', method: 'onAuthenticationFailure')]
class JWTAuthenticationFailureListener
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        // Note: We can't throw ApiException here because this is a JWT event, not a kernel event
        // The ApiExceptionListener wouldn't catch it, so we format the response manually
        $response = new JsonResponse([
            'code' => ErrorCode::AUTH_INVALID_CREDENTIALS,
            'message' => ErrorCode::getDescription(ErrorCode::AUTH_INVALID_CREDENTIALS)
        ], JsonResponse::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }
}
