<?php

declare(strict_types=1);

namespace App\Tests\Commercial\Unit\Application;

use App\Commercial\Application\InvoiceTaxCalculator;
use PHPUnit\Framework\TestCase;

final class InvoiceTaxCalculatorTest extends TestCase
{
    public function test_calculates_quebec_tps_tvq_and_total_from_subtotal(): void
    {
        $taxes = (new InvoiceTaxCalculator())->calculate('9375.00');

        self::assertSame('9375.00', $taxes['subtotal']);
        self::assertSame('468.75', $taxes['tps']);
        self::assertSame('935.16', $taxes['tvq']);
        self::assertSame('10778.91', $taxes['total']);
    }
}
