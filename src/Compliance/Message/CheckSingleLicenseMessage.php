<?php

declare(strict_types=1);

namespace App\Compliance\Message;

final readonly class CheckSingleLicenseMessage
{
    public function __construct(public string $subcontractorId) {}
}
