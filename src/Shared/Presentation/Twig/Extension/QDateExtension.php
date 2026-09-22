<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig\Extension;

use App\Shared\Presentation\Twig\Runtime\QDateRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Quebec-specific presentation helpers.
 *
 * `qdate` renders dates in the DD/MM/YYYY format used across the Quebec
 * construction industry (specs/12-ux-ui.md).
 */
final class QDateExtension extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('qdate', [QDateRuntime::class, 'format']),
        ];
    }
}
