<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\SubscriptionTier;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
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

    #[ORM\Embedded(class: Address::class)]
    private Address $address;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'organization', orphanRemoval: true)]
    private Collection $users;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'organization', orphanRemoval: true)]
    private Collection $projects;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->users = new ArrayCollection;
        $this->projects = new ArrayCollection;
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
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getOrganization() === $this) {
                $project->setOrganization(null);
            }
        }

        return $this;
    }
}
