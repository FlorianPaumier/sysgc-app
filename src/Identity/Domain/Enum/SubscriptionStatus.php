<?php

declare(strict_types=1);

namespace App\Identity\Domain\Enum;

enum SubscriptionStatus: string
{
    case NoSubscription = 'no_subscription';
    case Active = 'active';
    case Grace = 'grace';
    case ReadOnly = 'read_only';
    case EnterpriseRequired = 'enterprise_required';

    public function label(): string
    {
        return match ($this) {
            self::NoSubscription => 'Aucun abonnement',
            self::Active => 'Actif',
            self::Grace => 'Période de grâce',
            self::ReadOnly => 'Lecture seule',
            self::EnterpriseRequired => 'Enterprise requis',
        };
    }
}
