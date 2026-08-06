<?php

declare(strict_types=1);

namespace App\Enum;

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
}
