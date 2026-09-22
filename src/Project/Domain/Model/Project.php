<?php

declare(strict_types=1);

namespace App\Project\Domain\Model;

use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Enum\ProjectStatus;
use App\Shared\Domain\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Project
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', enumType: ProjectStatus::class)]
    private ProjectStatus $status = ProjectStatus::ACTIVE;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $budget = '0.00';

    /**
     * @var Collection<int, \App\Compliance\Domain\Model\Subcontractor>
     */
    #[ORM\ManyToMany(targetEntity: \App\Compliance\Domain\Model\Subcontractor::class, inversedBy: 'projects')]
    private Collection $subcontractors;

    /**
     * @var Collection<int, \App\Safety\Domain\Model\SafetyLog>
     */
    #[ORM\OneToMany(targetEntity: \App\Safety\Domain\Model\SafetyLog::class, mappedBy: 'project', cascade: ['remove'], orphanRemoval: true)]
    private Collection $safetyLogs;

    /**
     * @var Collection<int, \App\Commercial\Domain\Model\ChangeOrder>
     */
    #[ORM\OneToMany(targetEntity: \App\Commercial\Domain\Model\ChangeOrder::class, mappedBy: 'project', cascade: ['remove'], orphanRemoval: true)]
    private Collection $changeOrders;

    /**
     * @var Collection<int, \App\Commercial\Domain\Model\Invoice>
     */
    #[ORM\OneToMany(targetEntity: \App\Commercial\Domain\Model\Invoice::class, mappedBy: 'project', cascade: ['remove'], orphanRemoval: true)]
    private Collection $invoices;

    /**
     * @var Collection<int, \App\Compliance\Domain\Model\CCQDeclaration>
     */
    #[ORM\OneToMany(targetEntity: \App\Compliance\Domain\Model\CCQDeclaration::class, mappedBy: 'project', cascade: ['remove'], orphanRemoval: true)]
    private Collection $ccqDeclarations;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'assignedProjects')]
    private Collection $assignedForemen;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'projects')]
        #[ORM\JoinColumn(nullable: false)]
        private Organization $organization,
        #[ORM\Column(length: 255)]
        private string $address,
        #[ORM\Column(length: 255)]
        private string $clientName,
    ) {
        $this->id = Uuid::v7();
        $this->subcontractors = new ArrayCollection;
        $this->safetyLogs = new ArrayCollection;
        $this->changeOrders = new ArrayCollection;
        $this->invoices = new ArrayCollection;
        $this->ccqDeclarations = new ArrayCollection;
        $this->assignedForemen = new ArrayCollection;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getBudget(): string
    {
        return $this->budget;
    }

    /** @return Collection<int, User> */
    public function getAssignedForemen(): Collection
    {
        return $this->assignedForemen;
    }

    public function assignForeman(User $user): static
    {
        if (! $this->assignedForemen->contains($user)) {
            $this->assignedForemen->add($user);
            $user->assignProject($this);
        }

        return $this;
    }

    public function unassignForeman(User $user): static
    {
        $this->assignedForemen->removeElement($user);
        $user->unassignProject($this);

        return $this;
    }

    public function setBudget(string $budget): static
    {
        $this->budget = $budget;

        return $this;
    }

    /**
     * @return Collection<int, \App\Compliance\Domain\Model\Subcontractor>
     */
    public function getSubcontractors(): Collection
    {
        return $this->subcontractors;
    }

    public function addSubcontractor(\App\Compliance\Domain\Model\Subcontractor $subcontractor): static
    {
        if (! $this->subcontractors->contains($subcontractor)) {
            $this->subcontractors->add($subcontractor);
        }

        return $this;
    }

    public function removeSubcontractor(\App\Compliance\Domain\Model\Subcontractor $subcontractor): static
    {
        $this->subcontractors->removeElement($subcontractor);

        return $this;
    }

    /**
     * @return Collection<int, \App\Safety\Domain\Model\SafetyLog>
     */
    public function getSafetyLogs(): Collection
    {
        return $this->safetyLogs;
    }

    /**
     * @return Collection<int, \App\Commercial\Domain\Model\ChangeOrder>
     */
    public function getChangeOrders(): Collection
    {
        return $this->changeOrders;
    }

    /**
     * @return Collection<int, \App\Commercial\Domain\Model\Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    /**
     * @return Collection<int, \App\Compliance\Domain\Model\CCQDeclaration>
     */
    public function getCcqDeclarations(): Collection
    {
        return $this->ccqDeclarations;
    }
}
