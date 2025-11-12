<?php

namespace App\Entity;

use App\Enum\QuestStepCompletionStatus;
use App\Repository\QuestStepCompletionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestStepCompletionRepository::class)]
#[ORM\Table(name: 'quest_step_completions')]
#[ORM\UniqueConstraint(name: 'enrollment_step_idx', columns: ['enrollment_id', 'step_id'])]
#[ORM\HasLifecycleCallbacks]
class QuestStepCompletion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: QuestEnrollment::class, inversedBy: 'stepCompletions')]
    #[ORM\JoinColumn(name: 'enrollment_id', nullable: false)]
    #[Assert\NotNull(message: 'Enrollment is required')]
    private ?QuestEnrollment $enrollment = null;

    #[ORM\ManyToOne(targetEntity: QuestStep::class)]
    #[ORM\JoinColumn(name: 'step_id', nullable: false)]
    #[Assert\NotNull(message: 'Step is required')]
    private ?QuestStep $step = null;

    #[ORM\Column(type: 'string', enumType: QuestStepCompletionStatus::class)]
    #[Assert\NotNull(message: 'Status is required')]
    private QuestStepCompletionStatus $status = QuestStepCompletionStatus::IN_PROGRESS;

    #[ORM\Column(name: 'started_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'step_data', type: 'json')]
    private array $stepData = [];

    #[ORM\OneToMany(targetEntity: QuestStepSkipRecord::class, mappedBy: 'stepCompletion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $skipRecords;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = QuestStepCompletionStatus::IN_PROGRESS;
        $this->stepData = [];
        $this->skipRecords = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEnrollment(): ?QuestEnrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?QuestEnrollment $enrollment): static
    {
        $this->enrollment = $enrollment;

        return $this;
    }

    public function getStep(): ?QuestStep
    {
        return $this->step;
    }

    public function setStep(?QuestStep $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getStatus(): QuestStepCompletionStatus
    {
        return $this->status;
    }

    public function setStatus(QuestStepCompletionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;

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

    public function getStepData(): array
    {
        return $this->stepData;
    }

    public function setStepData(array $stepData): static
    {
        $this->stepData = $stepData;

        return $this;
    }

    /**
     * @return Collection<int, QuestStepSkipRecord>
     */
    public function getSkipRecords(): Collection
    {
        return $this->skipRecords;
    }

    public function addSkipRecord(QuestStepSkipRecord $skipRecord): static
    {
        if (!$this->skipRecords->contains($skipRecord)) {
            $this->skipRecords->add($skipRecord);
            $skipRecord->setStepCompletion($this);
        }

        return $this;
    }

    public function removeSkipRecord(QuestStepSkipRecord $skipRecord): static
    {
        if ($this->skipRecords->removeElement($skipRecord)) {
            // set the owning side to null (unless already changed)
            if ($skipRecord->getStepCompletion() === $this) {
                $skipRecord->setStepCompletion(null);
            }
        }

        return $this;
    }
}
