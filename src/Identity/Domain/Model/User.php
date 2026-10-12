<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\TimestampableTrait;
use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Model\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, \Symfony\Component\Security\Core\User\EquatableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var Collection<int, MagicLink>
     */
    #[ORM\OneToMany(targetEntity: MagicLink::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $magicLinks;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'assignedForemen')]
    #[ORM\JoinTable(name: 'project_foreman_assignment')]
    private Collection $assignedProjects;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $active = true;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->magicLinks = new ArrayCollection;
        $this->assignedProjects = new ArrayCollection;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param non-empty-string $email
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return \array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return \in_array($value, $this->roles, true);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(UserRole::OWNER) || $this->isLegacyUnassignedUser();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN) || $this->isOwner();
    }

    public function isForeman(): bool
    {
        return $this->hasRole(UserRole::FOREMAN);
    }

    public function getRoleLabel(): string
    {
        if ($this->isOwner()) {
            return UserRole::OWNER->label();
        }

        if ($this->isAdmin()) {
            return UserRole::ADMIN->label();
        }

        return UserRole::FOREMAN->label();
    }

    /** Existing role-less accounts remain operable during migration. */
    private function isLegacyUnassignedUser(): bool
    {
        return [] === \array_intersect(
            [UserRole::OWNER->value, UserRole::ADMIN->value, UserRole::FOREMAN->value],
            $this->roles,
        );
    }

    /**
     * @return Collection<int, MagicLink>
     */
    public function getMagicLinks(): Collection
    {
        return $this->magicLinks;
    }

    public function addMagicLink(MagicLink $magicLink): static
    {
        if (! $this->magicLinks->contains($magicLink)) {
            $this->magicLinks->add($magicLink);
            $magicLink->setOwner($this);
        }

        return $this;
    }

    public function removeMagicLink(MagicLink $magicLink): static
    {
        if ($this->magicLinks->removeElement($magicLink)) {
            // set the owning side to null (unless already changed)
            if ($magicLink->getOwner() === $this) {
                $magicLink->setOwner(null);
            }
        }

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->active && $user->active
            && $this->email === $user->email
            && $this->getRoles() === $user->getRoles()
            && $this->organization?->getId()->equals($user->organization?->getId()) === true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): static
    {
        $this->active = false;

        return $this;
    }

    public function activate(): static
    {
        $this->active = true;

        return $this;
    }

    public function transferOwnershipTo(self $member): void
    {
        if (!$this->isOwner() || !$member->isActive() || $this->organization === null || $member->getOrganization() !== $this->organization) {
            throw new \DomainException('Le transfert est réservé à un membre actif de la même organisation.');
        }

        $this->roles = array_values(array_diff($this->roles, [UserRole::OWNER->value]));
        if (!in_array(UserRole::ADMIN->value, $this->roles, true)) {
            $this->roles[] = UserRole::ADMIN->value;
        }
        $member->roles = array_values(array_diff($member->roles, [UserRole::ADMIN->value, UserRole::FOREMAN->value]));
        $member->roles[] = UserRole::OWNER->value;
    }

    /**
     * Human-readable display name for UI surfaces.
     */
    public function getDisplayName(): string
    {
        $at = strpos($this->email, '@');

        return $at !== false ? substr($this->email, 0, $at) : $this->email;
    }

    /** @return Collection<int, Project> */
    public function getAssignedProjects(): Collection
    {
        return $this->assignedProjects;
    }

    public function assignProject(Project $project): static
    {
        if (! $this->isForeman()) {
            throw new \DomainException('Seuls les contremaîtres peuvent être affectés à un chantier.');
        }

        if ($this->organization === null || ! $this->organization->getId()->equals($project->getOrganization()->getId())) {
            throw new \DomainException('Le chantier doit appartenir à la même organisation.');
        }

        if (! $this->assignedProjects->contains($project)) {
            $this->assignedProjects->add($project);
        }

        return $this;
    }

    public function unassignProject(Project $project): static
    {
        $this->assignedProjects->removeElement($project);

        return $this;
    }
}
