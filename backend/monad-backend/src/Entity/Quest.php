<?php

namespace App\Entity;

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

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->steps = new ArrayCollection();
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

    /** @param string[] $deviceCapabilities */
    public function isSupportedBy(array $deviceCapabilities): bool
    {
        return [] === array_diff($this->requiredCapabilities, $deviceCapabilities);
    }
}
