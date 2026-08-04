<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Organization;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Organization>
 */
final class OrganizationFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Organization::class;
    }

    /**
     * @return array<string, mixed>|callable
     */
    #[\Override]
    protected function defaults(): array | callable
    {
        return [
            'name' => self::faker()->company(),
            'rbqLicense' => 'RBQ-' . self::faker()->numerify('####-####-##'),
        ];
    }
}
