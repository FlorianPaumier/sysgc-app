<?php

declare(strict_types=1);

namespace App\Identity\Domain\Enum;

enum UserRole: string
{
    case OWNER = 'ROLE_OWNER';
    case ADMIN = 'ROLE_ADMIN';
    case FOREMAN = 'ROLE_FOREMAN';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur',
            self::FOREMAN => 'Contremaître',
        };
    }
}
