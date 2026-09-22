<?php

declare(strict_types=1);

namespace App\Compliance\Domain\Model;

use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class CCQDeclaration
{
    public const string CURRENT_RULE_VERSION = 'CCQ-2026.1';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'integer')]
    private int $periodMonth;

    #[ORM\Column(type: 'integer')]
    private int $periodYear;

    #[ORM\ManyToOne(inversedBy: 'ccqDeclarations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'cCQDeclarations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSONB)]
    private array $declaration = [];

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 30, options: ['default' => self::CURRENT_RULE_VERSION])]
    private string $ruleVersion = self::CURRENT_RULE_VERSION;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $receiptPath = null;

    /** @var array<int, array<string, mixed>>|null */
    #[ORM\Column(type: Types::JSONB, nullable: true)]
    private ?array $filedSnapshot = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $filedRuleVersion = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $exportedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $filedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $outcome = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPeriodMonth(): int
    {
        return $this->periodMonth;
    }

    public function setPeriodMonth(int $periodMonth): static
    {
        $this->periodMonth = $periodMonth;

        return $this;
    }

    public function getPeriodYear(): int
    {
        return $this->periodYear;
    }

    public function setPeriodYear(int $periodYear): static
    {
        $this->periodYear = $periodYear;

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

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getDeclaration(): array
    {
        return $this->declaration;
    }

    /** @param array<int, array<string, mixed>> $declaration */
    public function setDeclaration(array $declaration): static
    {
        if ($this->status === 'filed' || $this->filedSnapshot !== null) {
            throw new \LogicException('Une déclaration déposée est immuable.');
        }

        $this->declaration = $declaration;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if ($this->status === 'filed' && $status !== 'filed') {
            throw new \LogicException('Une déclaration déposée est immuable.');
        }

        $this->status = $status;

        return $this;
    }

    public function getRuleVersion(): string
    {
        return $this->ruleVersion;
    }

    public function setRuleVersion(string $ruleVersion): static
    {
        if ($this->filedSnapshot !== null || $this->status === 'filed') {
            throw new \LogicException('La version des règles d’une déclaration déposée est immuable.');
        }
        if (trim($ruleVersion) === '') {
            throw new \InvalidArgumentException('La version des règles CCQ est obligatoire.');
        }

        $this->ruleVersion = trim($ruleVersion);

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getReceiptPath(): ?string
    {
        return $this->receiptPath;
    }

    public function setReceiptPath(?string $receiptPath): static
    {
        $this->receiptPath = $receiptPath;

        return $this;
    }

    public function getExportedAt(): ?\DateTimeImmutable
    {
        return $this->exportedAt;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function getFiledSnapshot(): ?array
    {
        return $this->filedSnapshot;
    }

    public function getFiledRuleVersion(): ?string
    {
        return $this->filedRuleVersion;
    }

    public function getFiledAt(): ?\DateTimeImmutable
    {
        return $this->filedAt;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function markExported(string $receiptPath, \DateTimeImmutable $exportedAt): static
    {
        if ($this->status === 'filed') {
            throw new \LogicException('Une déclaration déposée ne peut pas être réexportée.');
        }

        $this->status = 'exported';
        $this->receiptPath = $receiptPath;
        $this->exportedAt = $exportedAt;

        return $this;
    }

    public function markFiled(\DateTimeImmutable $filedAt): static
    {
        if ($this->status !== 'exported' || $this->receiptPath === null) {
            throw new \LogicException('La déclaration doit être exportée avant le dépôt manuel.');
        }

        $this->filedSnapshot = $this->declaration;
        $this->filedRuleVersion = $this->ruleVersion;
        $this->filedAt = $filedAt;
        $this->status = 'filed';

        return $this;
    }

    public function recordOutcome(string $outcome): static
    {
        if ($this->status !== 'filed') {
            throw new \LogicException('Le résultat CCQ peut être enregistré après le dépôt seulement.');
        }
        if (!in_array($outcome, ['accepted', 'rejected'], true)) {
            throw new \InvalidArgumentException('Le résultat CCQ doit être accepted ou rejected.');
        }

        $this->outcome = $outcome;
        // Keep the lifecycle and the optional filing outcome queryable through
        // the same status field used by history/list views.
        $this->status = $outcome;

        return $this;
    }

    public function markSubmitted(string $receiptPath, \DateTimeImmutable $submittedAt): static
    {
        $this->status = 'submitted';
        $this->receiptPath = $receiptPath;
        $this->submittedAt = $submittedAt;

        return $this;
    }
}
