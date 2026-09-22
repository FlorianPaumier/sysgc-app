<?php

declare(strict_types=1);

namespace App\Compliance\Domain\Model;

use App\Compliance\Domain\ValueObject\RbqLicenseResult;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Model\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Subcontractor
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rbqLicenseNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiryDate = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $isManuallyVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastVerifiedAt = null;

    #[ORM\Column(length: 32, options: ['default' => 'unverified'])]
    private string $verificationStatus = 'unverified';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastVerificationAttemptAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastVerificationError = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verifiedBusinessName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $manualVerificationNotes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $evidenceOriginalName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $evidencePath = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $verifiedBy = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'subcontractors')]
    private Collection $projects;

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getRbqLicenseNumber(): ?string
    {
        return $this->rbqLicenseNumber;
    }

    public function setRbqLicenseNumber(?string $rbqLicenseNumber): static
    {
        $this->rbqLicenseNumber = $rbqLicenseNumber;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeImmutable $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function isManuallyVerified(): bool
    {
        return $this->isManuallyVerified;
    }

    public function setManuallyVerified(bool $isManuallyVerified): static
    {
        $this->isManuallyVerified = $isManuallyVerified;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        $this->verificationStatus = $isVerified ? 'verified' : 'unverified';

        return $this;
    }

    public function getLastVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastVerifiedAt;
    }

    public function setLastVerifiedAt(?\DateTimeImmutable $lastVerifiedAt): static
    {
        $this->lastVerifiedAt = $lastVerifiedAt;

        return $this;
    }

    public function getVerificationStatus(): string
    {
        return $this->verificationStatus;
    }

    public function requiresManualReview(): bool
    {
        return 'manual_review_required' === $this->verificationStatus;
    }

    public function getLastVerificationAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastVerificationAttemptAt;
    }

    public function getLastVerificationError(): ?string
    {
        return $this->lastVerificationError;
    }

    public function getVerificationSource(): ?string
    {
        return $this->verificationSource;
    }

    public function getVerifiedBusinessName(): ?string
    {
        return $this->verifiedBusinessName;
    }

    public function getManualVerificationNotes(): ?string
    {
        return $this->manualVerificationNotes;
    }

    public function getEvidenceOriginalName(): ?string
    {
        return $this->evidenceOriginalName;
    }

    public function getEvidencePath(): ?string
    {
        return $this->evidencePath;
    }

    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function recordAutomatedVerification(RbqLicenseResult $result, \DateTimeImmutable $attemptedAt): void
    {
        $this->lastVerificationAttemptAt = $attemptedAt;
        $this->lastVerificationError = $result->error;

        if (null !== $result->error) {
            $this->verificationStatus = 'manual_review_required';

            return;
        }

        if (! $result->found || 'valid' !== $result->status) {
            $this->isVerified = false;
            $this->verificationStatus = 'invalid';
            $this->applyRegistryEvidence($result);

            return;
        }

        $this->isVerified = true;
        $this->isManuallyVerified = false;
        $this->verificationStatus = 'verified';
        $this->lastVerifiedAt = $attemptedAt;
        $this->verificationSource = 'Registre RBQ';
        $this->manualVerificationNotes = null;
        $this->evidenceOriginalName = null;
        $this->evidencePath = null;
        $this->verifiedBy = null;
        $this->applyRegistryEvidence($result);
    }

    public function requireManualReview(string $error, \DateTimeImmutable $attemptedAt): void
    {
        $this->lastVerificationAttemptAt = $attemptedAt;
        $this->lastVerificationError = $error;
        $this->verificationStatus = 'manual_review_required';
    }

    public function recordManualVerification(
        string $source,
        ?string $notes,
        ?string $evidenceOriginalName,
        ?string $evidencePath,
        User $actor,
        \DateTimeImmutable $verifiedAt,
    ): void {
        $source = trim($source);
        if ('' === $source) {
            throw new \InvalidArgumentException('La source de la vérification manuelle est obligatoire.');
        }

        $this->isVerified = true;
        $this->isManuallyVerified = true;
        $this->verificationStatus = 'verified';
        $this->lastVerifiedAt = $verifiedAt;
        $this->lastVerificationAttemptAt = $verifiedAt;
        $this->lastVerificationError = null;
        $this->verificationSource = $source;
        $this->manualVerificationNotes = null !== $notes && '' !== trim($notes) ? trim($notes) : null;
        $this->evidenceOriginalName = $evidenceOriginalName;
        $this->evidencePath = $evidencePath;
        $this->verifiedBy = $actor;
        $this->verifiedBusinessName = $this->name;
    }

    private function applyRegistryEvidence(RbqLicenseResult $result): void
    {
        if (null !== $result->businessName) {
            $this->verifiedBusinessName = $result->businessName;
        }
        if (null !== $result->category) {
            $this->category = $result->category;
        }
        if ($result->expiryDate instanceof \DateTimeInterface) {
            $this->expiryDate = \DateTimeImmutable::createFromInterface($result->expiryDate);
        }
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }
}
