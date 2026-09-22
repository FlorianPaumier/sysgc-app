<?php

declare(strict_types=1);

namespace App\Commercial\Domain\Enum;

enum ChangeOrderStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case INVOICED = 'invoiced';
}
