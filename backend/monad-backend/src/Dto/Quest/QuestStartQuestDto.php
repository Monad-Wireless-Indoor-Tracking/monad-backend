<?php

namespace App\Dto\Quest;

use App\Entity\Quest;
use Symfony\Component\Uid\Uuid;

class QuestStartQuestDto
{
    /**
     * @param QuestStartStepDto[] $steps
     */
    public function __construct(
        public readonly Uuid $id,
        public readonly string $name,
        public readonly string $description,
        public readonly array $steps
    ) {
    }

    /**
     * @param QuestStartStepDto[] $steps
     */
    public static function fromEntity(Quest $quest, array $steps): self
    {
        return new self(
            id: $quest->getId(),
            name: $quest->getName(),
            description: $quest->getDescription(),
            steps: $steps
        );
    }

    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'steps' => array_map(fn(QuestStartStepDto $step) => $step->toArray(), $this->steps)
        ];
    }
}
