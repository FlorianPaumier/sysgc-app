<?php

declare(strict_types=1);

namespace App\Safety\Domain\Enum;

enum SafetyLogStatus: string
{
    case DRAFT = 'draft';
    case COMPLETED = 'completed';
    case AMENDED = 'amended';
}
