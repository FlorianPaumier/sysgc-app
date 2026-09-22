<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Domain\Service\ValidationEngine;
use App\Compliance\Application\HoursEntryReader;
use App\Compliance\Application\HoursSummarizer;
use App\Compliance\Presentation\Component\CcqGrid;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use PHPUnit\Framework\TestCase;

final class CcqGridTest extends TestCase
{
    public function test_validation_exposes_french_rule_codes_without_custom_javascript(): void
    {
        $organization = (new Organization())->setName('Construction Nord');
        $grid = $this->grid();
        $grid->project = new Project($organization, '100 Rue Principale', 'Client');
        $grid->month = 6;
        $grid->year = 2026;
        $grid->hoursData = [[
            'workerId' => 7,
            'workerName' => 'Jeanne Tremblay',
            'trade' => CcqTrade::Electrician,
            'region' => CcqRegion::Montreal,
            'week1Hours' => 40.0,
            'week2Hours' => 0.0,
            'week3Hours' => 0.0,
            'week4Hours' => 0.0,
            'week5Hours' => 0.0,
            'totalHours' => 40.0,
        ]];

        $grid->validate(new ValidationEngine());

        self::assertTrue($grid->validated);
        self::assertSame([], $grid->validationErrors);
    }

    public function test_organization_month_workspace_exposes_versioned_lifecycle_and_sticky_totals(): void
    {
        $grid = $this->grid();
        $grid->hoursData = [
            ['projectId' => 'chantier-a', 'projectName' => 'Résidence Nord', 'workerName' => 'Jeanne', 'week1Hours' => 40.0, 'week2Hours' => 20.0],
            ['projectId' => 'chantier-b', 'projectName' => 'École du Sud', 'workerName' => 'Marc', 'week1Hours' => 10.0, 'week2Hours' => 30.0],
        ];

        self::assertSame('CCQ-2026.1', $grid->ruleVersion);
        self::assertSame('draft', $grid->status);
        self::assertSame(50.0, $grid->weekTotal(1));
        self::assertSame(50.0, $grid->weekTotal(2));
        self::assertSame(100.0, $grid->grandTotal());
        self::assertSame('Brouillon', $grid->statusLabel());
    }

    public function test_warning_requires_an_acknowledgement_before_export(): void
    {
        $grid = $this->grid();
        $grid->validationErrors = [[
            'code' => 'worker_no_hours',
            'message' => 'Le travailleur n’a aucune heure déclarée.',
            'warning' => true,
            'workerId' => 7,
            'week' => null,
        ]];
        $grid->validated = true;

        self::assertFalse($grid->canExport());

        $grid->acknowledgeWarnings('Vérifié avec la paie du mois.');

        self::assertTrue($grid->canExport());
        self::assertSame('Vérifié avec la paie du mois.', $grid->warningAcknowledgement);
    }

    private function grid(): CcqGrid
    {
        return new CcqGrid(new HoursSummarizer(new class implements HoursEntryReader {
            public function findForProjectAndMonth(Project $project, int $month, int $year): iterable
            {
                return [];
            }
        }));
    }
}
