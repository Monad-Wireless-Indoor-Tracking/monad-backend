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
 * step's `expected_value` (QrCodeStep.kt, case-insensitive), so that string is the entire
 * contract. Anything that keeps a second list of markers can disagree with the quests, and the
 * failure mode is a code that scans fine and silently never advances the step.
 *
 * So markers are a projection of the quest set. Add a scan step, the marker appears; change the
 * expected value, the printable sheet changes with it.
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
     * @return list<array{value: string, label: string, quests: list<string>}>
     */
    public function markers(): array
    {
        $steps = $this->entityManager->createQuery(
            'SELECT s, q FROM App\Entity\QuestStep s JOIN s.quest q WHERE s.type = :type ORDER BY q.name, s.order'
        )->setParameter('type', QuestStepType::SCAN_QR)->getResult();

        $markers = [];
        foreach ($steps as $step) {
            $value = (string) ($step->getConfig()['expected_value'] ?? '');
            if ($value === '') {
                // A scan step with no expected value matches any code at all. That is a real
                // authoring mistake, but it is not a marker, so it cannot appear on a sheet.
                continue;
            }

            $markers[$value] ??= ['value' => $value, 'label' => $step->getName() ?? $value, 'quests' => []];
            $questName = $step->getQuest()?->getName();
            if ($questName !== null && !in_array($questName, $markers[$value]['quests'], true)) {
                $markers[$value]['quests'][] = $questName;
            }
        }

        return array_values($markers);
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
