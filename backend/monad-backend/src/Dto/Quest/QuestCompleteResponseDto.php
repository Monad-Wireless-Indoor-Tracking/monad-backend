<?php

namespace App\Dto\Quest;

class QuestCompleteResponseDto
{
    public bool $success;
    public string $enrollment_id;
    public float $points_earned;
    public string $completed_at;

    public function __construct(
        bool $success,
        string $enrollment_id,
        float $points_earned,
        string $completed_at
    ) {
        $this->success = $success;
        $this->enrollment_id = $enrollment_id;
        $this->points_earned = $points_earned;
        $this->completed_at = $completed_at;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'enrollment_id' => $this->enrollment_id,
            'points_earned' => $this->points_earned,
            'completed_at' => $this->completed_at,
        ];
    }
}
