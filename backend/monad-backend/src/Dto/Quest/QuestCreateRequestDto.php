<?php

namespace App\Dto\Quest;

use Symfony\Component\Validator\Constraints as Assert;

class QuestCreateRequestDto
{
    #[Assert\NotBlank(message: 'Quest name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Quest name cannot be longer than {{ limit }} characters')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Quest description is required')]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Available from date is required')]
    #[Assert\Type(type: 'string', message: 'Available from must be a string')]
    public ?string $available_from = null;

    #[Assert\Type(type: 'string', message: 'Available to must be a string')]
    public ?string $available_to = null;

    #[Assert\NotNull(message: 'Points value is required')]
    #[Assert\PositiveOrZero(message: 'Points must be a positive number or zero')]
    public ?float $points = 0.0;

    #[Assert\Positive(message: 'Estimated duration must be a positive number')]
    public ?int $estimated_duration = null;

    #[Assert\Length(max: 512, maxMessage: 'Featured image URL cannot be longer than {{ limit }} characters')]
    public ?string $featured_image = null;

    #[Assert\NotNull(message: 'Steps array is required')]
    #[Assert\Type(type: 'array', message: 'Steps must be an array')]
    #[Assert\Count(min: 1, minMessage: 'At least one step is required')]
    #[Assert\Valid]
    public array $steps = [];
}
