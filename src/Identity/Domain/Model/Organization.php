<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Compliance\Domain\Model\CCQDeclaration;
use App\Identity\Domain\Enum\SubscriptionTier;
use App\Identity\Domain\Enum\SubscriptionStatus;
use App\Project\Domain\Model\Project;
use App\Shared\Domain\TimestampableTrait;
use App\Workforce\Domain\Model\Worker;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Organization
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $rbqLicense = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 20, nullable: true, enumType: SubscriptionTier::class)]
    private ?SubscriptionTier $subscriptionTier = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $subscriptionExpiresAt = null;

    /** @var array<string, bool> */
    #[ORM\Column(type: 'json')]
    private array $checklistDefaults = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletionAt = null;

    #[ORM\Embedded(class: Address::class)]
    #[Assert\Valid]
    private Address $address;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'organization', cascade: ['remove'], orphanRemoval: true)]
    private Collection $users;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'organization', cascade: ['remove'], orphanRemoval: true)]
    private Collection $projects;

    /**
     * @var Collection<int, CCQDeclaration>
     */
    #[ORM\OneToMany(targetEntity: CCQDeclaration::class, mappedBy: 'organization', cascade: ['remove'], orphanRemoval: true)]
    private Collection $cCQDeclarations;

    /**
     * @var Collection<int, Worker>
     */
    #[ORM\OneToMany(targetEntity: Worker::class, mappedBy: 'organization', cascade: ['remove'], orphanRemoval: true)]
    private Collection $workers;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->users = new ArrayCollection;
        $this->projects = new ArrayCollection;
        $this->cCQDeclarations = new ArrayCollection();
        $this->workers = new ArrayCollection();
    }

    public function getId(): Uuid
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

    public function getRbqLicense(): ?string
    {
        return $this->rbqLicense;
    }

    public function setRbqLicense(string $rbqLicense): static
    {
        $this->rbqLicense = $rbqLicense;

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getSubscriptionTier(): ?SubscriptionTier
    {
        return $this->subscriptionTier;
    }

    public function setSubscriptionTier(?SubscriptionTier $subscriptionTier): static
    {
        $this->subscriptionTier = $subscriptionTier;

        return $this;
    }

    public function getSubscriptionExpiresAt(): ?\DateTimeImmutable
    {
        return $this->subscriptionExpiresAt;
    }

    public function setSubscriptionExpiresAt(?\DateTimeImmutable $subscriptionExpiresAt): static
    {
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;

        return $this;
    }

    /** @return array<string, bool> */
    public function getChecklistDefaults(): array
    {
        /** @var array<string, bool> $defaults */
        $defaults = $this->checklistDefaults;

        return $defaults;
    }

    /** @param array<string, bool> $defaults */
    public function setChecklistDefaults(array $defaults): static
    {
        $this->checklistDefaults = $defaults;

        return $this;
    }

    public function getDeletionAt(): ?\DateTimeImmutable
    {
        return $this->deletionAt;
    }

    public function isDeletionScheduled(): bool
    {
        return $this->deletionAt !== null;
    }

    public function scheduleDeletion(?\DateTimeImmutable $scheduledAt = null): static
    {
        $this->deletionAt = ($scheduledAt ?? new \DateTimeImmutable())->modify('+30 days');

        return $this;
    }

    public function canRecoverDeletion(\DateTimeImmutable $now): bool
    {
        return $this->deletionAt !== null && $now < $this->deletionAt;
    }

    public function isDeletionDue(\DateTimeImmutable $now): bool
    {
        return $this->deletionAt !== null && $now > $this->deletionAt;
    }

    public function recoverDeletion(): static
    {
        $this->deletionAt = null;

        return $this;
    }

    public function subscriptionStatus(?\DateTimeImmutable $now = null, int $workerCount = 0, int $userCount = 0): SubscriptionStatus
    {
        if ($this->subscriptionTier === null) {
            return SubscriptionStatus::NoSubscription;
        }

        if ($this->hasWorkerOverflow($workerCount) || $this->hasUserOverflow($userCount)) {
            return SubscriptionStatus::EnterpriseRequired;
        }

        if ($this->subscriptionExpiresAt === null) {
            return SubscriptionStatus::Active;
        }

        $now ??= new \DateTimeImmutable();
        if ($now <= $this->subscriptionExpiresAt) {
            return SubscriptionStatus::Active;
        }

        return $now <= $this->subscriptionExpiresAt->modify('+7 days')
            ? SubscriptionStatus::Grace
            : SubscriptionStatus::ReadOnly;
    }

    public function isReadOnly(?\DateTimeImmutable $now = null): bool
    {
        return $this->isDeletionScheduled() || $this->subscriptionStatus($now) === SubscriptionStatus::ReadOnly;
    }

    public function isAccessDisabled(\DateTimeImmutable $now): bool
    {
        return $this->isDeletionScheduled() && ! $this->canRecoverDeletion($now);
    }

    public function hasWorkerOverflow(int $workerCount): bool
    {
        return $this->subscriptionTier === SubscriptionTier::Business && $workerCount > $this->subscriptionTier->workerLimit();
    }

    public function hasUserOverflow(int $userCount): bool
    {
        return $this->subscriptionTier === SubscriptionTier::Business && $userCount > $this->subscriptionTier->userLimit();
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): Organization
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (! $this->users->contains($user)) {
            $this->users->add($user);
            $user->setOrganization($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getOrganization() === $this) {
                $user->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (! $this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setOrganization($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        $this->projects->removeElement($project);

        return $this;
    }

    /**
     * @return Collection<int, CCQDeclaration>
     */
    public function getCCQDeclarations(): Collection
    {
        return $this->cCQDeclarations;
    }

    public function addCCQDeclaration(CCQDeclaration $cCQDeclaration): static
    {
        if (!$this->cCQDeclarations->contains($cCQDeclaration)) {
            $this->cCQDeclarations->add($cCQDeclaration);
            $cCQDeclaration->setOrganization($this);
        }

        return $this;
    }

    public function removeCCQDeclaration(CCQDeclaration $cCQDeclaration): static
    {
        if ($this->cCQDeclarations->removeElement($cCQDeclaration)) {
            // set the owning side to null (unless already changed)
            if ($cCQDeclaration->getOrganization() === $this) {
                $cCQDeclaration->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Worker>
     */
    public function getWorkers(): Collection
    {
        return $this->workers;
    }

    public function addWorker(Worker $worker): static
    {
        if (!$this->workers->contains($worker)) {
            $this->workers->add($worker);
            $worker->setOrganization($this);
        }

        return $this;
    }

    public function removeWorker(Worker $worker): static
    {
        if ($this->workers->removeElement($worker)) {
            // set the owning side to null (unless already changed)
            if ($worker->getOrganization() === $this) {
                $worker->setOrganization(null);
            }
        }

        return $this;
    }
}
