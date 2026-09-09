<?php

namespace App\Controller;

use App\Entity\User;
use App\Quest\PassportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * "Which nodes have I collected, and what did my walks add up to?" (IP-128)
 *
 * Authenticated and scoped to the caller — there is no `?user=` and no admin
 * variant here. A passport is per-participant by construction: the route reads
 * `getUser()` and nothing else, so there is no parameter that could be tampered
 * with to read someone else's history.
 *
 * Falls under the existing `^/api` catch-all (`IS_AUTHENTICATED_FULLY`) rather
 * than needing its own `security.yaml` line — the public exceptions above it are
 * anchored to exact paths, so nothing about IP-128 widened them.
 */
class PassportController extends AbstractController
{
    public function __construct(private readonly PassportService $passports)
    {
    }

    #[Route('/api/me/passport', name: 'api_me_passport', methods: ['GET'])]
    public function passport(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->passports->forUser($user));
    }
}
