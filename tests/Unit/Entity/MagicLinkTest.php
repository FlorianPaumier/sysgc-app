<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Identity\Domain\Model\MagicLink;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MagicLink entity.
 * Tests token validation logic without database.
 */
final class MagicLinkTest extends TestCase
{
    public function test_isValid_returns_true_when_token_not_expired(): void
    {
        $magicLink = new MagicLink();
        $futureExpiry = new \DateTimeImmutable('+15 minutes');

        $magicLink->setTokenHash(hash('sha256', 'valid-token'));
        $magicLink->setMagicLinkTokenExpiresAt($futureExpiry);

        $this->assertTrue($magicLink->isValid(new \DateTimeImmutable()));
    }

    public function test_isValid_returns_false_when_token_expired(): void
    {
        $magicLink = new MagicLink();
        $pastExpiry = new \DateTimeImmutable('-15 minutes');

        $magicLink->setTokenHash(hash('sha256', 'expired-token'));
        $magicLink->setMagicLinkTokenExpiresAt($pastExpiry);

        $this->assertFalse($magicLink->isValid(new \DateTimeImmutable()));
    }

    public function test_isValid_returns_false_when_expiry_null(): void
    {
        $magicLink = new MagicLink();
        $magicLink->setTokenHash(hash('sha256', 'no-expiry-token'));

        $this->assertFalse($magicLink->isValid(new \DateTimeImmutable()));
    }

    public function test_tokenHash_is_exactly_64_chars_sha256_output(): void
    {
        $rawToken = bin2hex(random_bytes(64));
        $expectedHash = hash('sha256', $rawToken);

        $this->assertNotEmpty($expectedHash);
        $this->assertLength(64, $expectedHash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $expectedHash);
    }

    public function test_isValid_boundary_case_expires_in_1_second(): void
    {
        $magicLink = new MagicLink();
        $futureExpiry = new \DateTimeImmutable('+1 second');

        $magicLink->setTokenHash(hash('sha256', 'boundary-token'));
        $magicLink->setMagicLinkTokenExpiresAt($futureExpiry);

        $this->assertTrue($magicLink->isValid(new \DateTimeImmutable()));
    }

    public function test_isValid_boundary_case_expired_1_second_ago(): void
    {
        $magicLink = new MagicLink();
        $pastExpiry = new \DateTimeImmutable('-1 second');

        $magicLink->setTokenHash(hash('sha256', 'expired-boundary-token'));
        $magicLink->setMagicLinkTokenExpiresAt($pastExpiry);

        $this->assertFalse($magicLink->isValid(new \DateTimeImmutable()));
    }

    public function test_magicLinkTokenExpiresAt_accepts_datetimeImmutable(): void
    {
        $magicLink = new MagicLink();
        $expiry = new \DateTimeImmutable('+15 minutes');

        $magicLink->setMagicLinkTokenExpiresAt($expiry);

        $this->assertSame($expiry, $magicLink->getMagicLinkTokenExpiresAt());
    }

    /**
     * Helper: assert string length.
     */
    private function assertLength(int $expected, string $actual): void
    {
        $this->assertEquals($expected, \strlen($actual));
    }
}
