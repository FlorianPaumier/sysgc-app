<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ProjectStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';
}
