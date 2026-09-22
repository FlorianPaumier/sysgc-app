<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Application\Membership\AcceptInvitation;
use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Invitation;
use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Model\Project;
use PHPUnit\Framework\TestCase;

final class AcceptInvitationTest extends TestCase
{
    public function test_acceptance_applies_organization_role_and_project_assignments(): void
    {
        $organization = new Organization();
        $project = new Project($organization, '100 rue Test', 'Client');
        $invitation = new Invitation($organization, 'Foreman@Example.com', UserRole::FOREMAN, 'stored-hash');
        $invitation->assignProject($project);
        $user = (new User())->setEmail('foreman@example.com');

        (new AcceptInvitation())->accept($invitation, $user, 'stored-hash', new \DateTimeImmutable('+1 day'));

        self::assertSame($organization, $user->getOrganization());
        self::assertTrue($user->isForeman());
        self::assertTrue($user->getAssignedProjects()->contains($project));
        self::assertTrue($invitation->isAccepted());
    }

    public function test_acceptance_rejects_a_different_email(): void
    {
        $invitation = new Invitation(new Organization(), 'expected@example.com', UserRole::ADMIN, 'stored-hash');
        $user = (new User())->setEmail('attacker@example.com');

        $this->expectException(\DomainException::class);
        (new AcceptInvitation())->accept($invitation, $user, 'stored-hash', new \DateTimeImmutable('+1 day'));
    }

    public function test_acceptance_cannot_move_an_existing_user_between_organizations(): void
    {
        $invitation = new Invitation(new Organization(), 'member@example.com', UserRole::ADMIN, 'stored-hash');
        $user = (new User())->setEmail('member@example.com')->setOrganization(new Organization());

        $this->expectException(\DomainException::class);
        (new AcceptInvitation())->accept($invitation, $user, 'stored-hash', new \DateTimeImmutable('+1 day'));
    }
}
