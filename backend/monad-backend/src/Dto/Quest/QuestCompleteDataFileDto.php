<?php

namespace App\Dto\Quest;

use Symfony\Component\Validator\Constraints as Assert;

class QuestCompleteDataFileDto
{
    #[Assert\NotBlank(message: 'Filename is required')]
    #[Assert\Type(type: 'string', message: 'Filename must be a string')]
    #[Assert\Length(max: 255, maxMessage: 'Filename cannot be longer than {{ limit }} characters')]
    public ?string $filename = null;

    #[Assert\NotNull(message: 'File size is required')]
    #[Assert\Type(type: 'numeric', message: 'Size must be a number')]
    #[Assert\PositiveOrZero(message: 'Size must be a positive number or zero')]
    public ?int $size = null;

    #[Assert\NotBlank(message: 'Checksum is required')]
    #[Assert\Type(type: 'string', message: 'Checksum must be a string')]
    #[Assert\Length(max: 128, maxMessage: 'Checksum cannot be longer than {{ limit }} characters')]
    public ?string $checksum = null;
}
