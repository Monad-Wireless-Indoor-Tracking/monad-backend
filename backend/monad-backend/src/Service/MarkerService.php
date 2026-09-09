<?php

namespace App\Service;

use App\Entity\Quest;
use App\Enum\QuestStepType;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;

/**
 * Scan markers, rendered on demand and derived from the quests that reference them.
 *
 * There is no marker table and no marker list to maintain. A marker exists because some quest
 * step asks a participant to scan it: the app matches a scan by comparing the scanned text to the
 * step's target (QrCodeStep.kt / ProbeStep.kt, case-insensitive), so that string is the entire
 * contract. Anything that keeps a second list of markers can disagree with the quests, and the
 * failure mode is a code that scans fine and silently never advances the step.
 *
 * So markers are a projection of the quest set. Add a scan step, the marker appears; change the
 * target, the printable sheet changes with it.
 *
 * Two step types reach a card and both are projected (IP-140): `scan_qr` names one
 * `expected_value`, and `probe` names a list of `targets`. Projecting only the first would leave a
 * probe's cards answering "unknown marker" on `/m/<code>` while working perfectly in the app.
 */
class MarkerService
{
    private const SIZE = 600;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Every distinct marker the current quests ask for, with the quests that ask.
     *
     * Quests are described rather than named (IP-129 §2.4): the portal's `/m/<code>` page tells
     * whoever scanned a card what it is for, and "EXP-C1 Day 1" alone says nothing to a stranger.
     * The id travels with the name so that page can link to the quest rather than re-resolve it
     * by a name that is not a key.
     *
     * @return list<array{
     *     value: string,
     *     label: string,
     *     quests: list<array{id: string, name: string, description: ?string, points: float, estimated_duration: ?int}>
     * }>
     */
    public function markers(): array
    {
        $steps = $this->entityManager->createQuery(
            'SELECT s, q FROM App\Entity\QuestStep s JOIN s.quest q WHERE s.type IN (:types) ORDER BY q.name, s.order'
        )->setParameter('types', [QuestStepType::SCAN_QR, QuestStepType::PROBE])->getResult();

        $markers = [];
        foreach ($steps as $step) {
            $quest = $step->getQuest();
            $questId = (string) $quest?->getId();

            foreach (self::scannedValues($step) as $value => $label) {
                $markers[$value] ??= ['value' => $value, 'label' => $label, 'quests' => []];

                if ($quest === null || $questId === '') {
                    continue;
                }

                // Dedup on the id, not the name: one quest can scan the same card twice (in and
                // out of a leg), and two quests are allowed to share a name.
                if (isset($markers[$value]['quests'][$questId])) {
                    continue;
                }

                $markers[$value]['quests'][$questId] = [
                    'id' => $questId,
                    'name' => (string) $quest->getName(),
                    'description' => $quest->getDescription(),
                    'points' => $quest->getPoints(),
                    'estimated_duration' => $quest->getEstimatedDuration(),
                ];
            }
        }

        // The quest map is keyed by id only to dedup; callers get a list.
        return array_values(array_map(static function (array $marker): array {
            $marker['quests'] = array_values($marker['quests']);

            return $marker;
        }, $markers));
    }

    /**
     * Every code one step can be satisfied by, keyed by the value, valued by its printable label.
     *
     * Two step types reach a printed card and they name it differently. `scan_qr` carries one
     * `expected_value` and the step's own name is the only label there is. A `probe` (IP-140)
     * carries a list of targets, each already resolved to a label and a room by the generator that
     * read them out of PostGIS — so a probe's label is the target's, not the step's, because one
     * step legitimately accepts twenty cards.
     *
     * Both must appear here. The marker index is what `/m/<code>` reads to tell someone holding a
     * card what it is for, and a card named only by a probe would answer "unknown marker" while
     * working perfectly in the app.
     *
     * Public because it is the whole of the projection rule and it is a pure function of one step.
     * `markers()` itself needs a database; this does not, so it is the part that can be pinned by
     * a unit test rather than by an integration one.
     *
     * @return array<string, string>
     */
    public static function scannedValues(\App\Entity\QuestStep $step): array
    {
        $config = $step->getConfig();
        $stepLabel = $step->getName() ?? '';

        if ($step->getType() === QuestStepType::PROBE) {
            $values = [];
            foreach ((array) ($config['targets'] ?? []) as $target) {
                if (!is_array($target)) {
                    continue;
                }
                $value = trim((string) ($target['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $label = trim((string) ($target['label'] ?? ''));
                $values[$value] = $label !== '' ? $label : ($stepLabel !== '' ? $stepLabel : $value);
            }

            return $values;
        }

        if ($step->getType() !== QuestStepType::SCAN_QR) {
            // Exactly two step types put a participant in front of a printed code. Everything else
            // projects nothing, even if it happens to carry an `expected_value` key — otherwise a
            // stray config field grows a sheet entry no card corresponds to. `markers()` restricts
            // the query to the same two types, so this guard only matters to a direct caller; it
            // is here because the rule belongs to the rule, not to the query that happens to obey.
            return [];
        }

        $value = (string) ($config['expected_value'] ?? '');
        if ($value === '') {
            // A scan step with no expected value matches any code at all. That is a real
            // authoring mistake, but it is not a marker, so it cannot appear on a sheet.
            return [];
        }

        return [$value => $stepLabel !== '' ? $stepLabel : $value];
    }

    /**
     * Error correction H, deliberately: these are taped to a doorframe and scanned in a hurry, at
     * an angle, sometimes with a thumb across a corner. The redundancy costs nothing on a payload
     * this short.
     */
    public function svg(string $value): string
    {
        return $this->render(new SvgWriter(), $value);
    }

    public function png(string $value): string
    {
        return $this->render(new PngWriter(), $value);
    }

    /** endroid/qr-code 6 replaced the static fluent builder with a named-argument constructor. */
    private function render(WriterInterface $writer, string $value): string
    {
        return (new Builder(
            writer: $writer,
            data: $value,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: self::SIZE,
            margin: 16,
        ))->build()->getString();
    }

    /** True when some quest actually asks for this value — the guard on the public render route. */
    public function isKnown(string $value): bool
    {
        foreach ($this->markers() as $marker) {
            if (strcasecmp($marker['value'], $value) === 0) {
                return true;
            }
        }

        return false;
    }
}
