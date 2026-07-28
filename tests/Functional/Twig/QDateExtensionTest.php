<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The Quebec construction standard date format is DD/MM/YYYY (specs/12-ux-ui.md).
 * This proves the `qdate` Twig filter is registered and formats accordingly.
 */
final class QDateExtensionTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function datesProvider(): iterable
    {
        yield 'ISO date' => ['2026-07-28', '28/07/2026'];
        yield 'start of year' => ['2026-01-01', '01/01/2026'];
        yield 'end of year' => ['2026-12-31', '31/12/2026'];
        yield 'mid month' => ['2025-03-09', '09/03/2025'];
    }

    #[DataProvider('datesProvider')]
    public function testQDateFormatsAsDayMonthYear(string $input, string $expected): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $rendered = $twig->createTemplate('{{ date|qdate }}')->render(['date' => $input]);

        self::assertSame($expected, $rendered);
    }
}
