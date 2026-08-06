<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CCQDeclaration;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\SafetyLog;
use App\Entity\Subcontractor;

final class ComplianceScoreCalculator
{
    // Scoring weights (BR-24)
    private const int WEIGHT_CCQ_CURRENT_MONTH = 35;
    private const int WEIGHT_CCQ_LAST_MONTH = 15;
    private const int WEIGHT_SAFETY_LOG_TODAY = 20;
    private const int WEIGHT_SAFETY_LOGS_WEEK = 10;
    private const int WEIGHT_ALL_SUBS_VERIFIED = 15;
    private const int WEIGHT_NO_OVERDUE_INVOICES = 5;

    public function calculate(Project $project, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('today');

        $score = 0;

        if ($this->hasCcqForMonth($project, (int) $now->format('n'), (int) $now->format('Y'))) {
            $score += self::WEIGHT_CCQ_CURRENT_MONTH;
        }

        $lastMonth = $now->modify('first day of last month');
        if ($this->hasCcqForMonth($project, (int) $lastMonth->format('n'), (int) $lastMonth->format('Y'))) {
            $score += self::WEIGHT_CCQ_LAST_MONTH;
        }

        if ($this->hasSafetyLogForDate($project, $now)) {
            $score += self::WEIGHT_SAFETY_LOG_TODAY;
        }

        if ($this->hasSafetyLogsAllWeekdays($project, $now)) {
            $score += self::WEIGHT_SAFETY_LOGS_WEEK;
        }

        if ($this->allSubcontractorsVerified($project)) {
            $score += self::WEIGHT_ALL_SUBS_VERIFIED;
        }

        if ($this->noOverdueInvoices($project)) {
            $score += self::WEIGHT_NO_OVERDUE_INVOICES;
        }

        return $score;
    }

    /**
     * @return array{label: string, color: string}
     */
    public function getBadge(int $score): array
    {
        if ($score >= 80) {
            return ['label' => 'Conforme', 'color' => 'green'];
        }

        if ($score >= 50) {
            return ['label' => 'À surveiller', 'color' => 'yellow'];
        }

        return ['label' => 'Non conforme', 'color' => 'red'];
    }

    /**
     * @return list<array{level: string, message: string, link: string}>
     */
    public function getAlerts(Organization $organization, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('today');
        $alerts = [];

        foreach ($organization->getProjects() as $project) {
            $this->collectCcqAlerts($project, $now, $alerts);
            $this->collectSubcontractorAlerts($project, $alerts);
            $this->collectSafetyLogAlerts($project, $now, $alerts);
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'ok',
                'message' => 'Tous les indicateurs sont au vert.',
                'link' => '/dashboard',
            ];
        }

        // Sort by priority: critical first, then warning, then ok
        usort($alerts, fn (array $a, array $b): int => match (true) {
            $a['level'] === 'critical' && $b['level'] !== 'critical' => -1,
            $a['level'] !== 'critical' && $b['level'] === 'critical' => 1,
            $a['level'] === 'warning' && $b['level'] === 'ok' => -1,
            $a['level'] === 'ok' && $b['level'] === 'warning' => 1,
            default => 0,
        });

        return $alerts;
    }

    // ── Private helpers ────────────────────────────────────────

    private function hasCcqForMonth(Project $project, int $month, int $year): bool
    {
        foreach ($project->getCcqDeclarations() as $declaration) {
            if ($declaration->getPeriodMonth() === $month && $declaration->getPeriodYear() === $year) {
                return true;
            }
        }

        return false;
    }

    private function hasSafetyLogForDate(Project $project, \DateTimeImmutable $date): bool
    {
        $target = $date->format('Y-m-d');
        foreach ($project->getSafetyLogs() as $log) {
            $logDate = $log->getDate();
            if ($logDate !== null && $logDate->format('Y-m-d') === $target) {
                return true;
            }
        }

        return false;
    }

    private function hasSafetyLogsAllWeekdays(Project $project, \DateTimeImmutable $now): bool
    {
        $monday = $now->modify('monday this week');

        for ($offset = 0; $offset <= 4; $offset++) {
            $day = $monday->modify("+{$offset} days");
            if (! $this->hasSafetyLogForDate($project, \DateTimeImmutable::createFromInterface($day))) {
                return false;
            }
        }

        return true;
    }

    private function allSubcontractorsVerified(Project $project): bool
    {
        $subs = $project->getSubcontractors();
        if ($subs->isEmpty()) {
            return true;
        }

        foreach ($subs as $sub) {
            if (! $sub->isVerified()) {
                return false;
            }
        }

        return true;
    }

    private function noOverdueInvoices(Project $project): bool
    {
        $invoices = $project->getInvoices();
        if ($invoices->isEmpty()) {
            return true;
        }

        foreach ($invoices as $invoice) {
            if ($invoice->getStatus() === InvoiceStatus::OVERDUE) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{level: string, message: string, link: string}> $alerts
     */
    private function collectCcqAlerts(Project $project, \DateTimeImmutable $now, array &$alerts): void
    {
        $currentMonth = (int) $now->format('n');
        $currentYear = (int) $now->format('Y');
        $hasDeclaration = $this->hasCcqForMonth($project, $currentMonth, $currentYear);

        if ($hasDeclaration) {
            return;
        }

        $lastDayOfMonth = (int) $now->format('t');
        $currentDay = (int) $now->format('j');
        $daysUntilDeadline = $lastDayOfMonth - $currentDay;

        $projectName = $project->getName() ?? 'Sans nom';

        if ($daysUntilDeadline <= 0) {
            $alerts[] = [
                'level' => 'critical',
                'message' => sprintf('Déclaration CCQ en retard pour « %s » — échéance dépassée.', $projectName),
                'link' => sprintf('/ccq/declare/%s', $project->getId()),
            ];
        } elseif ($daysUntilDeadline <= 7) {
            $alerts[] = [
                'level' => 'warning',
                'message' => sprintf('Déclaration CCQ à produire pour « %s » — échéance dans %d jour(s).', $projectName, $daysUntilDeadline),
                'link' => sprintf('/ccq/declare/%s', $project->getId()),
            ];
        }
    }

    /**
     * @param list<array{level: string, message: string, link: string}> $alerts
     */
    private function collectSubcontractorAlerts(Project $project, array &$alerts): void
    {
        foreach ($project->getSubcontractors() as $sub) {
            if (! $sub->isVerified()) {
                $alerts[] = [
                    'level' => 'critical',
                    'message' => sprintf('Sous-traitant « %s » non vérifié dans « %s ».', $sub->getName(), $project->getName() ?? 'Sans nom'),
                    'link' => sprintf('/project/%s/subcontractors', $project->getId()),
                ];
            }
        }
    }

    /**
     * @param list<array{level: string, message: string, link: string}> $alerts
     */
    private function collectSafetyLogAlerts(Project $project, \DateTimeImmutable $now, array &$alerts): void
    {
        if (! $this->hasSafetyLogForDate($project, $now)) {
            $alerts[] = [
                'level' => 'warning',
                'message' => sprintf('Journal de sécurité manquant aujourd\'hui pour « %s ».', $project->getName() ?? 'Sans nom'),
                'link' => sprintf('/project/%s/safety', $project->getId()),
            ];
        }
    }
}
