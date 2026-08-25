<?php

namespace App\Mcp;

use App\Entity\Quest;
use App\Entity\QuestStep;
use App\Entity\User;
use App\Enum\QuestStepType;
use App\Service\GroundTruthService;
use App\Service\LabConfigService;
use App\Service\MarkerService;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The lab's authoring surface.
 *
 * Every tool here writes to or reads from the live database, which is the point: the previous
 * design shipped quest definitions as JSON inside the container image, so changing what a
 * participant is told meant a rebuild, a registry push and a deploy. Content that changes between
 * sessions cannot live in the deployment artefact.
 *
 * Reads are cheap and unguarded beyond the firewall; writes are attributed to the authenticated
 * account, so `quests.created_by` answers "who wrote this" without a second audit table.
 */
class LabTools
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MarkerService $markers,
        private readonly LabConfigService $labConfig,
        private readonly GroundTruthService $groundTruth,
        private readonly Security $security,
    ) {
    }

    /**
     * List every quest with its step count and availability window.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_quest_list',
        description: 'List all quests: name, availability window, estimated duration and step count.',
    )]
    public function questList(): array
    {
        $quests = $this->entityManager->getRepository(Quest::class)->findBy([], ['availableFrom' => 'DESC']);

        return ['quests' => array_map(fn (Quest $q): array => [
            'name' => $q->getName(),
            'available_from' => $q->getAvailableFrom()?->format(\DateTimeInterface::ATOM),
            'available_to' => $q->getAvailableTo()?->format(\DateTimeInterface::ATOM),
            'estimated_duration' => $q->getEstimatedDuration(),
            'steps' => $q->getSteps()->count(),
            'created_by' => $q->getCreatedBy()?->getEmail(),
        ], $quests)];
    }

    /**
     * Read one quest back in the exact shape lab_quest_write accepts.
     *
     * Round-tripping matters more than it looks: these are the instructions a pre-registered
     * experiment gave its participants, and "what were they actually told" has to stay answerable
     * months later. Read, edit one field, write back.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_quest_read',
        description: 'Read a quest by name, including every step, in the same shape lab_quest_write accepts.',
    )]
    public function questRead(string $name): array
    {
        $quest = $this->entityManager->getRepository(Quest::class)->findOneBy(['name' => $name]);
        if ($quest === null) {
            return ['error' => sprintf('No quest named "%s".', $name)];
        }

        $steps = $quest->getSteps()->toArray();
        usort($steps, static fn (QuestStep $a, QuestStep $b): int => $a->getOrder() <=> $b->getOrder());

        return [
            'name' => $quest->getName(),
            'description' => $quest->getDescription(),
            'available_from' => $quest->getAvailableFrom()?->format(\DateTimeInterface::ATOM),
            'available_to' => $quest->getAvailableTo()?->format(\DateTimeInterface::ATOM),
            'points' => $quest->getPoints(),
            'estimated_duration' => $quest->getEstimatedDuration(),
            'required_capabilities' => $quest->getRequiredCapabilities(),
            'created_by' => $quest->getCreatedBy()?->getEmail(),
            'steps' => array_map(static fn (QuestStep $s): array => [
                'order' => $s->getOrder(),
                'name' => $s->getName(),
                'type' => $s->getType()?->value,
                'config' => $s->getConfig(),
            ], $steps),
        ];
    }

    /**
     * Create or replace a quest.
     *
     * Keyed on name, and replacing rather than merging: the payload is the definition, so a step
     * dropped from it disappears instead of lingering at whatever order it held.
     *
     * @param list<array{order: int, name: string, type: string, config?: array<string, mixed>}> $steps
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_quest_write',
        description: <<<'TXT'
            Create or replace a quest by name. Steps are [{order, name, type, config}]; types are
            start, wait, scan_qr, connect_to_ap, walk_to, find_ble_device, sensor_capture,
            ble_advertise, probe, finish.

            Prefer `monad-knowledge lab quest-build` over hand-authoring a probe quest. It reads the
            surveyed placements out of PostGIS and writes the targets, so a card that moves costs a
            regenerate rather than a hand edit that nothing checks.

            A probe step (IP-140) is "scan one of these surveyed points, then hold still". It needs
            config.dwell_seconds and config.targets, each target {value, label, room, kind} with
            kind one of card|node. One target makes a treasure-hunt leg; many make a fingerprint
            probe that accepts whichever code the participant is standing at. A probe does NOT put
            the radio on air — see the features block below.

            The identity broadcast is SESSION-scoped, declared once on the start step as
            config.features {broadcast, track, witness, illuminator}. Every field defaults to false,
            so a quest that wants the frame on air across the whole run must say so. That is what
            keeps the trajectory between two probes recorded, and it is why a probe quest without
            features.broadcast records dwells with nothing broadcasting.

            A scan_qr step needs config.expected_value — that string is what the participant's scan
            is matched against, and lab_marker_svg renders it.

            A ble_advertise step needs config.duration_seconds; it broadcasts the lab identity
            frame (derived from the bundle's advertise namespace, never authored into the quest)
            and iOS honours it only while the app is in the foreground. The quest's
            required_capabilities gains "ble.advertise" automatically, so handsets that cannot
            broadcast are never offered it. Do not combine it with features.broadcast: a
            block-bracketing quest is correct only when the on-air interval equals the labelled one.

            Do NOT author connect_to_ap on this deployment: there is no AP to associate to and the
            run would block. Its credential comes from the bundle via config.ap_id, never from the
            quest — step config is served to every authenticated caller.
            TXT,
    )]
    public function questWrite(
        string $name,
        string $description,
        string $available_from,
        array $steps,
        ?string $available_to = null,
        ?int $estimated_duration = null,
        float $points = 0.0,
    ): array {
        $author = $this->security->getUser();
        if (!$author instanceof User) {
            return ['error' => 'No authenticated account; cannot attribute the quest.'];
        }

        $repository = $this->entityManager->getRepository(Quest::class);
        $quest = $repository->findOneBy(['name' => $name]);
        $existed = $quest !== null;
        $quest ??= new Quest();

        $quest->setName($name);
        $quest->setDescription($description);
        $quest->setCreatedBy($author);
        $quest->setPoints($points);
        $quest->setEstimatedDuration($estimated_duration);

        try {
            $quest->setAvailableFrom(new \DateTime($available_from));
            $quest->setAvailableTo($available_to !== null ? new \DateTime($available_to) : null);
        } catch (\Exception $e) {
            return ['error' => 'Unparseable date: ' . $e->getMessage()];
        }

        $warnings = [];
        $requiredCapabilities = [];
        // The one cross-step check worth making here: a probe records a dwell, and a dwell with
        // nothing on air is a participant standing still for thirty seconds for no reason. The
        // symptom is a gap in the trajectory rather than an error, so it has to be caught at
        // authoring time.
        $hasProbe = false;
        $broadcastDeclared = false;

        if ($existed) {
            foreach ($quest->getSteps()->toArray() as $old) {
                $quest->removeStep($old);
            }
            // The DELETEs have to reach the database before the replacements are inserted:
            // (quest_id, order) is unique, and Doctrine emits INSERTs first within one flush.
            $this->entityManager->flush();
        }

        foreach ($steps as $i => $data) {
            $type = QuestStepType::tryFrom($data['type'] ?? '');
            if ($type === null) {
                return ['error' => sprintf('Step %d: unknown type "%s".', $i, $data['type'] ?? '')];
            }

            if ($type === QuestStepType::CONNECT_TO_AP) {
                $warnings[] = sprintf(
                    'Step %d asks the phone to join an access point. None exists on this deployment '
                    . '(monitor-mode injection, no AP), so the run will abort at that step.',
                    $i,
                );
            }

            $config = $data['config'] ?? [];
            if ($type === QuestStepType::SCAN_QR && ($config['expected_value'] ?? '') === '') {
                $warnings[] = sprintf('Step %d is a scan with no expected_value: it matches any code.', $i);
            }

            if ($type === QuestStepType::BLE_ADVERTISE) {
                $requiredCapabilities[] = 'ble.advertise';
                $warnings[] = sprintf(
                    'Step %d broadcasts the lab identity frame. iOS honours it only in the '
                    . 'foreground, and the quest now requires the ble.advertise capability.',
                    $i,
                );
            }

            if ($type === QuestStepType::PROBE) {
                $requiredCapabilities[] = 'ble.advertise';
                $requiredCapabilities[] = 'camera.qr';
                $hasProbe = true;

                if (($config['targets'] ?? []) === []) {
                    $warnings[] = sprintf(
                        'Step %d is a probe with no targets: nothing can satisfy it.',
                        $i,
                    );
                }
                foreach ((array) ($config['targets'] ?? []) as $t) {
                    if (!is_array($t) || !in_array($t['kind'] ?? null, ['card', 'node'], true)) {
                        $warnings[] = sprintf(
                            'Step %d has a target with no kind (card|node). A dwell at a node sits '
                            . 'at zero distance from one link end and cannot be pooled with one on '
                            . 'open floor, so the analysis needs the tag.',
                            $i,
                        );
                        break;
                    }
                }
            }

            if ($type === QuestStepType::START) {
                $features = (array) ($config['features'] ?? []);
                $broadcastDeclared = ($features['broadcast'] ?? false) === true;
            }

            $step = new QuestStep();
            $step->setName($data['name'] ?? null);
            $step->setType($type);
            $step->setOrder($data['order'] ?? $i);
            $step->setConfig($config);
            $quest->addStep($step);
            $this->entityManager->persist($step);
        }

        if ($hasProbe && !$broadcastDeclared) {
            $warnings[] = 'This quest has probe steps but its start step does not declare '
                . 'features.broadcast. Every feature defaults to false, so the identity frame will '
                . 'never go on air and each dwell records a participant standing still while no '
                . 'receiver can hear them. Add {"features": {"broadcast": true}} to the start step.';
        }

        $quest->setRequiredCapabilities($requiredCapabilities);

        $this->entityManager->persist($quest);
        $this->entityManager->flush();

        return [
            'action' => $existed ? 'replaced' : 'created',
            'name' => $name,
            'steps' => count($steps),
            'warnings' => $warnings,
            'markers' => array_column($this->markers->markers(), 'value'),
        ];
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'lab_quest_delete', description: 'Delete a quest and its steps by name.')]
    public function questDelete(string $name): array
    {
        $quest = $this->entityManager->getRepository(Quest::class)->findOneBy(['name' => $name]);
        if ($quest === null) {
            return ['error' => sprintf('No quest named "%s".', $name)];
        }

        $this->entityManager->remove($quest);
        $this->entityManager->flush();

        return ['deleted' => $name];
    }

    /**
     * Every scan marker the current quests ask for.
     *
     * Derived, never stored: a marker exists because a step asks for it. That is what keeps the
     * printed sheet and the quests from drifting apart.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_markers',
        description: 'List every scan marker the current quests require, with the quests that use each and its render URL.',
    )]
    public function markerList(): array
    {
        return ['markers' => array_map(static function (array $m): array {
            $m['svg_url'] = '/api/lab/markers/' . rawurlencode($m['value']) . '.svg';
            $m['print_url'] = '/admin/lab/markers';

            return $m;
        }, $this->markers->markers())];
    }

    /**
     * Render a marker as SVG.
     *
     * Renders any string, including one no quest uses yet — authoring a quest and printing its
     * markers otherwise become a chicken-and-egg problem.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_marker_svg',
        description: 'Render a scan marker as an SVG for a given payload string. The payload must equal the step\'s expected_value exactly.',
    )]
    public function markerSvg(string $value): array
    {
        return [
            'value' => $value,
            'known_to_a_quest' => $this->markers->isKnown($value),
            'svg' => $this->markers->svg($value),
        ];
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'lab_bundle_read',
        description: 'The lab bundle exactly as GET /api/lab/config serves it to the handsets.',
    )]
    public function bundleRead(): array
    {
        return $this->labConfig->bundle();
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'lab_session_tally',
        description: 'Live ground-truth tally for one lab session: per-zone and room-level counts, plus E3 conflicts.',
    )]
    public function sessionTally(string $lab_session_id): array
    {
        return $this->groundTruth->aggregate($lab_session_id);
    }
}
