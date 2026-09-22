<?php

declare(strict_types=1);

namespace App\Identity\Application\Membership;

use App\Identity\Application\MagicLink\SendMagicLinkMessage;
use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\Invitation;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class IssueInvitation
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /** @param iterable<Project> $projects */
    public function issue(Organization $organization, string $email, UserRole $role, iterable $projects): Invitation
    {
        $rawToken = bin2hex(random_bytes(64));
        $invitation = new Invitation($organization, $email, $role, hash('sha256', $rawToken));
        foreach ($projects as $project) {
            $invitation->assignProject($project);
        }
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $url = $this->urlGenerator->generate('auth_invitation_accept', ['token' => $rawToken], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->bus->dispatch(new SendMagicLinkMessage(
            to: $invitation->getEmail(),
            subject: 'Invitation à rejoindre SysGC',
            template: 'emails/invitation.html.twig',
            context: ['url' => $url, 'organization' => $organization->getName()],
        ));

        return $invitation;
    }
}
