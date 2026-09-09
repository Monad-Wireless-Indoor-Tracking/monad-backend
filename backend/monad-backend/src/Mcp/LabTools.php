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
        description: <<<'TXT'
            List all quests with their status, window, points, duration and step count.

            `status` is the field to read: `live` (a participant can run it now), `scheduled`
            (opens later) or `hidden` (its window has closed). It is computed against the clock,
            so a listing never needs two timestamps compared by hand to answer "is this on?".

            Change any of it with lab_quest_update, which never touches the steps.
            TXT,
    )]
    public function questList(): array
    {
        $quests = $this->entityManager->getRepository(Quest::class)->findBy([], ['availableFrom' => 'DESC']);

        $rows = array_map(fn (Quest $q): array => [
            'name' => $q->getName(),
            // First, because it is the question. Two timestamps are the evidence for it,
            // and a reader should not have to do the comparison to learn whether anybody
            // can run the thing right now.
            'status' => self::describeWindow($q),
            // IP-145. Listed because its absence is what made a broken re-scope expensive to
            // find: an operator quest and a public one looked identical here, so the only way
            // to check the gate was to read the whole quest back or hit the public API.
            'audience' => $q->getAudience(),
            'points' => $q->getPoints(),
            'available_from' => $q->getAvailableFrom()?->format(\DateTimeInterface::ATOM),
            'available_to' => $q->getAvailableTo()?->format(\DateTimeInterface::ATOM),
            'estimated_duration' => $q->getEstimatedDuration(),
            'steps' => $q->getSteps()->count(),
            'created_by' => $q->getCreatedBy()?->getEmail(),
        ], $quests);

        $live = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'live'));

        return [
            'quests' => $rows,
            // The summary an operator actually opens this tool for.
            'summary' => [
                'total' => count($rows),
                'live' => count($live),
                'live_names' => array_column($live, 'name'),
            ],
        ];
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
            // IP-145. Returned so a read really does round-trip into a write: without these
            // two, "read, edit one field, write back" would silently reset an operator quest
            // to public and drop its route pool.
            'audience' => $quest->getAudience(),
            'route_policy' => $quest->getRoutePolicy(),
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

            `audience` is public (default) or operator (IP-145). An operator quest is filtered OUT
            of the participant listing and answers 404 on start for anyone without the role — never
            403 and never a QuestAvailability reason, because a reason discloses that a withheld
            quest exists. `lab quest-build --track` sets it, since a tracked take is an operator
            take by construction.

            `route_pool` is a LIST of routes, each a list of target keys:
            [["MONAD-FP-15","monad02",...], ["monad07",...]]. Omit it and the quest serves its
            declared step order, which is the default and what every quest did before IP-145.
            Supply it and each enrollment draws one route, recorded on the enrollment.

            A pool is PRE-GENERATED by `monad-knowledge lab quest-build --pool N` and never
            computed here: the rule that keeps a walk from crossing a wall reads PostGIS, which
            this backend cannot see, and a second implementation of it would diverge silently
            because an unwalkable leg still renders as a short line on a plan.

            Both fields are omitted-means-unchanged, so a generator that predates them is unaffected.
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
        ?string $audience = null,
        ?array $route_pool = null,
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

        // IP-145. Omitted means unchanged on a replace and `public` on a create, so a
        // generator that has never heard of these fields keeps working unchanged.
        if ($audience !== null) {
            $quest->setAudience($audience);
        }
        // A LIST of routes, not a policy object, and that is forced rather than chosen: this
        // parameter's JSON schema comes from the PHP type hint, and `array` renders as a JSON
        // array. An object would be rejected by the client before it ever arrived.
        //
        // It is also the better shape. The tool cannot be handed a malformed policy, because
        // it does not accept one: the mode is implied by presence, and the wrapper is built
        // here from a list it has already validated.
        if ($route_pool !== null) {
            $routes = array_values(array_filter(
                $route_pool,
                static fn ($r): bool => is_array($r) && $r !== [],
            ));
            $quest->setRoutePolicy($routes === [] ? null : ['mode' => 'pool', 'routes' => $routes]);
        }

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
     * Change anything on the quest **row**, and nothing on its steps.
     *
     * The tool for a quest somebody has already run. `quest_enrollments.quest_id` and
     * `quest_step_completions.step_id` both carry no `ON DELETE` clause, so Postgres
     * refuses to drop a quest that holds run records — the database protecting
     * measurement provenance, and exactly right. The same guard means
     * `lab_quest_write` cannot be used to re-time one either: it replaces the step
     * rows, and those rows are what the completions point at.
     *
     * So every quest-row field lives here, in **one** tool rather than one tool per
     * field. `lab_quest_retire`, `lab_quest_schedule` and `lab_quest_points` would
     * have been three write paths to one row, and the second one written would
     * eventually disagree with the first about what "not offered" means.
     *
     * Only fields that are passed are changed. Omitting one leaves it alone, which is
     * what makes "hide this" a single argument rather than a read-modify-write.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'lab_quest_update',
        description: <<<'TXT'
            Change a quest's window, points, description or duration WITHOUT touching its steps.

            This is the tool for a quest somebody has already run. lab_quest_delete refuses those
            — the database will not drop a quest holding run records — and lab_quest_write cannot
            re-time one either, because it replaces the step rows the completions point at.

            Only the fields you pass are changed. Everything else is left alone.

            To HIDE a quest:            available_to = "now"
            To re-open one:             available_to = "never"   (clears the end date)
            To schedule a close:        available_to = "2027-06-30T23:59:00+00:00"
            To open it later:           available_from = "2026-09-01T06:00:00+00:00"
            To arm or disarm points:    points = 120   /   points = 0
            To scope it to operators:   audience = "operator"   (or "public" to un-scope)

            Dates are anything PHP's DateTime parses: "now", "+2 weeks", an ISO timestamp. The
            literal "never" is the one special value and it clears available_to.

            A hidden quest keeps its enrolments, its step completions and its history. It simply
            stops being offered, which is what hiding means when the data has to survive.
            TXT,
    )]
    public function questUpdate(
        string $name,
        ?string $available_from = null,
        ?string $available_to = null,
        ?float $points = null,
        ?string $description = null,
        ?int $estimated_duration = null,
        ?string $audience = null,
    ): array {
        $quest = $this->entityManager->getRepository(Quest::class)->findOneBy(['name' => $name]);
        if ($quest === null) {
            return ['error' => sprintf('No quest named "%s".', $name)];
        }

        $changed = [];

        if ($available_from !== null) {
            try {
                $opensAt = new \DateTime($available_from);
            } catch (\Exception $e) {
                return ['error' => 'Unparseable available_from: ' . $e->getMessage()];
            }
            $quest->setAvailableFrom($opensAt);
            $changed['available_from'] = $opensAt->format(\DateTimeInterface::ATOM);
        }

        if ($available_to !== null) {
            // "never" clears the end date. Spelled as a word rather than as an empty
            // string, because an empty string is what a mis-wired caller sends by
            // accident and re-opening a retired quest should never be accidental.
            if (strtolower(trim($available_to)) === 'never') {
                $quest->setAvailableTo(null);
                $changed['available_to'] = null;
            } else {
                try {
                    $closesAt = new \DateTime($available_to);
                } catch (\Exception $e) {
                    return ['error' => 'Unparseable available_to: ' . $e->getMessage()];
                }
                $quest->setAvailableTo($closesAt);
                $changed['available_to'] = $closesAt->format(\DateTimeInterface::ATOM);
            }
        }

        if ($points !== null) {
            if ($points < 0) {
                return ['error' => 'points must be zero or positive.'];
            }
            $quest->setPoints($points);
            $changed['points'] = $points;
        }

        if ($description !== null) {
            if (trim($description) === '') {
                return ['error' => 'description cannot be blank.'];
            }
            $quest->setDescription($description);
            $changed['description'] = $description;
        }

        if ($estimated_duration !== null) {
            if ($estimated_duration <= 0) {
                return ['error' => 'estimated_duration must be a positive number of minutes.'];
            }
            $quest->setEstimatedDuration($estimated_duration);
            $changed['estimated_duration'] = $estimated_duration;
        }

        // IP-145. The one field you actually want to change on a quest somebody has already
        // run: `lab_quest_write` replaces the step rows their completions point at, and the
        // FK on quest_step_completions.step_id has no ON DELETE clause, so such a write is
        // refused by the database rather than silently destructive. Re-scoping has to happen
        // here instead.
        //
        // Recorded in `$changed` like every other field. Without that the caller is told
        // "updated" with the audience absent from the list, which reads as "already set"
        // rather than "not applied" — and that is exactly how this shipped once already.
        if ($audience !== null) {
            $quest->setAudience($audience);
            $changed['audience'] = $quest->getAudience();
        }

        if ($changed === []) {
            return ['error' => 'Nothing to change. Pass at least one field.'];
        }

        $this->entityManager->flush();

        return [
            'updated' => $name,
            'changed' => $changed,
            'status' => self::describeWindow($quest),
            'note' => 'Steps, enrolments and step completions untouched.',
        ];
    }

    /**
     * Where a quest stands relative to now, as one word.
     *
     * `available_from` / `available_to` are two timestamps a reader has to compare
     * against the clock in their head before they know whether anybody can run the
     * thing. That comparison is the question, so the answer travels with the data.
     */
    private static function describeWindow(Quest $quest): string
    {
        $now = new \DateTimeImmutable();
        $from = $quest->getAvailableFrom();
        $to = $quest->getAvailableTo();

        if ($from !== null && $from > $now) {
            return 'scheduled';
        }
        if ($to !== null && $to < $now) {
            return 'hidden';
        }

        return 'live';
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
