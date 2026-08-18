<?php

namespace App\Tests\Validator;

use App\Dto\Quest\QuestCreateStepDto;
use App\Enum\QuestStepType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The step-config validator dispatches on QuestStepType with a `match` and no default arm.
 *
 * That shape is deliberate — adding an enum case without a validation arm should be loud — but
 * before these tests it was loud in production: `sensor_capture` was added to the enum, left out
 * of the match, and every request reaching it would have been a 500 (`\UnhandledMatchError`). The
 * exhaustiveness test below turns that failure into a red test at the moment the case is added.
 */
class ValidStepConfigValidatorTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function dto(string $type, array $config): QuestCreateStepDto
    {
        $dto = new QuestCreateStepDto();
        $dto->name = 'Step under test';
        $dto->type = $type;
        $dto->order = 0;
        $dto->config = $config;

        return $dto;
    }

    /** @return list<string> */
    private function violationMessages(string $type, array $config): array
    {
        $violations = $this->validator->validate($this->dto($type, $config));

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    public function testEveryEnumCaseHasAValidatorArm(): void
    {
        foreach (QuestStepType::cases() as $case) {
            // An empty config may produce violations; it must never throw. A missing match arm
            // throws \UnhandledMatchError, which is exactly the regression this guards against.
            $this->validator->validate($this->dto($case->value, []));
        }

        $this->addToAssertionCount(1);
    }

    public function testEveryEnumCaseIsCreatable(): void
    {
        foreach (QuestStepType::cases() as $case) {
            self::assertContains(
                $case->value,
                QuestCreateStepDto::VALID_STEP_TYPES,
                sprintf('"%s" exists in the enum but the create DTO rejects it.', $case->value),
            );
        }
    }

    public function testSensorCaptureRequiresModule(): void
    {
        self::assertNotEmpty($this->violationMessages('sensor_capture', []));
        self::assertSame([], $this->violationMessages('sensor_capture', ['module' => 'room-scan']));
    }

    public function testBleAdvertiseRequiresDuration(): void
    {
        $messages = $this->violationMessages('ble_advertise', []);
        self::assertNotEmpty($messages);
        self::assertStringContainsString('duration_seconds', $messages[0]);
    }

    public function testBleAdvertiseAcceptsAMinimalConfig(): void
    {
        self::assertSame([], $this->violationMessages('ble_advertise', ['duration_seconds' => 120]));
    }

    public function testBleAdvertiseAcceptsAFullConfig(): void
    {
        self::assertSame([], $this->violationMessages('ble_advertise', [
            'duration_seconds' => 300,
            'adv_interval_ms' => 250,
            'tx_power' => 'medium',
        ]));
    }

    public function testBleAdvertiseRejectsAnIntervalOutsideTheBleBounds(): void
    {
        foreach ([99, 10241] as $interval) {
            $messages = $this->violationMessages('ble_advertise', [
                'duration_seconds' => 60,
                'adv_interval_ms' => $interval,
            ]);
            self::assertNotEmpty($messages, sprintf('interval %d must be rejected', $interval));
        }
    }

    public function testBleAdvertiseRejectsAnUnknownTxPower(): void
    {
        $messages = $this->violationMessages('ble_advertise', [
            'duration_seconds' => 60,
            'tx_power' => 'maximum',
        ]);
        self::assertNotEmpty($messages);
    }
}
