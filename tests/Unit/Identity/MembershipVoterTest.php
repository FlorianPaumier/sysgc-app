<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Security\MembershipVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MembershipVoterTest extends TestCase
{
    public function test_owner_and_admin_can_manage_members_in_their_organization(): void
    {
        $organization = new Organization();
        foreach ([UserRole::OWNER, UserRole::ADMIN] as $role) {
            $actor = (new User())->setEmail('actor@example.com')->setOrganization($organization)->setRoles([$role->value]);
            self::assertSame(Voter::ACCESS_GRANTED, $this->vote($actor, MembershipVoter::MANAGE, $organization));
        }
    }

    public function test_foreman_and_cross_organization_user_cannot_manage_members(): void
    {
        $organization = new Organization();
        $foreman = (new User())->setEmail('foreman@example.com')->setOrganization($organization)->setRoles([UserRole::FOREMAN->value]);
        $otherOwner = (new User())->setEmail('owner@example.com')->setOrganization(new Organization())->setRoles([UserRole::OWNER->value]);

        self::assertSame(Voter::ACCESS_DENIED, $this->vote($foreman, MembershipVoter::MANAGE, $organization));
        self::assertSame(Voter::ACCESS_DENIED, $this->vote($otherOwner, MembershipVoter::MANAGE, $organization));
    }

    private function vote(User $user, string $attribute, Organization $organization): int
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return (new MembershipVoter())->vote($token, $organization, [$attribute]);
    }
}
