<?php

namespace App\EventListener;

use App\Constants\ErrorCode;
use App\Entity\User;
use App\Exception\AuthException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
class DeletedUserCheckListener
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        if ($user->isDeleted()) {
            throw new AuthException(ErrorCode::AUTH_ACCOUNT_DELETED, Response::HTTP_FORBIDDEN);
        }
    }
}
