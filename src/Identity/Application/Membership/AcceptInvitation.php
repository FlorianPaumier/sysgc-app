<?php

declare(strict_types=1);

namespace App\Identity\Application\Membership;

use App\Identity\Domain\Model\Invitation;
use App\Identity\Domain\Model\User;

final readonly class AcceptInvitation
{
    public function accept(Invitation $invitation, User $user, #[\SensitiveParameter] string $tokenHash, \DateTimeImmutable $now): void
    {
        if (mb_strtolower($user->getEmail()) !== $invitation->getEmail()) {
            throw new \DomainException('Cette invitation est destinée à une autre adresse courriel.');
        }
        $currentOrganization = $user->getOrganization();
        if ($currentOrganization !== null && ! $currentOrganization->getId()->equals($invitation->getOrganization()->getId())) {
            throw new \DomainException('Un utilisateur ne peut appartenir qu\'à une seule organisation.');
        }
        if (! $invitation->canBeAccepted($tokenHash, $now)) {
            throw new \DomainException('Cette invitation est invalide, expirée ou déjà utilisée.');
        }

        $user->setOrganization($invitation->getOrganization())->setRoles([$invitation->getRole()->value]);
        foreach ($invitation->getAssignedProjects() as $project) {
            $user->assignProject($project);
        }
        $invitation->accept($now);
    }
}
