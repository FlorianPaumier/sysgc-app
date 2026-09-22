<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Invitation;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use PHPUnit\Framework\TestCase;

final class InvitationTest extends TestCase
{
    public function test_invitation_is_bound_to_one_organization_and_allowed_role(): void
    {
        $organization = new Organization();
        $invitation = new Invitation($organization, 'foreman@example.com', UserRole::FOREMAN, 'hash');

        self::assertSame($organization, $invitation->getOrganization());
        self::assertSame('foreman@example.com', $invitation->getEmail());
        self::assertSame('Contremaître', $invitation->getRoleLabel());
        self::assertFalse($invitation->isExpired(new \DateTimeImmutable('+6 days')));
        self::assertTrue($invitation->isExpired(new \DateTimeImmutable('+8 days')));
    }

    public function test_owner_role_cannot_be_invited(): void
    {
        $this->expectException(\DomainException::class);
        new Invitation(new Organization(), 'owner@example.com', UserRole::OWNER, 'hash');
    }

    public function test_blank_email_cannot_be_invited(): void
    {
        $this->expectException(\DomainException::class);
        new Invitation(new Organization(), '  ', UserRole::ADMIN, 'hash');
    }

    public function test_foreman_project_assignment_must_belong_to_invitation_organization(): void
    {
        $invitation = new Invitation(new Organization(), 'foreman@example.com', UserRole::FOREMAN, 'hash');

        $this->expectException(\DomainException::class);
        $invitation->assignProject(new Project(new Organization(), '200 rue Autre', 'Client'));
    }

    public function test_invitation_is_single_use_and_revocable(): void
    {
        $invitation = new Invitation(new Organization(), 'admin@example.com', UserRole::ADMIN, 'hash');
        self::assertTrue($invitation->canBeAccepted('hash', new \DateTimeImmutable('+1 day')));

        $invitation->accept();

        self::assertFalse($invitation->canBeAccepted('hash', new \DateTimeImmutable('+1 day')));
        $this->expectException(\DomainException::class);
        $invitation->accept();
    }
}
