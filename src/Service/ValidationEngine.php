<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CcqRegion;
use App\Enum\CcqTrade;
use App\Service\ValueObject\CcqError;
use App\Service\ValueObject\CcqValidationResult;

/**
 * Validates CCQ monthly declaration data against 20+ business rules.
 *
 * Pure service — no Doctrine writes. Receives all context from the caller.
 *
 * @see specs/domain-ccq.md
 *
 * @phpstan-type WorkerEntry array{
 *     workerId: ?int,
 *     workerName: string,
 *     trade: CcqTrade|string|null,
 *     region: CcqRegion|string|null,
 *     isApprentice: bool,
 *     hasSupervisorSignature: bool,
 *     hasOvertimeFlag: bool,
 *     hasPerDiemFlag: bool,
 *     hasSundayJustification: bool,
 *     dailyHours: array<int, array{monday: float, tuesday: float, wednesday: float, thursday: float, friday: float, saturday: float, sunday: float}|null>,
 * }
 */
final readonly class ValidationEngine
{
    private const array DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const float MAX_DAY_HOURS = 14.0;
    private const float MAX_WEEK_HOURS = 60.0;
    private const float MAX_MONTHLY_HOURS = 250.0;
    private const float ELECTRICIAN_OVERTIME_THRESHOLD = 40.0;

    /**
     * @param WorkerEntry[]                                                  $hoursData
     * @param array<int, array<int, float>>                                  $crossProjectWeeklyHours workerId => [weekNum => hours] from other projects (rule 19)
     */
    public function validate(
        array $hoursData,
        int $month,
        int $year,
        bool $projectIsActive = true,
        bool $hasExistingDeclaration = false,
        array $crossProjectWeeklyHours = [],
    ): CcqValidationResult {
        $errors = [];

        // Rule 11: empty_declaration
        if (count($hoursData) === 0) {
            $errors[] = new CcqError(
                code: 'empty_declaration',
                message: 'Au moins un travailleur doit être déclaré.',
                isWarning: false,
            );

            return new CcqValidationResult($errors);
        }

        // Rule 15: project_inactive
        if (! $projectIsActive) {
            $errors[] = new CcqError(
                code: 'project_inactive',
                message: 'Le projet n\'est pas actif. Les déclarations CCQ ne peuvent être soumises que pour des projets actifs.',
                isWarning: false,
            );
        }

        // Rule 16: duplicate_declaration
        if ($hasExistingDeclaration) {
            $errors[] = new CcqError(
                code: 'duplicate_declaration',
                message: 'Une déclaration CCQ a déjà été soumise pour ce mois. Une seule déclaration par mois est autorisée.',
                isWarning: false,
            );
        }

        foreach ($hoursData as $worker) {
            /** @var WorkerEntry $worker */
            $workerId = $worker['workerId'];

            // Rule 5: invalid_trade
            $trade = $this->resolveTrade($worker['trade'] ?? null);
            if ($trade === null) {
                $errors[] = new CcqError(
                    code: 'invalid_trade',
                    message: 'Le métier déclaré n\'est pas un métier CCQ reconnu.',
                    isWarning: false,
                    workerId: $workerId,
                );
            }

            // Rule 6: invalid_region
            $region = $this->resolveRegion($worker['region'] ?? null);
            if ($region === null) {
                $errors[] = new CcqError(
                    code: 'invalid_region',
                    message: 'La région déclarée n\'est pas une région CCQ reconnue.',
                    isWarning: false,
                    workerId: $workerId,
                );
            }

            // Rules 7 & 9: trade_region_mismatch & plumber_region_restriction
            if ($trade !== null && $region !== null) {
                if ($trade->isRestrictedInRegion($region)) {
                    if ($trade === CcqTrade::Plumber) {
                        $errors[] = new CcqError(
                            code: 'plumber_region_restriction',
                            message: sprintf(
                                'Les plombiers dans la région %s doivent détenir une certification supplémentaire.',
                                $region->label(),
                            ),
                            isWarning: false,
                            workerId: $workerId,
                        );
                    } else {
                        $errors[] = new CcqError(
                            code: 'trade_region_mismatch',
                            message: sprintf(
                                'Le métier « %s » n\'est pas autorisé dans la région « %s ».',
                                $trade->label(),
                                $region->label(),
                            ),
                            isWarning: false,
                            workerId: $workerId,
                        );
                    }
                }
            }

            // Rule 18: seasonal_restriction_roofing
            if ($trade !== null && $trade->isRestrictedInMonth($month)) {
                $errors[] = new CcqError(
                    code: 'seasonal_restriction_roofing',
                    message: 'Le travail de couvreur est interdit en janvier en raison des restrictions saisonnières de la CCQ.',
                    isWarning: true,
                    workerId: $workerId,
                );
            }

            // Rule 20: cote_nord_perdiem
            if ($region !== null && $region->requiresPerDiem() && ! $worker['hasPerDiemFlag']) {
                $errors[] = new CcqError(
                    code: 'cote_nord_perdiem',
                    message: 'Le travail dans la région Côte-Nord nécessite une déclaration de frais de déplacement (per diem).',
                    isWarning: false,
                    workerId: $workerId,
                );
            }

            // Rule 14: apprentice_supervisor
            if ($worker['isApprentice'] && ! $worker['hasSupervisorSignature']) {
                $errors[] = new CcqError(
                    code: 'apprentice_supervisor',
                    message: 'L\'apprenti doit avoir la signature d\'un compagnon superviseur.',
                    isWarning: true,
                    workerId: $workerId,
                );
            }

            // Per-week and per-day validations
            $dailyHours = $worker['dailyHours'];
            $workerHasAnyHours = false;
            $monthlyTotal = 0.0;

            foreach ($dailyHours as $weekNum => $days) {
                $weekNum = (int) $weekNum;

                // Rule 17: missing_week_declaration
                if ($days === null) {
                    $errors[] = new CcqError(
                        code: 'missing_week_declaration',
                        message: sprintf(
                            'La semaine %d doit être déclarée explicitement (mettre 0 si aucune heure).',
                            $weekNum,
                        ),
                        isWarning: false,
                        workerId: $workerId,
                        week: $weekNum,
                    );
                    continue;
                }

                $weekTotal = 0.0;

                foreach (self::DAYS as $day) {
                    $hours = (float) $days[$day];

                    // Rule 12: negative_hours
                    if ($hours < 0.0) {
                        $errors[] = new CcqError(
                            code: 'negative_hours',
                            message: sprintf(
                                'Les heures ne peuvent pas être négatives (semaine %d, %s).',
                                $weekNum,
                                $day,
                            ),
                            isWarning: false,
                            workerId: $workerId,
                            week: $weekNum,
                        );
                        continue;
                    }

                    // Rule 13: invalid_hours_precision
                    if ($hours !== 0.0 && ! $this->isValidPrecision($hours)) {
                        $errors[] = new CcqError(
                            code: 'invalid_hours_precision',
                            message: sprintf(
                                'Les heures doivent être en multiples de 0,5 (semaine %d, %s : %.1f).',
                                $weekNum,
                                $day,
                                $hours,
                            ),
                            isWarning: false,
                            workerId: $workerId,
                            week: $weekNum,
                        );
                    }

                    // Rule 3: day_hour_ceiling
                    if ($hours > self::MAX_DAY_HOURS) {
                        $errors[] = new CcqError(
                            code: 'day_hour_ceiling',
                            message: sprintf(
                                'Un jour ne peut pas dépasser %.0f heures (semaine %d, %s : %.1f h).',
                                self::MAX_DAY_HOURS,
                                $weekNum,
                                $day,
                                $hours,
                            ),
                            isWarning: false,
                            workerId: $workerId,
                            week: $weekNum,
                        );
                    }

                    if ($hours > 0.0) {
                        $workerHasAnyHours = true;
                    }

                    $weekTotal += $hours;
                }

                $monthlyTotal += $weekTotal;

                // Rule 4: sunday_hours
                $sundayHours = (float) $days['sunday'];
                if ($sundayHours > 0.0 && ! $worker['hasSundayJustification']) {
                    $errors[] = new CcqError(
                        code: 'sunday_hours',
                        message: sprintf(
                            'Des heures sont déclarées le dimanche (semaine %d). Une justification est requise.',
                            $weekNum,
                        ),
                        isWarning: true,
                        workerId: $workerId,
                        week: $weekNum,
                    );
                }

                // Rule 2: week_hour_ceiling
                if ($weekTotal > self::MAX_WEEK_HOURS) {
                    $errors[] = new CcqError(
                        code: 'week_hour_ceiling',
                        message: sprintf(
                            'La semaine %d totalise %.1f h, ce qui dépasse le plafond de %.0f h par semaine.',
                            $weekNum,
                            $weekTotal,
                            self::MAX_WEEK_HOURS,
                        ),
                        isWarning: false,
                        workerId: $workerId,
                        week: $weekNum,
                    );
                }

                // Rule 8: electrician_overtime
                if (
                    $trade === CcqTrade::Electrician
                    && $weekTotal > self::ELECTRICIAN_OVERTIME_THRESHOLD
                    && ! $worker['hasOvertimeFlag']
                ) {
                    $errors[] = new CcqError(
                        code: 'electrician_overtime',
                        message: sprintf(
                            'L\'électricien dépasse %.0f h (semaine %d : %.1f h). Le drapeau d\'heures supplémentaires doit être activé.',
                            self::ELECTRICIAN_OVERTIME_THRESHOLD,
                            $weekNum,
                            $weekTotal,
                        ),
                        isWarning: true,
                        workerId: $workerId,
                        week: $weekNum,
                    );
                }

                // Rule 19: cross_project_ceiling
                if ($workerId !== null && ($crossProjectWeeklyHours[$workerId][$weekNum] ?? null) !== null) {
                    $crossHours = (float) $crossProjectWeeklyHours[$workerId][$weekNum];
                    $combinedTotal = $weekTotal + $crossHours;
                    if ($combinedTotal > self::MAX_WEEK_HOURS) {
                        $errors[] = new CcqError(
                            code: 'cross_project_ceiling',
                            message: sprintf(
                                'Le total des heures tous projets confondus dépasse %.0f h pour la semaine %d (%.1f h + %.1f h d\'autres projets).',
                                self::MAX_WEEK_HOURS,
                                $weekNum,
                                $weekTotal,
                                $crossHours,
                            ),
                            isWarning: false,
                            workerId: $workerId,
                            week: $weekNum,
                        );
                    }
                }
            }

            // Rule 1: worker_no_hours
            if (! $workerHasAnyHours) {
                $errors[] = new CcqError(
                    code: 'worker_no_hours',
                    message: sprintf(
                        '%s n\'a aucune heure déclarée pour ce mois.',
                        $worker['workerName'],
                    ),
                    isWarning: true,
                    workerId: $workerId,
                );
            }

            // Rule 10: monthly_hour_ceiling
            if ($monthlyTotal > self::MAX_MONTHLY_HOURS) {
                $errors[] = new CcqError(
                    code: 'monthly_hour_ceiling',
                    message: sprintf(
                        'Le total mensuel de %.1f h dépasse le plafond de %.0f h par mois.',
                        $monthlyTotal,
                        self::MAX_MONTHLY_HOURS,
                    ),
                    isWarning: false,
                    workerId: $workerId,
                );
            }
        }

        return new CcqValidationResult($errors);
    }

    private function resolveTrade(mixed $trade): ?CcqTrade
    {
        if ($trade instanceof CcqTrade) {
            return $trade;
        }

        if (is_string($trade)) {
            return CcqTrade::tryFrom($trade);
        }

        return null;
    }

    private function resolveRegion(mixed $region): ?CcqRegion
    {
        if ($region instanceof CcqRegion) {
            return $region;
        }

        if (is_string($region)) {
            return CcqRegion::tryFrom($region);
        }

        return null;
    }

    /**
     * CCQ only allows hours in .0 or .5 increments.
     */
    private function isValidPrecision(float $hours): bool
    {
        $doubled = $hours * 2.0;

        return abs($doubled - round($doubled)) < 0.001;
    }
}
