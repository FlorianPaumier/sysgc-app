<?php

declare(strict_types=1);

namespace App\Factory;

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
            // RBQ numbers are formatted like "RBQ-1234-5678-90".
            'rbqLicense' => 'RBQ-' . self::faker()->numerify('####-####-##'),
            'address' => AddressFactory::new(),
        ];
    }
}
