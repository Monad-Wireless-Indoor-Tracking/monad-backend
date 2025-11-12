<?php

namespace App\Dto\Quest;

use App\Entity\Quest;

class QuestDetailResponseDto
{
    /**
     * @param QuestStepDto[] $steps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly float $points,
        public readonly ?int $estimatedDuration,
        public readonly string $createdAt,
        public readonly array $steps
    ) {
    }

    public static function fromEntity(Quest $quest): self
    {
        $steps = [];
        foreach ($quest->getSteps() as $step) {
            $steps[] = QuestStepDto::fromEntity($step);
        }

        return new self(
            id: $quest->getId()->toRfc4122(),
            name: $quest->getName(),
            description: $quest->getDescription(),
            points: $quest->getPoints(),
            estimatedDuration: $quest->getEstimatedDuration(),
            createdAt: $quest->getCreatedAt()->format('Y-m-d H:i:s'),
            steps: $steps
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'points' => $this->points,
            'estimatedDuration' => $this->estimatedDuration,
            'createdAt' => $this->createdAt,
            'steps' => array_map(fn(QuestStepDto $step) => $step->toArray(), $this->steps)
        ];
    }
}
