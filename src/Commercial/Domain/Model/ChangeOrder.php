<?php

declare(strict_types=1);

namespace App\Commercial\Domain\Model;

use App\Commercial\Domain\Enum\ChangeOrderStatus;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class ChangeOrder
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $costImpact = '0.00';

    #[ORM\Column]
    private int $scheduleImpactDays = 0;

    #[ORM\Column(enumType: ChangeOrderStatus::class)]
    private ChangeOrderStatus $status = ChangeOrderStatus::PENDING;

    /** @var list<array{timestamp: string, user: string, action: string, detail: string}> */
    #[ORM\Column(type: 'json')]
    private array $auditTrail = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\ManyToOne(inversedBy: 'changeOrders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCostImpact(): string { return $this->costImpact; }
    public function setCostImpact(string $costImpact): static { $this->costImpact = $costImpact; return $this; }
    public function getScheduleImpactDays(): int { return $this->scheduleImpactDays; }
    public function setScheduleImpactDays(int $days): static { $this->scheduleImpactDays = $days; return $this; }
    public function getStatus(): ChangeOrderStatus { return $this->status; }
    public function getRequestedBy(): ?string { return $this->requestedBy; }
    public function setRequestedBy(?string $requestedBy): static { $this->requestedBy = $requestedBy; return $this; }
    public function getApprovedBy(): ?string { return $this->approvedBy; }

    /** @return list<array{timestamp: string, user: string, action: string, detail: string}> */
    public function getAuditTrail(): array { return $this->auditTrail; }

    public function recordCreated(string $user): static
    {
        return $this->appendAudit($user, 'created', 'Avenant créé');
    }

    public function recordModified(string $user, string $detail): static
    {
        return $this->appendAudit($user, 'modified', $detail);
    }

    public function transitionTo(ChangeOrderStatus $status, string $user, string $detail): static
    {
        $this->status = $status;
        if ($status === ChangeOrderStatus::APPROVED) { $this->approvedBy = $user; }

        return $this->appendAudit($user, $status->value, $detail);
    }

    private function appendAudit(string $user, string $action, string $detail): static
    {
        $this->auditTrail[] = ['timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM), 'user' => $user, 'action' => $action, 'detail' => $detail];

        return $this;
    }
}
