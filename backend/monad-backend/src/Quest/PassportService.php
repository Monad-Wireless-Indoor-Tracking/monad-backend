<?php

namespace App\Quest;

use App\Entity\User;
use App\Repository\DeviceRepository;
use App\Repository\QuestEnrollmentRepository;

/**
 * The device passport and contribution figures (IP-128).
 *
 * DERIVED, NEVER LEDGERED. There is no points column on `User` and none is
 * added: a mutable counter can disagree with the enrollment history, which is
 * exactly the failure the ground-truth design already refuses. A stamp *is* a
 * completed enrollment at a device, so the history is the score and the two
 * cannot drift.
 *
 * The denominator is `DeviceRepository::countActive()`, so a thirteenth node
 * changes every passport by an INSERT rather than a release.
 *
 * What is deliberately NOT here: streaks and leaderboards. Both reward
 * frequency, and frequency at one convenient node is precisely the sampling bias
 * the fleet is trying to avoid. Stamps reward *coverage*, which is the thing the
 * research actually wants.
 */
final class PassportService
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly QuestEnrollmentRepository $enrollments,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $runsBySlug = $this->enrollments->countCompletedByDevice($user);
        $active = $this->devices->findAllActive();

        $stamps = [];
        $collected = 0;
        foreach ($active as $device) {
            $slug = (string) $device->getSlug();
            $runs = $runsBySlug[$slug] ?? 0;
            if ($runs > 0) {
                ++$collected;
            }
            $stamps[] = [
                'slug' => $slug,
                'label' => $device->getLabel(),
                'collected' => $runs > 0,
                'runs' => $runs,
            ];
        }

        // Runs at nodes that have since been retired still happened. Counting
        // them in `total_runs` while leaving them out of the passport grid keeps
        // both numbers honest — the grid is "nodes you can still visit", the
        // total is "what you actually did".
        $totalRuns = array_sum($runsBySlug);

        return [
            'passport' => [
                'collected' => $collected,
                'total' => count($active),
                'stamps' => $stamps,
            ],
            'contribution' => [
                'completed_runs' => $totalRuns,
                'distinct_devices' => count(array_filter($runsBySlug, static fn (int $n): bool => $n > 0)),
            ],
        ];
    }
}
