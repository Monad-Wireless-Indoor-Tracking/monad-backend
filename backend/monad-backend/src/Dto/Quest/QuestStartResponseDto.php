<?php

namespace App\Dto\Quest;

use App\Entity\QuestEnrollment;
use Symfony\Component\Uid\Uuid;

class QuestStartResponseDto
{
    public function __construct(
        public readonly Uuid $enrollmentId,
        public readonly QuestStartQuestDto $quest,
        public readonly string $dataPath,
        public readonly \DateTimeImmutable $startedAt
    ) {
    }

    public static function fromEntity(QuestEnrollment $enrollment, QuestStartQuestDto $questDto): self
    {
        return new self(
            enrollmentId: $enrollment->getId(),
            quest: $questDto,
            dataPath: $enrollment->getDataPath(),
            startedAt: $enrollment->getCreatedAt()
        );
    }

    public function toArray(): array
    {
        return [
            'enrollment_id' => (string) $this->enrollmentId,
            'quest' => $this->quest->toArray(),
            'data_path' => $this->dataPath,
            'started_at' => $this->startedAt->format('Y-m-d\TH:i:s\Z')
        ];
    }
}
