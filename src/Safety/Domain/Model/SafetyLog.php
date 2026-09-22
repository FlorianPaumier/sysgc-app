<?php

declare(strict_types=1);

namespace App\Safety\Domain\Model;

use App\Project\Domain\Model\Project;
use App\Safety\Domain\Enum\SafetyLogStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-type ChecklistItem array{id?: string, label: string, checked: bool, comment: string, protected?: bool}
 * @phpstan-type Signature array{workerName: string, dataUrl: string}
 */
#[ORM\Entity]
#[ORM\Table(name: 'safety_log', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_safety_log_project_date', columns: ['project_id', 'date'])])]
class SafetyLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 100)]
    private string $weather = '';

    /** @var list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}> */
    #[ORM\Column(type: 'json')]
    private array $checklistItems = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $hazardPhotos = [];

    /** @var list<array{workerName: string, dataUrl: string}> */
    #[ORM\Column(type: 'json')]
    private array $workerSignatures = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $incidentReport = null;

    #[ORM\Column(type: 'string', enumType: SafetyLogStatus::class)]
    private SafetyLogStatus $status = SafetyLogStatus::DRAFT;

    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $appliedClientEvents = [];

    /** @var list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalChecklistItems = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalHazardPhotos = null;

    /** @var list<array{workerName: string, dataUrl: string}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalWorkerSignatures = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $originalWeather = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalIncidentReport = null;

    #[ORM\ManyToOne(inversedBy: 'safetyLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    /**
     * @param list<ChecklistItem> $checklistItems
     * @param list<string> $hazardPhotos
     * @param list<Signature> $workerSignatures
     */
    public static function record(
        Project $project,
        \DateTimeInterface $date,
        string $weather,
        array $checklistItems,
        array $hazardPhotos,
        array $workerSignatures,
        ?string $incidentReport,
    ): self {
        $log = new self();
        $log->project = $project;
        $log->date = self::toDoctrineDate($date);
        $log->weather = $weather;
        $log->checklistItems = $checklistItems;
        $log->hazardPhotos = $hazardPhotos;
        $log->workerSignatures = $workerSignatures;
        $log->incidentReport = $incidentReport;

        return $log;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = self::toDoctrineDate($date);

        return $this;
    }

    private static function toDoctrineDate(\DateTimeInterface $date): \DateTime
    {
        return $date instanceof \DateTime
            ? $date
            : \DateTime::createFromImmutable($date);
    }

    public function getWeather(): string
    {
        return $this->weather;
    }

    /** @return list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}> */
    public function getChecklistItems(): array
    {
        return $this->checklistItems;
    }

    /** @return list<string> */
    public function getHazardPhotos(): array
    {
        return $this->hazardPhotos;
    }

    /** @return list<array{workerName: string, dataUrl: string}> */
    public function getWorkerSignatures(): array
    {
        return $this->workerSignatures;
    }

    public function getIncidentReport(): ?string
    {
        return $this->incidentReport;
    }

    /**
     * @param list<ChecklistItem> $checklistItems
     * @param list<string> $hazardPhotos
     * @param list<Signature> $workerSignatures
     */
    public function update(
        string $weather,
        array $checklistItems,
        array $hazardPhotos,
        array $workerSignatures,
        ?string $incidentReport,
    ): void {
        if (SafetyLogStatus::COMPLETED === $this->status) {
            throw new \LogicException('Un rapport complété est en lecture seule.');
        }

        $this->weather = $weather;
        $this->checklistItems = $checklistItems;
        $this->hazardPhotos = $hazardPhotos;
        $this->workerSignatures = $workerSignatures;
        $this->incidentReport = $incidentReport;
        ++$this->version;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function hasAppliedClientEvent(string $clientEventId): bool
    {
        return in_array($clientEventId, $this->appliedClientEvents, true);
    }

    public function rememberClientEvent(string $clientEventId): void
    {
        if (! $this->hasAppliedClientEvent($clientEventId)) {
            $this->appliedClientEvents[] = $clientEventId;
        }
    }

    public function getStatus(): SafetyLogStatus
    {
        return $this->status;
    }

    public function complete(): void
    {
        if (SafetyLogStatus::AMENDED === $this->status) {
            throw new \LogicException('Un rapport amendé est en lecture seule.');
        }

        $this->status = SafetyLogStatus::COMPLETED;
    }

    /**
     * Store the immutable completed payload before applying an administrative correction.
     *
     * @param list<ChecklistItem> $checklistItems
     * @param list<string> $hazardPhotos
     * @param list<Signature> $workerSignatures
     */
    public function amend(
        string $weather,
        array $checklistItems,
        array $hazardPhotos,
        array $workerSignatures,
        ?string $incidentReport,
    ): void {
        if (SafetyLogStatus::COMPLETED !== $this->status) {
            throw new \LogicException('Seul un rapport complété peut être amendé.');
        }

        $this->originalWeather = $this->weather;
        $this->originalChecklistItems = $this->checklistItems;
        $this->originalHazardPhotos = $this->hazardPhotos;
        $this->originalWorkerSignatures = $this->workerSignatures;
        $this->originalIncidentReport = $this->incidentReport;
        $this->weather = $weather;
        $this->checklistItems = $checklistItems;
        $this->hazardPhotos = $hazardPhotos;
        $this->workerSignatures = $workerSignatures;
        $this->incidentReport = $incidentReport;
        $this->status = SafetyLogStatus::AMENDED;
        ++$this->version;
    }

    /** @return list<ChecklistItem>|null */
    public function getOriginalChecklistItems(): ?array
    {
        return $this->originalChecklistItems;
    }

    /** @return list<string>|null */
    public function getOriginalHazardPhotos(): ?array
    {
        return $this->originalHazardPhotos;
    }

    public function getOriginalWeather(): ?string
    {
        return $this->originalWeather;
    }

    /** @return list<Signature>|null */
    public function getOriginalWorkerSignatures(): ?array
    {
        return $this->originalWorkerSignatures;
    }

    public function getOriginalIncidentReport(): ?string
    {
        return $this->originalIncidentReport;
    }

    public function isReadOnly(): bool
    {
        return SafetyLogStatus::COMPLETED === $this->status || SafetyLogStatus::AMENDED === $this->status;
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
}
