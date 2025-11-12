<?php

namespace App\Dto\Quest;

use App\Entity\QuestStep;
use App\Entity\QuestStepCompletion;
use Symfony\Component\Uid\Uuid;

class QuestStartStepDto
{
    public function __construct(
        public readonly Uuid $stepId,
        public readonly Uuid $stepCompletionId,
        public readonly string $name,
        public readonly string $type,
        public readonly int $order,
        public readonly array $config
    ) {
    }

    public static function fromEntities(QuestStep $step, QuestStepCompletion $completion): self
    {
        return new self(
            stepId: $step->getId(),
            stepCompletionId: $completion->getId(),
            name: $step->getName(),
            type: $step->getType()->value,
            order: $step->getOrder(),
            config: $step->getConfig()
        );
    }

    public function toArray(): array
    {
        return [
            'step_id' => (string) $this->stepId,
            'step_completion_id' => (string) $this->stepCompletionId,
            'name' => $this->name,
            'type' => $this->type,
            'order' => $this->order,
            'config' => $this->config
        ];
    }
}
