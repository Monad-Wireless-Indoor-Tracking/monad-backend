<?php

namespace App\Controller;

use App\Dto\Lab\ArmingMatrixResponseDto;
use App\Entity\Quest;
use App\Quest\QuestArmingService;
use App\Repository\DeviceRepository;
use App\Repository\QuestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * "What is armed where, and why not?" — the ops read behind `/quests/config` (IP-129 §4.2).
 *
 * The data already exists; the projection did not. `GET /api/quests` carries no window and no
 * arming, `GET /api/quest/{id}` carries no device dimension at all, and `GET /api/device/{slug}`
 * carries the availability but drops three of its reasons on the floor.
 *
 * THE ASSESSMENT HERE IS UNFILTERED, AND THAT IS THE WHOLE POINT.
 *
 * `QuestAvailabilityFilter::isHidden()` removes `window_closed`, `not_armed` and
 * `device_inactive` from a participant's list, which is right: a quest outside its window or not
 * offered at this box is noise to a stranger standing in front of it. Inheriting that filter here
 * would make the matrix useless in the specific way that matters — an empty cell would mean
 * "outside its window" or "not armed here" or "node out of service" or "we filtered it", and the
 * researcher checking arming from a corridor before a session would read the first as the second
 * and stand down a quest that was fine.
 *
 * UNAUTHENTICATED, like the two public reads either side of it. What that publishes is
 * experimental design — accepted by owner decision 4 — and two properties bound what it buys an
 * adversary: the answer key is private (`QuestStepDto`, closed 2026-08-14), and points are a
 * static float echoed on completion with no ledger behind them, so knowing the design does not
 * let anyone accumulate anything. The `security.yaml` entry is anchored (`^/api/lab/arming-matrix$`)
 * for the reason every entry in that block is: `/api/lab/*` is otherwise authenticated because it
 * carries AP credentials and accepts ground-truth writes, and only this one read is public.
 */
class ArmingMatrixController extends AbstractController
{
    public function __construct(
        private readonly QuestRepository $quests,
        private readonly DeviceRepository $devices,
        private readonly QuestArmingService $arming,
    ) {
    }

    #[Route('/api/lab/arming-matrix', name: 'api_lab_arming_matrix', methods: ['GET'])]
    public function matrix(): JsonResponse
    {
        // Every device, not just the active ones: `device_inactive` is a cell state the ops view
        // must be able to show, and a node dropped from the list would show it as a missing
        // column instead — the same ambiguity the filter above was rejected for.
        $devices = $this->devices->findBy([], ['slug' => 'ASC']);

        $rows = [];
        foreach ($this->quests->findAll() as $quest) {
            if (!$quest instanceof Quest) {
                continue;
            }

            $requiresCapture = QuestArmingService::producesMeasurement($quest);

            $cells = [];
            foreach ($devices as $device) {
                $availability = $this->arming->assess(
                    quest: $quest,
                    // No user, like the device page: this describes the world — window, arming,
                    // node state — never a participant's history. `assess()` returns
                    // `available()` for a guest before any cooldown is evaluated, so `retry_at`
                    // is always null here and the column is a shape, not a value.
                    user: null,
                    device: $device,
                    requiresCapture: $requiresCapture,
                );

                $cells[] = [
                    'slug' => $device->getSlug(),
                    // Not derived from the reason: a quest armed at a node can still be blocked
                    // by its window, and an ops view has to be able to see arming and blocking
                    // as the two independent facts they are.
                    'armed' => $quest->isArmedAt($device),
                    'availability' => $availability->jsonSerialize(),
                ];
            }

            $rows[] = [
                'id' => (string) $quest->getId(),
                'name' => $quest->getName(),
                'description' => $quest->getDescription(),
                'points' => $quest->getPoints(),
                'estimated_duration' => $quest->getEstimatedDuration(),
                'available_from' => $quest->getAvailableFrom()?->format(\DateTimeInterface::ATOM),
                'available_to' => $quest->getAvailableTo()?->format(\DateTimeInterface::ATOM),
                'recurrence' => $quest->getRecurrencePolicy()?->jsonSerialize(),
                'required_capabilities' => $quest->getRequiredCapabilities(),
                'devices' => $cells,
            ];
        }

        $response = $this->json((new ArmingMatrixResponseDto($rows))->toArray());
        // The same short, uniform cache the other two public reads carry: the portal page in
        // front of this is the load-shedding layer.
        $response->headers->set('Cache-Control', 'public, max-age=30');

        return $response;
    }
}
