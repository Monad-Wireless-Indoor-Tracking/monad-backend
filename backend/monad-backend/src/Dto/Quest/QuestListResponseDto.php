<?php

namespace App\Dto\Quest;

use App\Entity\Quest;

class QuestListResponseDto
{
    private string $id;
    private string $name;
    private string $description;
    private float $points;
    private ?int $estimatedDuration;
    private int $numberOfSteps;

    public function __construct(Quest $quest)
    {
        $this->id = $quest->getId()->toRfc4122();
        $this->name = $quest->getName();
        $this->description = $quest->getDescription();
        $this->points = $quest->getPoints();
        $this->estimatedDuration = $quest->getEstimatedDuration();
        $this->numberOfSteps = $quest->getSteps()->count();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'points' => $this->points,
            'estimatedDuration' => $this->estimatedDuration,
            'numberOfSteps' => $this->numberOfSteps,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPoints(): float
    {
        return $this->points;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function getNumberOfSteps(): int
    {
        return $this->numberOfSteps;
    }
}
