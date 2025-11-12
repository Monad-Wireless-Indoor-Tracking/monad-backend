<?php

namespace App\Dto\Quest;

use App\Entity\QuestStep;
use App\Enum\QuestStepType;

class QuestStepDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly int $order,
        public readonly array $config
    ) {
    }

    public static function fromEntity(QuestStep $step): self
    {
        return new self(
            id: $step->getId()->toRfc4122(),
            name: $step->getName(),
            type: $step->getType()->value,
            order: $step->getOrder(),
            config: $step->getConfig()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'order' => $this->order,
            'config' => $this->config
        ];
    }
}
