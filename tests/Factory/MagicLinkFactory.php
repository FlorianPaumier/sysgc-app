<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\MagicLink;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MagicLink>
 */
final class MagicLinkFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return MagicLink::class;
    }

    /**
     * @return array<string, mixed>|callable
     */
    #[\Override]
    protected function defaults(): array | callable
    {
        return [
            'tokenHash' => hash('sha256', 'test-token'),
            'magicLinkTokenExpiresAt' => self::faker()->dateTimeInTimeInterval('+15 minutes'),
            'owner' => UserFactory::new(),
        ];
    }
}
