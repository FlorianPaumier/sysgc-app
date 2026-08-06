<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for project-level access control.
 *
 * Enforces per-project authorization by checking that the user's organization
 * matches the project's organization. Prevents cross-organization data access.
 *
 * @see specs/10-security.md (BR-02)
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (! \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)) {
            return false;
        }

        if (! $subject instanceof Project) {
            return false;
        }

        return true;
    }

    #[\Override]
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        #[\SensitiveParameter]
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        /** @var Project $subject */
        $projectOrganization = $subject->getOrganization();
        $userOrganization = $user->getOrganization();

        // User must have an organization
        if ($userOrganization === null) {
            return false;
        }

        // Project must have an organization
        if ($projectOrganization === null) {
            return false;
        }

        // Access granted only if organizations match
        return $projectOrganization->getId()->equals($userOrganization->getId());
    }
}
