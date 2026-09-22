<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Organization> */
final class MembershipVoter extends Voter
{
    public const string MANAGE = 'organization_members_manage';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (! $user instanceof User || ! $user->isAdmin() || $user->getOrganization() === null) {
            return false;
        }

        return $user->getOrganization()->getId()->equals($subject->getId());
    }
}
