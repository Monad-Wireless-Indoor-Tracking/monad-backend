<?php

namespace App\Entity;

use App\Enum\QuestEnrollmentStatus;
use App\Repository\QuestEnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestEnrollmentRepository::class)]
#[ORM\Table(name: 'quest_enrollments')]
#[ORM\Index(name: 'enrollment_status_idx', columns: ['status'])]
#[ORM\Index(name: 'user_quest_idx', columns: ['user_id', 'quest_id'])]
#[ORM\HasLifecycleCallbacks]
class QuestEnrollment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[Assert\NotNull(message: 'User is required')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Quest::class)]
    #[ORM\JoinColumn(name: 'quest_id', nullable: false)]
    #[Assert\NotNull(message: 'Quest is required')]
    private ?Quest $quest = null;

    #[ORM\Column(type: 'string', enumType: QuestEnrollmentStatus::class)]
    #[Assert\NotNull(message: 'Status is required')]
    private QuestEnrollmentStatus $status = QuestEnrollmentStatus::IN_PROGRESS;

    #[ORM\Column(name: 'data_path', type: 'string', length: 512, nullable: true)]
    #[Assert\Length(max: 512, maxMessage: 'Data path cannot be longer than {{ limit }} characters')]
    private ?string $dataPath = null;

    /**
     * Which physical node produced this run (IP-128).
     *
     * MEASUREMENT PROVENANCE once written, not bookkeeping — it is the record of
     * where a measurement came from, so the admin renders it read-only and the
     * device row can be deactivated but never deleted (ON DELETE RESTRICT).
     */
    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(name: 'device_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Device $device = null;

    /**
     * When the SERVER received the completion (IP-128).
     *
     * Distinct from `$completedAt`, which `QuestController::completeQuest()` sets
     * from the request body. A cooldown measured against a client-supplied
     * timestamp is not a cooldown — a backdated finish clears it instantly — so
     * the recurrence gate reads only this column.
     */
    #[ORM\Column(name: 'completion_received_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completionReceivedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: QuestStepCompletion::class, mappedBy: 'enrollment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $stepCompletions;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = QuestEnrollmentStatus::IN_PROGRESS;
        $this->stepCompletions = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getQuest(): ?Quest
    {
        return $this->quest;
    }

    public function setQuest(?Quest $quest): static
    {
        $this->quest = $quest;

        return $this;
    }

    public function getStatus(): QuestEnrollmentStatus
    {
        return $this->status;
    }

    public function setStatus(QuestEnrollmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDataPath(): ?string
    {
        return $this->dataPath;
    }

    public function setDataPath(?string $dataPath): static
    {
        $this->dataPath = $dataPath;

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

    /**
     * @return Collection<int, QuestStepCompletion>
     */
    public function getStepCompletions(): Collection
    {
        return $this->stepCompletions;
    }

    public function addStepCompletion(QuestStepCompletion $stepCompletion): static
    {
        if (!$this->stepCompletions->contains($stepCompletion)) {
            $this->stepCompletions->add($stepCompletion);
            $stepCompletion->setEnrollment($this);
        }

        return $this;
    }

    public function removeStepCompletion(QuestStepCompletion $stepCompletion): static
    {
        if ($this->stepCompletions->removeElement($stepCompletion)) {
            // set the owning side to null (unless already changed)
            if ($stepCompletion->getEnrollment() === $this) {
                $stepCompletion->setEnrollment(null);
            }
        }

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): static
    {
        $this->device = $device;

        return $this;
    }

    public function getCompletionReceivedAt(): ?\DateTimeImmutable
    {
        return $this->completionReceivedAt;
    }

    /** Stamped by the server on receipt; never taken from a request body. */
    public function markCompletionReceived(?\DateTimeImmutable $at = null): static
    {
        $this->completionReceivedAt = $at ?? new \DateTimeImmutable();

        return $this;
    }
}
