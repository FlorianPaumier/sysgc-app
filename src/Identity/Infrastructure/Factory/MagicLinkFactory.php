<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Factory;

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
    protected function defaults(): array|callable
    {
        return [
            // SHA-256 of 'test-token' by default — override in tests.
            'tokenHash' => hash('sha256', 'test-token'),
            // Valid 15 minutes from now by default.
            'magicLinkTokenExpiresAt' => self::faker()->dateTimeBetween('now', '+15 minutes'),
            'owner' => UserFactory::new(),
        ];
    }
}
