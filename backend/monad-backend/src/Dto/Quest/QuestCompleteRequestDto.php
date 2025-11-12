<?php

namespace App\Dto\Quest;

use Symfony\Component\Validator\Constraints as Assert;

class QuestCompleteRequestDto
{
    #[Assert\NotBlank(message: 'Enrollment ID is required')]
    #[Assert\Uuid(message: 'Enrollment ID must be a valid UUID')]
    public ?string $enrollment_id = null;

    #[Assert\NotNull(message: 'Completed at timestamp is required')]
    #[Assert\Type(type: 'string', message: 'Completed at must be a string')]
    public ?string $completed_at = null;

    #[Assert\NotNull(message: 'Steps array is required')]
    #[Assert\Type(type: 'array', message: 'Steps must be an array')]
    #[Assert\Count(min: 1, minMessage: 'At least one step is required')]
    #[Assert\Valid]
    public array $steps = [];

    #[Assert\Valid]
    public ?QuestCompleteDataFileDto $data_file = null;
}
