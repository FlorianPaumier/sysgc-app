<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Model\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Invitation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 20, enumType: UserRole::class)]
    private UserRole $role;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'invitation_project_assignment')]
    private Collection $assignedProjects;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
        string $email,
        UserRole $role,
        #[\SensitiveParameter]
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        if ($role === UserRole::OWNER) {
            throw new \DomainException('Le rôle propriétaire ne peut pas être attribué par invitation.');
        }

        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            throw new \DomainException('Une adresse courriel est requise.');
        }

        $this->id = Uuid::v7();
        $this->email = $normalizedEmail;
        $this->role = $role;
        $this->expiresAt = ($createdAt ?? new \DateTimeImmutable())->modify('+7 days');
        $this->assignedProjects = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getOrganization(): Organization { return $this->organization; }
    /** @return non-empty-string */
    public function getEmail(): string { return $this->email; }
    public function getRole(): UserRole { return $this->role; }
    public function getRoleLabel(): string { return $this->role->label(); }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function isAccepted(): bool { return $this->acceptedAt !== null; }
    public function isRevoked(): bool { return $this->revokedAt !== null; }
    public function isExpired(\DateTimeImmutable $now): bool { return $this->expiresAt <= $now; }

    /** @return Collection<int, Project> */
    public function getAssignedProjects(): Collection { return $this->assignedProjects; }

    public function assignProject(Project $project): static
    {
        if ($this->role !== UserRole::FOREMAN) {
            throw new \DomainException('Les assignations de chantier sont réservées aux contremaîtres.');
        }
        if (! $this->organization->getId()->equals($project->getOrganization()->getId())) {
            throw new \DomainException('Le chantier doit appartenir à la même organisation.');
        }
        if (! $this->assignedProjects->contains($project)) {
            $this->assignedProjects->add($project);
        }
        return $this;
    }

    public function canBeAccepted(#[\SensitiveParameter] string $tokenHash, \DateTimeImmutable $now): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired($now)
            && hash_equals($this->tokenHash, $tokenHash);
    }

    public function accept(?\DateTimeImmutable $at = null): void
    {
        if ($this->isAccepted() || $this->isRevoked()) {
            throw new \DomainException('Cette invitation ne peut plus être utilisée.');
        }
        $this->acceptedAt = $at ?? new \DateTimeImmutable();
    }

    public function revoke(?\DateTimeImmutable $at = null): void
    {
        if ($this->isAccepted()) {
            throw new \DomainException('Une invitation acceptée ne peut pas être révoquée.');
        }
        $this->revokedAt = $at ?? new \DateTimeImmutable();
    }
}
