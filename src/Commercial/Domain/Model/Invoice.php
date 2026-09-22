<?php

declare(strict_types=1);

namespace App\Commercial\Domain\Model;

use App\Commercial\Domain\Enum\InvoiceStatus;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $milestoneName = '';

    #[ORM\Column]
    private int $percentage = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeInvoiceId = null;

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
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

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getMilestoneName(): string { return $this->milestoneName; }
    public function setMilestoneName(string $milestoneName): static { $this->milestoneName = $milestoneName; return $this; }
    public function getPercentage(): int { return $this->percentage; }
    public function setPercentage(int $percentage): static { $this->percentage = $percentage; return $this; }
    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function getStripeInvoiceId(): ?string { return $this->stripeInvoiceId; }
    public function setStripeInvoiceId(?string $stripeInvoiceId): static { $this->stripeInvoiceId = $stripeInvoiceId; return $this; }

    public function markSent(): static { $this->status = InvoiceStatus::SENT; $this->sentAt = new \DateTimeImmutable(); return $this; }
    public function markPaid(): static { $this->status = InvoiceStatus::PAID; $this->paidAt = new \DateTimeImmutable(); return $this; }
}
