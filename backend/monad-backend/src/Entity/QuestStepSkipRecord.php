<?php

namespace App\Entity;

use App\Repository\QuestStepSkipRecordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestStepSkipRecordRepository::class)]
#[ORM\Table(name: 'quest_step_skip_records')]
#[ORM\Index(name: 'skip_record_completion_idx', columns: ['step_completion_id'])]
#[ORM\HasLifecycleCallbacks]
class QuestStepSkipRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: QuestStepCompletion::class, inversedBy: 'skipRecords')]
    #[ORM\JoinColumn(name: 'step_completion_id', nullable: false)]
    #[Assert\NotNull(message: 'Step completion is required')]
    private ?QuestStepCompletion $stepCompletion = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Message is required')]
    private ?string $message = null;

    #[ORM\Column(name: 'error_code', type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Error code cannot be longer than {{ limit }} characters')]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->metadata = [];
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStepCompletion(): ?QuestStepCompletion
    {
        return $this->stepCompletion;
    }

    public function setStepCompletion(?QuestStepCompletion $stepCompletion): static
    {
        $this->stepCompletion = $stepCompletion;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): static
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
