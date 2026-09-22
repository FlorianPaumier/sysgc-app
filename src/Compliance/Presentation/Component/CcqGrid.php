<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Component;

use App\Compliance\Application\HoursSummarizer;
use App\Compliance\Application\ExportCCQMessage;
use App\Compliance\Domain\Model\CCQDeclaration;
use App\Compliance\Domain\Service\ValidationEngine;
use App\Compliance\Domain\ValueObject\CcqError;
use App\Compliance\Infrastructure\Doctrine\CCQDeclarationRepository;
use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsLiveComponent('CcqGrid')]
final class CcqGrid
{
    use DefaultActionTrait;

    public function __construct(private readonly HoursSummarizer $summarizer)
    {
    }

    #[LiveProp]
    public ?Project $project = null;

    /** @var list<Project> */
    public array $projects = [];

    #[LiveProp(writable: true)]
    public int $month;

    #[LiveProp(writable: true)]
    public int $year;

    /** @var list<array<string, mixed>> */
    #[LiveProp(writable: true)]
    public array $hoursData = [];

    /** @var list<array{code: string, message: string, warning: bool, workerId: ?int, week: ?int}> */
    #[LiveProp]
    public array $validationErrors = [];

    public bool $validated = false;

    /** The active CCQ rule set is kept with the workspace snapshot. */
    public string $ruleVersion = 'CCQ-2026.1';

    /** draft → validated → exported → filed; filing is handled by the next flow. */
    public string $status = 'draft';

    public bool $exporting = false;

    public bool $filingConfirmationOpen = false;

    #[LiveProp(writable: true)]
    public string $warningAcknowledgement = '';

    #[LiveProp]
    public ?string $declarationId = null;

    /** @param list<Project> $projects */
    public function mount(?Project $project = null, int $month = 0, int $year = 0, array $projects = []): void
    {
        $this->projects = $projects !== [] ? $projects : ($project instanceof Project ? [$project] : []);
        $this->project = $project ?? $this->projects[0] ?? null;
        $this->month = $month;
        $this->year = $year;
        $this->hoursData = $this->summarizeProjects($this->summarizer, $this->projects);
    }

    #[LiveAction]
    public function validate(ValidationEngine $validation): void
    {
        $project = $this->project;
        $result = $validation->validate(
            hoursData: $this->validationRows(),
            month: $this->month,
            year: $this->year,
            projectIsActive: $project === null || $project->getStatus()->value === 'active',
            hasExistingDeclaration: false,
        );

        $this->validationErrors = array_values(array_map(
            static fn (CcqError $error): array => [
                'code' => $error->code,
                'message' => $error->message,
                'warning' => $error->isWarning,
                'workerId' => $error->workerId,
                'week' => $error->week,
            ],
            $result->errors,
        ));
        $this->validated = $result->isValid();
        $this->warningAcknowledgement = '';
        $this->status = $this->validated ? 'validated' : 'draft';
    }

    public function canExport(): bool
    {
        if (!$this->validated) {
            return false;
        }

        foreach ($this->validationErrors as $error) {
            if ($error['warning'] === true && trim($this->warningAcknowledgement) === '') {
                return false;
            }
        }

        return true;
    }

    #[LiveAction]
    public function acknowledgeWarnings(?string $explanation = null): void
    {
        $explanation ??= $this->warningAcknowledgement;
        if ($this->hasWarnings() && trim($explanation) !== '') {
            $this->warningAcknowledgement = trim($explanation);
        }
    }

    #[LiveAction]
    public function exportPdf(
        ValidationEngine $validation,
        CCQDeclarationRepository $declarations,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messages,
    ): void {
        if (!$this->project instanceof Project) {
            return;
        }
        $project = $this->project;
        $acknowledgement = $this->warningAcknowledgement;
        $this->validate($validation);
        $this->warningAcknowledgement = $acknowledgement;
        if (!$this->canExport()) {
            if ($this->validated && $this->hasWarnings()) {
                $this->validationErrors[] = [
                    'code' => 'warning_acknowledgement_required',
                    'message' => 'Confirmez la prise en compte des avertissements avant l’exportation.',
                    'warning' => true,
                    'workerId' => null,
                    'week' => null,
                ];
            }
            return;
        }

        $declaration = (new CCQDeclaration())
            ->setProject($project)
            ->setOrganization($project->getOrganization())
            ->setPeriodMonth($this->month)
            ->setPeriodYear($this->year)
            ->setRuleVersion($this->ruleVersion)
            ->setDeclaration($this->hoursData)
            ->setStatus('draft');

        if ($declarations->existsSubmittedForProjectPeriod($declaration)) {
            $this->validationErrors = [[
                'code' => 'duplicate_declaration',
                'message' => 'Une déclaration CCQ a déjà été soumise pour ce mois.',
                'warning' => false,
                'workerId' => null,
                'week' => null,
            ]];
            $this->validated = false;

            return;
        }

        $entityManager->persist($declaration);
        $entityManager->flush();
        $messages->dispatch(new ExportCCQMessage($declaration->getId()));
        $this->declarationId = $declaration->getId()->toRfc4122();
        $this->exporting = true;
        $this->status = 'exported';
    }

    #[LiveAction]
    public function prepareFiling(): void
    {
        if ($this->declarationId !== null && $this->status === 'exported' && !$this->exporting) {
            $this->filingConfirmationOpen = true;
        }
    }

    #[LiveAction]
    public function refreshExportStatus(CCQDeclarationRepository $declarations): void
    {
        if ($this->declarationId === null) {
            return;
        }

        $declaration = $declarations->find(Uuid::fromString($this->declarationId));
        if ($declaration instanceof CCQDeclaration && $declaration->getStatus() === 'exported') {
            $this->exporting = false;
            $this->status = 'exported';
        }
    }

    #[LiveAction]
    public function fileDeclaration(CCQDeclarationRepository $declarations, EntityManagerInterface $entityManager): void
    {
        // Filing is deliberately a second, explicit step after the export worker
        // has completed and the confirmation modal has been opened.
        if ($this->declarationId === null || !$this->filingConfirmationOpen || $this->exporting || $this->status !== 'exported') {
            return;
        }

        $declaration = $declarations->find(Uuid::fromString($this->declarationId));
        if (!$declaration instanceof CCQDeclaration) {
            return;
        }

        $declaration->markFiled(new \DateTimeImmutable());
        $entityManager->flush();
        $this->status = 'filed';
        $this->exporting = false;
        $this->filingConfirmationOpen = false;
    }

    private function hasWarnings(): bool
    {
        foreach ($this->validationErrors as $error) {
            if ($error['warning'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     workerId: int|null,
     *     workerName: string,
     *     trade: CcqTrade|string|null,
     *     region: CcqRegion|string|null,
     *     isApprentice: bool,
     *     hasSupervisorSignature: bool,
     *     hasOvertimeFlag: bool,
     *     hasPerDiemFlag: bool,
     *     hasSundayJustification: bool,
     *     dailyHours: array<int, array{
     *         monday: float,
     *         tuesday: float,
     *         wednesday: float,
     *         thursday: float,
     *         friday: float,
     *         saturday: float,
     *         sunday: float,
     *     }>,
     * }>
     */
    private function validationRows(): array
    {
        return array_map(function (array $row): array {
            $dailyHours = [];
            for ($week = 1; $week <= 5; ++$week) {
                // The weekly-entry view records total weekly hours. Split the total across
                // weekdays so the domain engine still receives its daily CCQ shape.
                $total = $this->numericValue($row['week'.$week.'Hours'] ?? 0.0);
                $dailyHours[$week] = $this->weekdayDistribution($total);
            }

            $workerId = $row['workerId'] ?? null;
            $workerName = $row['workerName'] ?? '';
            $trade = $row['trade'] ?? null;
            $region = $row['region'] ?? null;

            return [
                'workerId' => is_int($workerId) ? $workerId : null,
                'workerName' => is_string($workerName) ? $workerName : '',
                'trade' => $trade instanceof CcqTrade || is_string($trade) ? $trade : null,
                'region' => $region instanceof CcqRegion || is_string($region) ? $region : null,
                'isApprentice' => is_bool($row['isApprentice'] ?? null) && $row['isApprentice'],
                'hasSupervisorSignature' => is_bool($row['hasSupervisorSignature'] ?? null) && $row['hasSupervisorSignature'],
                'hasOvertimeFlag' => is_bool($row['hasOvertimeFlag'] ?? null) && $row['hasOvertimeFlag'],
                'hasPerDiemFlag' => is_bool($row['hasPerDiemFlag'] ?? null) && $row['hasPerDiemFlag'],
                'hasSundayJustification' => is_bool($row['hasSundayJustification'] ?? null) && $row['hasSundayJustification'],
                'dailyHours' => $dailyHours,
            ];
        }, $this->hoursData);
    }

    /** @param array<string, mixed> $row */
    public function totalFor(array $row): float
    {
        $total = 0.0;
        for ($week = 1; $week <= 5; ++$week) {
            $total += $this->numericValue($row['week'.$week.'Hours'] ?? 0.0);
        }

        return $total;
    }

    public function weekTotal(int $week): float
    {
        $total = 0.0;
        foreach ($this->hoursData as $row) {
            $total += $this->numericValue($row['week'.$week.'Hours'] ?? 0.0);
        }

        return $total;
    }

    public function grandTotal(): float
    {
        $total = 0.0;
        foreach ($this->hoursData as $row) {
            $total += $this->totalFor($row);
        }

        return $total;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'validated' => 'Validée',
            'exported' => 'Exportée',
            'filed' => 'Déposée',
            'accepted' => 'Acceptée',
            'rejected' => 'Rejetée',
            default => 'Brouillon',
        };
    }

    /**
     * @param list<Project> $projects
     * @return list<array<string, mixed>>
     */
    private function summarizeProjects(HoursSummarizer $summarizer, array $projects): array
    {
        $rows = [];
        foreach ($projects as $project) {
            foreach ($summarizer->summarize($project, $this->month, $this->year) as $row) {
                $row['projectId'] = $project->getId()?->toRfc4122();
                $row['projectName'] = $project->getName() ?? $project->getAddress();
                $row['projectAddress'] = $project->getAddress();
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *     monday: float,
     *     tuesday: float,
     *     wednesday: float,
     *     thursday: float,
     *     friday: float,
     *     saturday: float,
     *     sunday: float,
     * }
     */
    private function weekdayDistribution(float $total): array
    {
        $days = [
            'monday' => 0.0,
            'tuesday' => 0.0,
            'wednesday' => 0.0,
            'thursday' => 0.0,
            'friday' => 0.0,
            'saturday' => 0.0,
            'sunday' => 0.0,
        ];
        foreach (array_keys($days) as $day) {
            $hours = min(14.0, $total);
            $days[$day] = $hours;
            $total -= $hours;
            if ($total <= 0.0) {
                break;
            }
        }

        return $days;
    }

    private function numericValue(mixed $value): float
    {
        return is_float($value) ? $value : (is_int($value) ? (float) $value : 0.0);
    }
}
