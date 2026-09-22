<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Model\Project;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_role_has_french_membership_label(): void
    {
        self::assertSame('Propriétaire', (new User())->setRoles([UserRole::OWNER->value])->getRoleLabel());
        self::assertSame('Administrateur', (new User())->setRoles([UserRole::ADMIN->value])->getRoleLabel());
        self::assertSame('Contremaître', (new User())->setRoles([UserRole::FOREMAN->value])->getRoleLabel());
    }

    public function test_only_foremen_can_be_assigned_to_projects_in_their_organization(): void
    {
        $organization = new Organization();
        $project = new Project($organization, '100 rue Test', 'Client');
        $admin = (new User())->setRoles([UserRole::ADMIN->value])->setOrganization($organization);

        $this->expectException(\DomainException::class);
        $admin->assignProject($project);
    }

    public function test_foreman_cannot_be_assigned_to_project_from_another_organization(): void
    {
        $foreman = (new User())->setRoles([UserRole::FOREMAN->value])->setOrganization(new Organization());
        $project = new Project(new Organization(), '100 rue Test', 'Client');

        $this->expectException(\DomainException::class);
        $foreman->assignProject($project);
    }
    public function test_email_is_mutable(): void
    {
        $user = new User();

        self::assertSame('owner@example.com', $user->setEmail('owner@example.com')->getEmail());
    }

    public function test_user_identifier_is_email(): void
    {
        $user = new User()->setEmail('owner@example.com');

        self::assertSame('owner@example.com', $user->getUserIdentifier());
    }

    public function test_roles_defaults_to_role_user_only(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function test_roles_always_includes_role_user(): void
    {
        $user = new User()->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function test_roles_are_deduplicated(): void
    {
        $user = new User()->setRoles(['ROLE_USER', 'ROLE_USER']);

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
