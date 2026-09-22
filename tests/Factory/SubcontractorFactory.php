<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Compliance\Domain\Model\Subcontractor;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Subcontractor>
 */
final class SubcontractorFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Subcontractor::class;
    }

    /** @return array<string, mixed>|callable */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->company(),
            'rbqLicenseNumber' => self::faker()->optional()->regexify('RBQ-[0-9]{4}-[0-9]{4}-[0-9]{2}'),
            'isVerified' => false,
            'lastVerifiedAt' => null,
        ];
    }

}
