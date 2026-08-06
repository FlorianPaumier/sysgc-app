<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubcontractorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubcontractorRepository::class)]
class Subcontractor
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rbqLicenseNumber = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastVerifiedAt = null;

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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

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

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }
}
