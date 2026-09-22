<?php

declare(strict_types=1);

namespace App\Identity\Domain\Enum;

enum SubscriptionTier: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Business => 'Business',
        };
    }

    public function workerLimit(): int
    {
        return match ($this) {
            self::Starter => 5,
            self::Pro => 20,
            self::Business => 50,
        };
    }

    public function userLimit(): int
    {
        return match ($this) {
            self::Starter => 2,
            self::Pro => 5,
            self::Business => 10,
        };
    }

    /**
     * Monthly price in Canadian dollars (CAD).
     */
    public function monthlyPrice(): int
    {
        return match ($this) {
            self::Starter => 79,
            self::Pro => 149,
            self::Business => 199,
        };
    }
}
