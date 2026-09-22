<?php

declare(strict_types=1);

namespace App\Compliance\Message;

final readonly class BatchRbqCheckMessage
{
    /** @param list<string> $licenseNumbers */
    public function __construct(
        public string $projectId,
        public array $licenseNumbers,
    ) {}
}
