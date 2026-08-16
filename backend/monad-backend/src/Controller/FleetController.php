<?php

namespace App\Controller;

use App\Fleet\FleetMetricsReader;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The fleet's public vital signs, for the website to render.
 *
 * This exists so that `monad.dubec.dev` — a host process, and the most exposed
 * thing on the box — never needs a route into the observability stack. Mimir has
 * no auth of its own and publishes no host port; this container is on the same
 * docker bridge, so it reads `mimir:9009` by container DNS and hands over only
 * what `FleetMetricsReader`'s closed allow-list decided to publish.
 *
 * NOT on the public Internet. The `api.monad.dubec.dev` vhost 404s this path,
 * the same treatment `/admin` and `/mcp` get: the website calls it over loopback
 * and an operator reaches it on the tailnet. The data is not secret — the site
 * renders it to anyone — but a metrics surface that the world can poll at
 * whatever rate it likes is a different thing from a cached page, and the
 * asymmetry is free to keep.
 *
 * Unauthenticated by necessity and by design: the site holds no JWT and learns
 * nothing about its readers, so requiring one would mean giving a public,
 * anonymous, cookie-less website a credential to store.
 */
class FleetController extends AbstractController
{
    public function __construct(
        private readonly FleetMetricsReader $fleet,
    ) {
    }

    #[Route('/api/lab/fleet', name: 'api_lab_fleet', methods: ['GET'])]
    #[OA\Get(
        path: '/api/lab/fleet',
        summary: 'Public vital signs for every fleet node',
        description: 'Per-node readings and fleet-wide scalars from the metrics store, as a closed allow-list. `reachable: false` means the store could not be read — which is a different fact from a resting fleet, and the caller is expected to say so rather than render zeros. Not reachable from the public Internet; the site calls it over loopback.',
        tags: ['Lab']
    )]
    #[OA\Response(response: 200, description: 'Fleet snapshot')]
    public function fleet(): JsonResponse
    {
        $snapshot = $this->fleet->snapshot();

        $response = $this->json($snapshot, Response::HTTP_OK);
        // Equal to the reader's own cache TTL: no layer is ever fresher than its
        // source, and the caller caches on the same window.
        $response->headers->set('Cache-Control', 'public, max-age=30');

        return $response;
    }
}
