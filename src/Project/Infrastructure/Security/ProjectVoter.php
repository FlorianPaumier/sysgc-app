<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Security;

use App\Project\Domain\Model\Project;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Enum\UserRole;
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
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const string VIEW = 'view';
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';
    public const string SAFETY_EDIT = 'safety_edit';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (! \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SAFETY_EDIT], true)) {
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

        if (! $user instanceof User || !$user->isActive()) {
            return false;
        }

        /** @var Project $subject */
        $projectOrganization = $subject->getOrganization();
        $userOrganization = $user->getOrganization();

        // User must have an organization
        if ($userOrganization === null) {
            return false;
        }

        if (! $projectOrganization->getId()->equals($userOrganization->getId())) {
            return false;
        }

        if ($attribute !== self::VIEW && ($userOrganization->isReadOnly() || $subject->getStatus()->value === 'archived')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasRole(UserRole::FOREMAN) || !in_array($attribute, [self::VIEW, self::SAFETY_EDIT], true)) {
            return false;
        }

        return $user->getAssignedProjects()->contains($subject);
    }
}
