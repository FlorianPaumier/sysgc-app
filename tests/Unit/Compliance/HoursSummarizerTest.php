<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\HoursEntryReader;
use App\Compliance\Application\HoursSummarizer;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use App\Workforce\Domain\Model\HoursEntry;
use App\Workforce\Domain\Model\Worker;
use PHPUnit\Framework\TestCase;

final class HoursSummarizerTest extends TestCase
{
    public function test_summarizes_project_hours_into_ccq_calendar_weeks(): void
    {
        $organization = (new Organization())->setName('Construction Nord');
        $project = new Project($organization, '100 Rue Principale', 'Client');
        $worker = (new Worker())
            ->setName('Jeanne Tremblay')
            ->setCcqTrade(CcqTrade::Electrician)
            ->setCcqRegion(CcqRegion::Montreal)
            ->setOrganization($organization);

        $entry = (new HoursEntry())
            ->setProject($project)
            ->setWorker($worker)
            ->setWeekStart(new \DateTime('2026-06-08'))
            ->setHours(['monday' => 8, 'tuesday' => 8, 'wednesday' => 8, 'thursday' => 8, 'friday' => 8]);

        $reader = new class([$entry]) implements HoursEntryReader {
            /** @param list<HoursEntry> $entries */
            public function __construct(private array $entries) {}

            public function findForProjectAndMonth(Project $project, int $month, int $year): iterable
            {
                return $this->entries;
            }
        };

        self::assertSame([
            [
                'workerId' => null,
                'workerName' => 'Jeanne Tremblay',
                'trade' => CcqTrade::Electrician,
                'region' => CcqRegion::Montreal,
                'week1Hours' => 0.0,
                'week2Hours' => 40.0,
                'week3Hours' => 0.0,
                'week4Hours' => 0.0,
                'week5Hours' => 0.0,
                'totalHours' => 40.0,
            ],
        ], (new HoursSummarizer($reader))->summarize($project, 6, 2026));
    }
}
