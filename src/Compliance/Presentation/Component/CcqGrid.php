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

    public function __construct(
        private readonly HoursSummarizer $summarizer,
        private readonly \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $authorization,
    )
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

    #[LiveProp]
    public bool $validated = false;

    /** The active CCQ rule set is kept with the workspace snapshot. */
    #[LiveProp]
    public string $ruleVersion = 'CCQ-2026.1';

    /** draft → validated → exported → filed; filing is handled by the next flow. */
    #[LiveProp]
    public string $status = 'draft';

    #[LiveProp]
    public bool $exporting = false;

    #[LiveProp]
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
        $this->assertCanManage();
        if ($this->month < 1 || $this->month > 12 || $this->year < 2000 || $this->year > 2100) {
            throw new \InvalidArgumentException('La période CCQ est invalide.');
        }
        $project = $this->project;
        $result = $validation->validate(
            hoursData: $this->validationRows(),
            month: $this->month,
            year: $this->year,
            projectIsActive: $project->getStatus()->value === 'active',
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
        return array_all($this->validationErrors, fn($error) => !($error['warning'] === true && trim($this->warningAcknowledgement) === ''));
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

        $entityManager->wrapInTransaction(function () use ($project, $declarations, $entityManager, $messages): void {
            // Serialize exports for the same organization before checking the period.
            $entityManager->lock($project->getOrganization(), \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $existing = $declarations->findOneBy([
                'organization' => $project->getOrganization(),
                'periodMonth' => $this->month,
                'periodYear' => $this->year,
            ]);
            if ($existing instanceof CCQDeclaration) {
                $this->declarationId = $existing->getId()->toRfc4122();
                $this->status = $existing->getStatus() ?? 'draft';
                $this->exporting = $this->status === 'draft';
                return;
            }
            $snapshot = $this->hoursData;
            foreach ($snapshot as &$row) {
                for ($week = 1; $week <= 5; ++$week) {
                    $row['week'.$week.'Hours'] = $this->rowWeekTotal($row, $week);
                }
                $row['totalHours'] = $this->totalFor($row);
            }
            unset($row);
            $declaration = new CCQDeclaration()
                ->setProject($project)
                ->setOrganization($project->getOrganization())
                ->setPeriodMonth($this->month)
                ->setPeriodYear($this->year)
                ->setRuleVersion($this->ruleVersion)
                ->setDeclaration($snapshot)
                ->setStatus('draft');
            $entityManager->persist($declaration);
            $entityManager->flush();
            $messages->dispatch(new ExportCCQMessage($declaration->getId()));
            $this->declarationId = $declaration->getId()->toRfc4122();
            $this->exporting = true;
            $this->status = 'draft';
        });
    }

    #[LiveAction]
    public function prepareFiling(): void
    {
        $this->assertCanManage();
        if ($this->declarationId !== null && $this->status === 'exported' && !$this->exporting) {
            $this->filingConfirmationOpen = true;
        }
    }

    #[LiveAction]
    public function refreshExportStatus(CCQDeclarationRepository $declarations): void
    {
        $this->assertCanManage();
        if ($this->declarationId === null) {
            return;
        }

        $declaration = $declarations->findOneForOrganization(Uuid::fromString($this->declarationId), $this->project->getOrganization()->getId());
        if ($declaration instanceof CCQDeclaration && $declaration->getStatus() === 'exported') {
            $this->exporting = false;
            $this->status = 'exported';
        }
    }

    #[LiveAction]
    public function fileDeclaration(CCQDeclarationRepository $declarations, EntityManagerInterface $entityManager): void
    {
        $this->assertCanManage();
        // Filing is deliberately a second, explicit step after the export worker
        // has completed and the confirmation modal has been opened.
        if ($this->declarationId === null || !$this->filingConfirmationOpen || $this->exporting || $this->status !== 'exported') {
            return;
        }

        $declaration = $declarations->findOneForOrganization(Uuid::fromString($this->declarationId), $this->project->getOrganization()->getId());
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
        return array_any($this->validationErrors, fn($error) => $error['warning'] === true);
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
        $workers = [];
        $rows = $this->hoursData;
        $workerIds = array_column($rows, 'workerId');
        if ($this->project !== null) {
            $includedProjects = array_column($rows, 'projectId');
            $includedProjects[] = $this->project->getId()?->toRfc4122();
            foreach ($this->project->getOrganization()->getProjects() as $otherProject) {
                if (in_array($otherProject->getId()?->toRfc4122(), $includedProjects, true)) {
                    continue;
                }
                foreach ($this->summarizer->summarize($otherProject, $this->month, $this->year) as $row) {
                    if (in_array($row['workerId'], $workerIds, true)) {
                        $rows[] = $row;
                    }
                }
            }
        }
        foreach ($rows as $index => $row) {
            if (!is_array($row['dailyHours'] ?? null)) {
                throw new \InvalidArgumentException('Les heures doivent être renseignées par jour avant la validation.');
            }
            $workerId = $row['workerId'] ?? null;
            $trade = $row['trade'] ?? null;
            $region = $row['region'] ?? null;
            $key = is_int($workerId) ? 'worker-'.$workerId : 'row-'.$index;
            if (!isset($workers[$key])) {
                $workers[$key] = [
                    'workerId' => is_int($workerId) ? $workerId : null,
                    'workerName' => is_string($row['workerName'] ?? null) ? $row['workerName'] : '',
                    'trade' => $trade instanceof CcqTrade || is_string($trade) ? $trade : null,
                    'region' => $region instanceof CcqRegion || is_string($region) ? $region : null,
                    'isApprentice' => ($row['isApprentice'] ?? false) === true,
                    'hasSupervisorSignature' => ($row['hasSupervisorSignature'] ?? false) === true,
                    'hasOvertimeFlag' => ($row['hasOvertimeFlag'] ?? false) === true,
                    'hasPerDiemFlag' => ($row['hasPerDiemFlag'] ?? false) === true,
                    'hasSundayJustification' => ($row['hasSundayJustification'] ?? false) === true,
                    'dailyHours' => [],
                ];
            }
            foreach ($row['dailyHours'] as $day => $value) {
                $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $this->year, $this->month, (int) $day));
                if ((int) $day < 1 || $date->format('Y-m') !== sprintf('%04d-%02d', $this->year, $this->month)) {
                    throw new \InvalidArgumentException('La date des heures CCQ est invalide.');
                }
                $week = (int) $date->format('W');
                $weekday = match ((int) $date->format('N')) {
                    1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday',
                    5 => 'friday', 6 => 'saturday', default => 'sunday',
                };
                $workers[$key]['dailyHours'][$week] ??= array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], 0.0);
                $hours = $this->numericValue($value);
                if ($hours < 0) {
                    throw new \InvalidArgumentException('Les heures ne peuvent pas être négatives.');
                }
                $workers[$key]['dailyHours'][$week][$weekday] += $hours;
            }
        }

        return array_values($workers);
    }

    /** @param array<string, mixed> $row */
    public function totalFor(array $row): float
    {
        $total = 0.0;
        for ($week = 1; $week <= 5; ++$week) {
            $total += $this->rowWeekTotal($row, $week);
        }

        return $total;
    }

    public function weekTotal(int $week): float
    {
        $total = 0.0;
        foreach ($this->hoursData as $row) {
            $total += $this->rowWeekTotal($row, $week);
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
                $daysInMonth = (int) new \DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month))->format('t');
                $row['dailyHours'] += array_fill(1, $daysInMonth, 0.0);
                ksort($row['dailyHours']);
                $row['tradeLabel'] = $row['trade']?->label();
                $row['regionLabel'] = $row['region']?->label();
                $row['trade'] = $row['trade']?->value;
                $row['region'] = $row['region']?->value;
                $row['projectId'] = $project->getId()?->toRfc4122();
                $row['projectName'] = $project->getName() ?? $project->getAddress();
                $row['projectAddress'] = $project->getAddress();
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function rowWeekTotal(array $row, int $week): float
    {
        if (!is_array($row['dailyHours'] ?? null)) {
            return $this->numericValue($row['week'.$week.'Hours'] ?? 0.0);
        }
        $total = 0.0;
        foreach ($row['dailyHours'] as $day => $hours) {
            if (min(5, intdiv((int) $day - 1, 7) + 1) === $week) {
                $total += $this->numericValue($hours);
            }
        }
        return $total;
    }

    public function canManage(): bool
    {
        return $this->project instanceof Project
            && $this->authorization->isGranted(\App\Project\Infrastructure\Security\ProjectVoter::EDIT, $this->project);
    }

    /** @phpstan-assert Project $this->project */
    private function assertCanManage(): void
    {
        if (!$this->canManage()) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }
    }

    private function numericValue(mixed $value): float
    {
        if ($value === '') {
            return 0.0;
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new \InvalidArgumentException('Les heures doivent être un nombre valide.');
        }
        return (float) $value;
    }
}
