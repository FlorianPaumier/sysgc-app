<?php

declare(strict_types=1);

namespace App\Commercial\Application;

final readonly class InvoiceMilestoneCalculator
{
    public function amountFor(string $budget, int $percentage): string
    {
        if ($percentage < 1 || $percentage > 100) {
            throw new \InvalidArgumentException('Le pourcentage doit être entre 1 et 100.');
        }

        $budgetCents = (int) round((float) $budget * 100, 0, PHP_ROUND_HALF_UP);

        return number_format(round($budgetCents * $percentage / 100, 0, PHP_ROUND_HALF_UP) / 100, 2, '.', '');
    }
}
