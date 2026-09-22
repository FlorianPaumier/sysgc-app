<?php

declare(strict_types=1);

namespace App\Compliance\Domain\ValueObject;

/**
 * A single CCQ validation error or warning.
 */
readonly class CcqError
{
    public function __construct(
        public string $code,
        public string $message,
        public bool $isWarning,
        public ?int $workerId = null,
        public ?int $week = null,
    ) {}

    public function isError(): bool
    {
        return ! $this->isWarning;
    }
}
