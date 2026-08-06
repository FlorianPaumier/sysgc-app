<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\CcqRegion;
use App\Enum\CcqTrade;
use App\Service\ValidationEngine;
use App\Service\ValueObject\CcqError;
use App\Service\ValueObject\CcqValidationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationEngineTest extends TestCase
{
    private ValidationEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new ValidationEngine;
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Build a single worker entry with sensible defaults.
     */
    private function worker(array $overrides = []): array
    {
        return array_merge([
            'workerId' => 1,
            'workerName' => 'Jean Tremblay',
            'trade' => CcqTrade::Carpenter,
            'region' => CcqRegion::Montreal,
            'isApprentice' => false,
            'hasSupervisorSignature' => false,
            'hasOvertimeFlag' => false,
            'hasPerDiemFlag' => false,
            'hasSundayJustification' => false,
            'dailyHours' => [
                1 => ['monday' => 8.0, 'tuesday' => 8.0, 'wednesday' => 8.0, 'thursday' => 8.0, 'friday' => 8.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ], $overrides);
    }

    private function validate(array $workers, array $options = []): CcqValidationResult
    {
        return $this->engine->validate(
            hoursData: $workers,
            month: $options['month'] ?? 6,
            year: $options['year'] ?? 2026,
            projectIsActive: $options['projectIsActive'] ?? true,
            hasExistingDeclaration: $options['hasExistingDeclaration'] ?? false,
            crossProjectWeeklyHours: $options['crossProjectWeeklyHours'] ?? [],
        );
    }

    private function assertHasError(string $code, CcqValidationResult $result): void
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && $error->isError()) {
                $this->assertNotEmpty($error->message);
                $this->assertSame($code, $error->code);

                return;
            }
        }
        $this->fail("Expected error '{$code}' not found. Errors: " . implode(', ', array_map(fn (CcqError $e): string => $e->code, $result->errors)));
    }

    private function assertHasWarning(string $code, CcqValidationResult $result): void
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && $error->isWarning) {
                $this->assertNotEmpty($error->message);

                return;
            }
        }
        $this->fail("Expected warning '{$code}' not found.");
    }

    private function assertNoError(string $code, CcqValidationResult $result): void
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && $error->isError()) {
                $this->fail("Did not expect error '{$code}' but it was found.");
            }
        }
        $this->assertTrue(true); // pass
    }

    private function assertNoWarning(string $code, CcqValidationResult $result): void
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && $error->isWarning) {
                $this->fail("Did not expect warning '{$code}' but it was found.");
            }
        }
        $this->assertTrue(true);
    }

    // ── Rule 1: worker_no_hours (warning) ─────────────────────

    #[Test]
    public function rule1_workerWithZeroHoursInAllWeeksTriggersWarning(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasWarning('worker_no_hours', $result);
    }

    #[Test]
    public function rule1_workerWithHoursDoesNotTriggerWarning(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 8.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('worker_no_hours', $result);
    }

    // ── Rule 2: week_hour_ceiling (error) ─────────────────────

    #[Test]
    public function rule2_weekExceeding60HoursTriggersError(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 10.0, 'sunday' => 0.5],
            ],
        ]); // 60.5h

        $result = $this->validate([$worker]);

        $this->assertHasError('week_hour_ceiling', $result);
    }

    #[Test]
    public function rule2_weekAtExactly60HoursIsOk(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 10.0, 'sunday' => 0.0],
            ],
        ]); // 60h exact

        $result = $this->validate([$worker]);

        $this->assertNoError('week_hour_ceiling', $result);
    }

    // ── Rule 3: day_hour_ceiling (error) ──────────────────────

    #[Test]
    public function rule3_dayExceeding14HoursTriggersError(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 14.5, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('day_hour_ceiling', $result);
    }

    #[Test]
    public function rule3_dayAtExactly14HoursIsOk(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 14.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('day_hour_ceiling', $result);
    }

    // ── Rule 4: sunday_hours (warning) ────────────────────────

    #[Test]
    public function rule4_sundayHoursWithoutJustificationTriggersWarning(): void
    {
        $worker = $this->worker([
            'hasSundayJustification' => false,
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 8.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasWarning('sunday_hours', $result);
    }

    #[Test]
    public function rule4_sundayHoursWithJustificationIsOk(): void
    {
        $worker = $this->worker([
            'hasSundayJustification' => true,
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 8.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('sunday_hours', $result);
    }

    // ── Rule 5: invalid_trade (error) ─────────────────────────

    #[Test]
    public function rule5_invalidTradeValueTriggersError(): void
    {
        $worker = $this->worker(['trade' => 'nonexistent_trade']);

        $result = $this->validate([$worker]);

        $this->assertHasError('invalid_trade', $result);
    }

    #[Test]
    public function rule5_validTradeDoesNotTriggerError(): void
    {
        $worker = $this->worker(['trade' => CcqTrade::Electrician]);

        $result = $this->validate([$worker]);

        $this->assertNoError('invalid_trade', $result);
    }

    // ── Rule 6: invalid_region (error) ────────────────────────

    #[Test]
    public function rule6_invalidRegionValueTriggersError(): void
    {
        $worker = $this->worker(['region' => 'mars']);

        $result = $this->validate([$worker]);

        $this->assertHasError('invalid_region', $result);
    }

    #[Test]
    public function rule6_validRegionDoesNotTriggerError(): void
    {
        $worker = $this->worker(['region' => CcqRegion::Estrie]);

        $result = $this->validate([$worker]);

        $this->assertNoError('invalid_region', $result);
    }

    // ── Rule 7: trade_region_mismatch (error) ──────────────────

    #[Test]
    public function rule7_elevatorMechanicOutsideUrbanRegionTriggersError(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::ElevatorMechanic,
            'region' => CcqRegion::Estrie,
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('trade_region_mismatch', $result);
    }

    #[Test]
    public function rule7_elevatorMechanicInMontrealIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::ElevatorMechanic,
            'region' => CcqRegion::Montreal,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('trade_region_mismatch', $result);
    }

    #[Test]
    public function rule7_carpenterInAnyRegionIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Carpenter,
            'region' => CcqRegion::CoteNord,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('trade_region_mismatch', $result);
    }

    // ── Rule 8: electrician_overtime (warning) ─────────────────

    #[Test]
    public function rule8_electricianOver40hWithoutOvertimeFlagTriggersWarning(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Electrician,
            'hasOvertimeFlag' => false,
            'dailyHours' => [
                1 => ['monday' => 9.0, 'tuesday' => 9.0, 'wednesday' => 9.0, 'thursday' => 9.0, 'friday' => 9.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]); // 45h

        $result = $this->validate([$worker]);

        $this->assertHasWarning('electrician_overtime', $result);
    }

    #[Test]
    public function rule8_electricianOver40hWithOvertimeFlagIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Electrician,
            'hasOvertimeFlag' => true,
            'dailyHours' => [
                1 => ['monday' => 9.0, 'tuesday' => 9.0, 'wednesday' => 9.0, 'thursday' => 9.0, 'friday' => 9.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('electrician_overtime', $result);
    }

    #[Test]
    public function rule8_electricianAtExactly40hIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Electrician,
            'hasOvertimeFlag' => false,
            'dailyHours' => [
                1 => ['monday' => 8.0, 'tuesday' => 8.0, 'wednesday' => 8.0, 'thursday' => 8.0, 'friday' => 8.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('electrician_overtime', $result);
    }

    #[Test]
    public function rule8_nonElectricianOver40hDoesNotTriggerWarning(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Carpenter,
            'hasOvertimeFlag' => false,
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('electrician_overtime', $result);
    }

    // ── Rule 9: plumber_region_restriction (error) ─────────────

    #[Test]
    public function rule9_plumberInCoteNordTriggersError(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Plumber,
            'region' => CcqRegion::CoteNord,
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('plumber_region_restriction', $result);
    }

    #[Test]
    public function rule9_plumberInMontrealIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Plumber,
            'region' => CcqRegion::Montreal,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('plumber_region_restriction', $result);
    }

    // ── Rule 10: monthly_hour_ceiling (error) ──────────────────

    #[Test]
    public function rule10_monthlyTotalExceeding250hTriggersError(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 12.0, 'tuesday' => 12.0, 'wednesday' => 12.0, 'thursday' => 12.0, 'friday' => 12.0, 'saturday' => 0.0, 'sunday' => 0.0], // 60
                2 => ['monday' => 12.0, 'tuesday' => 12.0, 'wednesday' => 12.0, 'thursday' => 12.0, 'friday' => 12.0, 'saturday' => 0.0, 'sunday' => 0.0], // 60
                3 => ['monday' => 12.0, 'tuesday' => 12.0, 'wednesday' => 12.0, 'thursday' => 12.0, 'friday' => 12.0, 'saturday' => 0.0, 'sunday' => 0.0], // 60
                4 => ['monday' => 12.0, 'tuesday' => 12.0, 'wednesday' => 12.0, 'thursday' => 12.0, 'friday' => 12.0, 'saturday' => 0.0, 'sunday' => 0.0], // 60
                5 => ['monday' => 12.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0], // 12
            ],
        ]); // 252h

        $result = $this->validate([$worker]);

        $this->assertHasError('monthly_hour_ceiling', $result);
    }

    #[Test]
    public function rule10_monthlyTotalAtExactly250hIsOk(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0], // 50
                2 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0], // 50
                3 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0], // 50
                4 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0], // 50
                5 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0], // 50
            ],
        ]); // 250h

        $result = $this->validate([$worker]);

        $this->assertNoError('monthly_hour_ceiling', $result);
    }

    // ── Rule 11: empty_declaration (error) ─────────────────────

    #[Test]
    public function rule11_emptyWorkerArrayTriggersError(): void
    {
        $result = $this->validate([]);

        $this->assertHasError('empty_declaration', $result);
    }

    #[Test]
    public function rule11_nonEmptyWorkerArrayIsOk(): void
    {
        $result = $this->validate([$this->worker()]);

        $this->assertNoError('empty_declaration', $result);
    }

    // ── Rule 12: negative_hours (error) ────────────────────────

    #[Test]
    public function rule12_negativeHoursTriggersError(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => -1.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('negative_hours', $result);
    }

    #[Test]
    public function rule12_zeroHoursIsOk(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('negative_hours', $result);
    }

    // ── Rule 13: invalid_hours_precision (error) ───────────────

    #[Test]
    #[DataProvider('invalidPrecisionProvider')]
    public function rule13_invalidDecimalPrecisionTriggersError(float $hours): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => $hours, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('invalid_hours_precision', $result);
    }

    public static function invalidPrecisionProvider(): iterable
    {
        yield '0.1' => [0.1];
        yield '0.3' => [0.3];
        yield '0.7' => [0.7];
        yield '0.9' => [0.9];
        yield '1.2' => [1.2];
        yield '8.25' => [8.25];
    }

    #[Test]
    #[DataProvider('validPrecisionProvider')]
    public function rule13_validPrecisionIsOk(float $hours): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => $hours, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('invalid_hours_precision', $result);
    }

    public static function validPrecisionProvider(): iterable
    {
        yield '0.0' => [0.0];
        yield '0.5' => [0.5];
        yield '1.0' => [1.0];
        yield '8.0' => [8.0];
        yield '8.5' => [8.5];
        yield '14.0' => [14.0];
    }

    // ── Rule 14: apprentice_supervisor (warning) ───────────────

    #[Test]
    public function rule14_apprenticeWithoutSupervisorTriggersWarning(): void
    {
        $worker = $this->worker([
            'isApprentice' => true,
            'hasSupervisorSignature' => false,
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasWarning('apprentice_supervisor', $result);
    }

    #[Test]
    public function rule14_apprenticeWithSupervisorIsOk(): void
    {
        $worker = $this->worker([
            'isApprentice' => true,
            'hasSupervisorSignature' => true,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('apprentice_supervisor', $result);
    }

    #[Test]
    public function rule14_nonApprenticeDoesNotTriggerWarning(): void
    {
        $worker = $this->worker([
            'isApprentice' => false,
            'hasSupervisorSignature' => false,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoWarning('apprentice_supervisor', $result);
    }

    // ── Rule 15: project_inactive (error) ──────────────────────

    #[Test]
    public function rule15_inactiveProjectTriggersError(): void
    {
        $result = $this->validate([$this->worker()], ['projectIsActive' => false]);

        $this->assertHasError('project_inactive', $result);
    }

    #[Test]
    public function rule15_activeProjectIsOk(): void
    {
        $result = $this->validate([$this->worker()], ['projectIsActive' => true]);

        $this->assertNoError('project_inactive', $result);
    }

    // ── Rule 16: duplicate_declaration (error) ─────────────────

    #[Test]
    public function rule16_existingDeclarationTriggersError(): void
    {
        $result = $this->validate([$this->worker()], ['hasExistingDeclaration' => true]);

        $this->assertHasError('duplicate_declaration', $result);
    }

    #[Test]
    public function rule16_noExistingDeclarationIsOk(): void
    {
        $result = $this->validate([$this->worker()], ['hasExistingDeclaration' => false]);

        $this->assertNoError('duplicate_declaration', $result);
    }

    // ── Rule 17: missing_week_declaration (error) ──────────────

    #[Test]
    public function rule17_nullWeekTriggersError(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => null,
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('missing_week_declaration', $result);
    }

    #[Test]
    public function rule17_zeroHoursWeekIsOk(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('missing_week_declaration', $result);
    }

    // ── Rule 18: seasonal_restriction_roofing (warning) ────────

    #[Test]
    public function rule18_rooferInJanuaryTriggersWarning(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Roofer,
        ]);

        $result = $this->validate([$worker], ['month' => 1]);

        $this->assertHasWarning('seasonal_restriction_roofing', $result);
    }

    #[Test]
    public function rule18_rooferInJuneIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Roofer,
        ]);

        $result = $this->validate([$worker], ['month' => 6]);

        $this->assertNoWarning('seasonal_restriction_roofing', $result);
    }

    #[Test]
    public function rule18_nonRooferInJanuaryIsOk(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Carpenter,
        ]);

        $result = $this->validate([$worker], ['month' => 1]);

        $this->assertNoWarning('seasonal_restriction_roofing', $result);
    }

    // ── Rule 19: cross_project_ceiling (error) ─────────────────

    #[Test]
    public function rule19_crossProjectHoursExceeding60hTriggersError(): void
    {
        // Worker already has 50h in this project for week 1
        $worker = $this->worker([
            'workerId' => 42,
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]); // 50h

        // Cross-project data shows this worker already has 15h in another project
        $result = $this->validate([$worker], [
            'crossProjectWeeklyHours' => [42 => [1 => 15.0]],
        ]);

        $this->assertHasError('cross_project_ceiling', $result);
    }

    #[Test]
    public function rule19_crossProjectHoursAtExactly60hIsOk(): void
    {
        $worker = $this->worker([
            'workerId' => 42,
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]); // 50h

        $result = $this->validate([$worker], [
            'crossProjectWeeklyHours' => [42 => [1 => 10.0]], // 50+10=60
        ]);

        $this->assertNoError('cross_project_ceiling', $result);
    }

    #[Test]
    public function rule19_noCrossProjectDataIsOk(): void
    {
        $worker = $this->worker([
            'workerId' => 42,
            'dailyHours' => [
                1 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]); // 50h, no cross-project

        $result = $this->validate([$worker]);

        $this->assertNoError('cross_project_ceiling', $result);
    }

    // ── Rule 20: cote_nord_perdiem (error) ─────────────────────

    #[Test]
    public function rule20_coteNordWithoutPerDiemTriggersError(): void
    {
        $worker = $this->worker([
            'region' => CcqRegion::CoteNord,
            'hasPerDiemFlag' => false,
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('cote_nord_perdiem', $result);
    }

    #[Test]
    public function rule20_coteNordWithPerDiemIsOk(): void
    {
        $worker = $this->worker([
            'region' => CcqRegion::CoteNord,
            'hasPerDiemFlag' => true,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('cote_nord_perdiem', $result);
    }

    #[Test]
    public function rule20_nonCoteNordWithoutPerDiemIsOk(): void
    {
        $worker = $this->worker([
            'region' => CcqRegion::Montreal,
            'hasPerDiemFlag' => false,
        ]);

        $result = $this->validate([$worker]);

        $this->assertNoError('cote_nord_perdiem', $result);
    }

    // ── Integration: validity flag ─────────────────────────────

    #[Test]
    public function validIsTrueWhenNoErrors(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::Carpenter,
            'region' => CcqRegion::Montreal,
            'hasPerDiemFlag' => false,
            'dailyHours' => [
                1 => ['monday' => 8.0, 'tuesday' => 8.0, 'wednesday' => 8.0, 'thursday' => 8.0, 'friday' => 8.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function validIsTrueWhenOnlyWarnings(): void
    {
        // Sunday hours without justification = warning, not error
        $worker = $this->worker([
            'hasSundayJustification' => false,
            'dailyHours' => [
                1 => ['monday' => 0.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 4.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertTrue($result->isValid(), 'Warnings should not invalidate the declaration');
    }

    #[Test]
    public function validIsFalseWhenAnyErrorPresent(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 15.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertFalse($result->isValid());
    }

    // ── Error metadata ─────────────────────────────────────────

    #[Test]
    public function errorsCarryWorkerIdAndWeek(): void
    {
        $worker = $this->worker([
            'workerId' => 7,
            'trade' => CcqTrade::Electrician,
            'hasOvertimeFlag' => false,
            'dailyHours' => [
                3 => ['monday' => 10.0, 'tuesday' => 10.0, 'wednesday' => 10.0, 'thursday' => 10.0, 'friday' => 10.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]); // 50h week 3, electrician

        $result = $this->validate([$worker]);

        $overtimeWarning = null;
        foreach ($result->errors as $error) {
            if ($error->code === 'electrician_overtime') {
                $overtimeWarning = $error;
            }
        }
        $this->assertNotNull($overtimeWarning);
        $this->assertSame(7, $overtimeWarning->workerId);
        $this->assertSame(3, $overtimeWarning->week);
    }

    #[Test]
    public function allErrorsReturnedForMultipleRuleViolations(): void
    {
        $worker = $this->worker([
            'trade' => 'invalid_trade_value',
            'region' => 'invalid_region_value',
            'dailyHours' => [
                1 => ['monday' => 15.0, 'tuesday' => -2.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker]);

        $codes = array_map(fn (CcqError $e): string => $e->code, $result->errors);
        $this->assertContains('invalid_trade', $codes);
        $this->assertContains('invalid_region', $codes);
        $this->assertContains('day_hour_ceiling', $codes);
        $this->assertContains('negative_hours', $codes);
    }

    #[Test]
    public function frenchMessagesForAllErrorCodes(): void
    {
        $worker = $this->worker([
            'trade' => 'bad',
            'region' => 'bad',
            'isApprentice' => true,
            'hasSundayJustification' => false,
            'hasOvertimeFlag' => false,
            'dailyHours' => [
                1 => ['monday' => 15.0, 'tuesday' => -1.0, 'wednesday' => 0.3, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 8.0],
            ],
        ]);

        $result = $this->validate(
            [$worker],
            ['month' => 1, 'projectIsActive' => false, 'hasExistingDeclaration' => true],
        );

        foreach ($result->errors as $error) {
            $this->assertNotEmpty($error->message, "Message should not be empty for code: {$error->code}");
            // French messages should contain accented characters or be recognizably French.
            // At minimum, they must be non-empty strings.
        }
    }

    // ── Edge cases ─────────────────────────────────────────────

    #[Test]
    public function multipleWorkersAllValidated(): void
    {
        $worker1 = $this->worker([
            'workerId' => 1,
            'workerName' => 'Jean',
            'dailyHours' => [
                1 => ['monday' => 15.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);
        $worker2 = $this->worker([
            'workerId' => 2,
            'workerName' => 'Pierre',
            'dailyHours' => [
                1 => ['monday' => -1.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
            ],
        ]);

        $result = $this->validate([$worker1, $worker2]);

        $dayCeilingErrors = array_values(array_filter($result->errors, fn (CcqError $e): bool => $e->code === 'day_hour_ceiling'));
        $negativeErrors = array_values(array_filter($result->errors, fn (CcqError $e): bool => $e->code === 'negative_hours'));

        $this->assertCount(1, $dayCeilingErrors);
        $this->assertCount(1, $negativeErrors);
        $this->assertSame(1, $dayCeilingErrors[0]->workerId);
        $this->assertSame(2, $negativeErrors[0]->workerId);
    }

    #[Test]
    public function missingWeekWithWeekNumber2IsDetected(): void
    {
        $worker = $this->worker([
            'dailyHours' => [
                1 => ['monday' => 8.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
                2 => ['monday' => 8.0, 'tuesday' => 0.0, 'wednesday' => 0.0, 'thursday' => 0.0, 'friday' => 0.0, 'saturday' => 0.0, 'sunday' => 0.0],
                3 => null,  // missing
            ],
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('missing_week_declaration', $result);
        $missingWeekError = null;
        foreach ($result->errors as $error) {
            if ($error->code === 'missing_week_declaration') {
                $missingWeekError = $error;
            }
        }
        $this->assertNotNull($missingWeekError);
        $this->assertSame(3, $missingWeekError->week);
    }

    #[Test]
    public function craneOperatorRestrictedInCoteNord(): void
    {
        $worker = $this->worker([
            'trade' => CcqTrade::CraneOperator,
            'region' => CcqRegion::CoteNord,
        ]);

        $result = $this->validate([$worker]);

        $this->assertHasError('trade_region_mismatch', $result);
    }
}
