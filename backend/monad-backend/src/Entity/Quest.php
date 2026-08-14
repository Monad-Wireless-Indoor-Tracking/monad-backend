<?php

namespace App\Entity;

use App\Quest\RecurrencePolicy;
use App\Repository\QuestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestRepository::class)]
#[ORM\Table(name: 'quests')]
#[ORM\HasLifecycleCallbacks]
class Quest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Quest name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Quest name cannot be longer than {{ limit }} characters')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Quest description is required')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Available from date is required')]
    private ?\DateTimeInterface $availableFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $availableTo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Quest creator is required')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull(message: 'Points value is required')]
    #[Assert\PositiveOrZero(message: 'Points must be a positive number or zero')]
    private float $points = 0.0;

    /**
     * Capability tokens a device must satisfy to be offered this quest.
     *
     * A quest needing a sensor the handset lacks is not a degraded run — it is a run that looks
     * complete and is missing the measurement. So the catalogue filters on this rather than letting
     * the app discover the gap halfway through.
     *
     * Free-form strings on purpose: adding a sensor is a token on the device plus a token here, not
     * a schema migration.
     *
     * @var string[]
     */
    #[ORM\Column(name: 'required_capabilities', type: Types::JSON)]
    private array $requiredCapabilities = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Estimated duration must be a positive number')]
    private ?int $estimatedDuration = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $featuredImage = null;

    #[ORM\OneToMany(targetEntity: QuestStep::class, mappedBy: 'quest', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    private Collection $steps;

    /**
     * How often one participant may repeat this quest (IP-128).
     *
     * NULL means UNLIMITED, which is the behaviour every quest has today —
     * `Version20251202185420` dropped the unique enrollment index and
     * `startQuest()` performs no enrollment lookup, so nothing has ever prevented
     * a replay. A cooldown is therefore opt-in per quest, authored in `/admin`,
     * and there is deliberately no system-wide default: a measurement quest wants
     * none (a pre-registered session runs the same nodes repeatedly in one
     * afternoon), while an evergreen "collect the fleet" quest wants one.
     *
     * Shape: `{"scope": "per_device"|"per_quest", "cooldown_seconds": int}`.
     * Read through {@see \App\Quest\RecurrencePolicy::fromArray()}, which returns
     * null for anything malformed rather than throwing — a bad policy must
     * degrade to "no extra gate", never take the quest catalogue down.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recurrence = null;

    /**
     * Which physical nodes offer this quest (IP-128).
     *
     * EMPTY MEANS EVERY NODE, not "none" — that is what every quest written
     * before IP-128 means, and treating empty as "nowhere" would silently
     * unpublish the entire existing catalogue on migration.
     *
     * @var Collection<int, Device>
     */
    #[ORM\ManyToMany(targetEntity: Device::class)]
    #[ORM\JoinTable(name: 'quest_devices')]
    #[ORM\JoinColumn(name: 'quest_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'device_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['slug' => 'ASC'])]
    private Collection $armedDevices;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->steps = new ArrayCollection();
        $this->armedDevices = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAvailableFrom(): ?\DateTimeInterface
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(\DateTimeInterface $availableFrom): static
    {
        $this->availableFrom = $availableFrom;

        return $this;
    }

    public function getAvailableTo(): ?\DateTimeInterface
    {
        return $this->availableTo;
    }

    public function setAvailableTo(?\DateTimeInterface $availableTo): static
    {
        $this->availableTo = $availableTo;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getPoints(): float
    {
        return $this->points;
    }

    public function setPoints(float $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): static
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    /**
     * @return Collection<int, QuestStep>
     */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    public function addStep(QuestStep $step): static
    {
        if (!$this->steps->contains($step)) {
            $this->steps->add($step);
            $step->setQuest($this);
        }

        return $this;
    }

    public function removeStep(QuestStep $step): static
    {
        if ($this->steps->removeElement($step)) {
            // set the owning side to null (unless already changed)
            if ($step->getQuest() === $this) {
                $step->setQuest(null);
            }
        }

        return $this;
    }

    /** @return string[] */
    public function getRequiredCapabilities(): array
    {
        return $this->requiredCapabilities;
    }

    /** @param string[] $capabilities */
    public function setRequiredCapabilities(array $capabilities): static
    {
        $this->requiredCapabilities = array_values(array_unique($capabilities));

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRecurrence(): ?array
    {
        return $this->recurrence;
    }

    /**
     * @param array<string, mixed>|null $recurrence
     */
    public function setRecurrence(?array $recurrence): static
    {
        // An empty array is not a policy — normalise it to null so "cleared in
        // the admin form" and "never set" mean the same thing downstream.
        $this->recurrence = ($recurrence === null || $recurrence === []) ? null : $recurrence;

        return $this;
    }

    /** Typed view of {@see getRecurrence()}; null when unset or malformed. */
    public function getRecurrencePolicy(): ?RecurrencePolicy
    {
        return RecurrencePolicy::fromArray($this->recurrence);
    }

    /**
     * @return Collection<int, Device>
     */
    public function getArmedDevices(): Collection
    {
        return $this->armedDevices;
    }

    public function addArmedDevice(Device $device): static
    {
        if (!$this->armedDevices->contains($device)) {
            $this->armedDevices->add($device);
        }

        return $this;
    }

    public function removeArmedDevice(Device $device): static
    {
        $this->armedDevices->removeElement($device);

        return $this;
    }

    /** True when this quest is offered at $device (empty arming = everywhere). */
    public function isArmedAt(Device $device): bool
    {
        return $this->armedDevices->isEmpty() || $this->armedDevices->contains($device);
    }

    /** @param string[] $deviceCapabilities */
    public function isSupportedBy(array $deviceCapabilities): bool
    {
        return [] === array_diff($this->requiredCapabilities, $deviceCapabilities);
    }
}
