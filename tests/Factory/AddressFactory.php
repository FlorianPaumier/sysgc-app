<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Address;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Address>
 */
final class AddressFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Address::class;
    }

    /**
     * @return array<string, mixed>|callable
     */
    #[\Override]
    protected function defaults(): array | callable
    {
        return [
            'street' => self::faker()->streetAddress(),
            'street2' => null,
            'postalCode' => self::faker()->postcode(),
            'city' => self::faker()->city(),
            'state' => null,
            'country' => 'CA',
        ];
    }
}
