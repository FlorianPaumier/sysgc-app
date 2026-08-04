<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Organization;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_email_is_mutable(): void
    {
        $user = new User();

        self::assertSame('owner@example.com', $user->setEmail('owner@example.com')->getEmail());
    }

    public function test_user_identifier_is_email(): void
    {
        $user = (new User())->setEmail('owner@example.com');

        self::assertSame('owner@example.com', $user->getUserIdentifier());
    }

    public function test_roles_defaults_to_role_user_only(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function test_roles_always_includes_role_user(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function test_roles_are_deduplicated(): void
    {
        $user = (new User())->setRoles(['ROLE_USER', 'ROLE_USER']);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function test_organization_is_mutable(): void
    {
        $user = new User();
        $organization = new Organization();

        self::assertNull($user->getOrganization());
        self::assertSame($organization, $user->setOrganization($organization)->getOrganization());
    }

    public function test_is_verified_defaults_to_false(): void
    {
        $user = new User();

        self::assertFalse($user->isVerified());
        self::assertTrue($user->setIsVerified(true)->isVerified());
    }
}
