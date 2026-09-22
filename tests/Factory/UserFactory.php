<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Identity\Domain\Model\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>|callable
     */
    #[\Override]
    protected function defaults(): array | callable
    {
        return [
            'email' => self::faker()->unique()->email(),
            'roles' => [],
            'isVerified' => false,
            'organization' => OrganizationFactory::new(),
        ];
    }
}
