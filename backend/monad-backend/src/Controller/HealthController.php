<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $dbHealthy = false;
        try {
            $this->connection->executeQuery('SELECT 1');
            $dbHealthy = true;
        } catch (\Exception $e) {
            // Database not available
        }

        return new JsonResponse([
            'status' => $dbHealthy ? 'healthy' : 'degraded',
            'database' => $dbHealthy,
            'timestamp' => (new \DateTime())->format('c'),
        ], $dbHealthy ? 200 : 503);
    }
}
