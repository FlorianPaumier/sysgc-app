<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use App\Workforce\Domain\Model\Worker;

/**
 * @phpstan-type Summary array{
 *     workerId: int|null,
 *     workerName: string,
 *     trade: CcqTrade|null,
 *     region: CcqRegion|null,
 *     week1Hours: float,
 *     week2Hours: float,
 *     week3Hours: float,
 *     week4Hours: float,
 *     week5Hours: float,
 *     totalHours: float,
 *     dailyHours: array<int, float>,
 * }
 */
final readonly class HoursSummarizer
{
    public function __construct(private HoursEntryReader $hoursEntries) {}

    /**
     * @return list<Summary>
     */
    public function summarize(Project $project, int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Le mois doit être compris entre 1 et 12.');
        }

        $summaries = [];
        foreach ($this->hoursEntries->findForProjectAndMonth($project, $month, $year) as $entry) {
            $worker = $entry->getWorker();
            if ($worker === null) {
                continue;
            }

            $key = $worker->getId() === null ? 'object-'.spl_object_id($worker) : 'worker-'.$worker->getId();
            if (!isset($summaries[$key])) {
                $summaries[$key] = $this->newSummary($worker);
            }

            $weekStart = $entry->getWeekStart();
            if ($weekStart === null) {
                continue;
            }

            foreach ($entry->getHours() as $day => $hours) {
                $dayOffset = match ($day) {
                    'monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3,
                    'friday' => 4, 'saturday' => 5, 'sunday' => 6,
                    default => null,
                };
                if ($dayOffset === null) {
                    continue;
                }

                $date = (clone $weekStart)->modify(sprintf('+%d days', $dayOffset));
                if ((int) $date->format('n') !== $month || (int) $date->format('Y') !== $year) {
                    continue;
                }

                $week = min(5, intdiv(((int) $date->format('j')) - 1, 7) + 1);
                $dayNumber = (int) $date->format('j');
                $summaries[$key]['dailyHours'][$dayNumber] = ($summaries[$key]['dailyHours'][$dayNumber] ?? 0.0) + (float) $hours;
                $this->addHours($summaries[$key], $week, (float) $hours);
            }
        }

        return array_values($summaries);
    }

    /** @return Summary */
    private function newSummary(Worker $worker): array
    {
        return [
            'workerId' => $worker->getId(),
            'workerName' => $worker->getName() ?? '',
            'trade' => $worker->getCcqTrade(),
            'region' => $worker->getCcqRegion(),
            'week1Hours' => 0.0,
            'week2Hours' => 0.0,
            'week3Hours' => 0.0,
            'week4Hours' => 0.0,
            'week5Hours' => 0.0,
            'totalHours' => 0.0,
            'dailyHours' => [],
        ];
    }

    /** @param Summary $summary */
    private function addHours(array &$summary, int $week, float $hours): void
    {
        match ($week) {
            1 => $summary['week1Hours'] += $hours,
            2 => $summary['week2Hours'] += $hours,
            3 => $summary['week3Hours'] += $hours,
            4 => $summary['week4Hours'] += $hours,
            5 => $summary['week5Hours'] += $hours,
            default => throw new \LogicException('Le numéro de semaine CCQ doit être compris entre 1 et 5.'),
        };
        $summary['totalHours'] += $hours;
    }
}
