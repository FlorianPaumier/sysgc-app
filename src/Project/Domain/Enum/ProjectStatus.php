<?php

declare(strict_types=1);

namespace App\Project\Domain\Enum;

enum ProjectStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';
}
