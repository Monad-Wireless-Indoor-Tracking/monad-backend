<?php

namespace App\Dto\Quest;

use Symfony\Component\Validator\Constraints as Assert;

class QuestCompleteSkipRecordDto
{
    #[Assert\NotBlank(message: 'Skip message is required')]
    #[Assert\Type(type: 'string', message: 'Message must be a string')]
    public ?string $message = null;

    #[Assert\Type(type: 'string', message: 'Error code must be a string')]
    #[Assert\Length(max: 100, maxMessage: 'Error code cannot be longer than {{ limit }} characters')]
    public ?string $error_code = null;

    #[Assert\Type(type: 'array', message: 'Metadata must be an object')]
    public array $metadata = [];
}
