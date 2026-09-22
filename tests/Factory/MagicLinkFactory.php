<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Identity\Domain\Model\MagicLink;
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
            // Keep generated links valid for the same 15-minute window without
            // relying on Faker providers that are not available in all versions.
            'magicLinkTokenExpiresAt' => new \DateTimeImmutable('+15 minutes'),
            'owner' => UserFactory::new(),
        ];
    }
}
