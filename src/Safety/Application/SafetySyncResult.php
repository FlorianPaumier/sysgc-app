<?php

declare(strict_types=1);

namespace App\Safety\Application;

use App\Safety\Domain\Model\SafetyLog;

final readonly class SafetySyncResult
{
    /** @param list<string> $conflicts */
    public function __construct(public SafetyLog $log, public array $conflicts = [], public bool $duplicate = false)
    {
    }
}
