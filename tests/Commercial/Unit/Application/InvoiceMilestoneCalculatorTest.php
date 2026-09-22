<?php

declare(strict_types=1);

namespace App\Tests\Commercial\Unit\Application;

use App\Commercial\Application\InvoiceMilestoneCalculator;
use PHPUnit\Framework\TestCase;

final class InvoiceMilestoneCalculatorTest extends TestCase
{
    public function test_calculates_milestone_amount_from_project_budget_and_percentage(): void
    {
        $calculator = new InvoiceMilestoneCalculator();

        self::assertSame('11250.00', $calculator->amountFor('45000.00', 25));
    }

    public function test_rejects_percentages_outside_one_to_one_hundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new InvoiceMilestoneCalculator())->amountFor('45000.00', 101);
    }
}
