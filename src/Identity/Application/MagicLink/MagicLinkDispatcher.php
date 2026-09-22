<?php

declare(strict_types=1);

namespace App\Identity\Application\MagicLink;

use App\Identity\Domain\Model\MagicLink;
use App\Identity\Domain\Model\User;
use App\Identity\Application\MagicLink\SendMagicLinkMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates a fresh magic-link token, persists its SHA-256 hash, and dispatches
 * the email message. Shared by the login and registration flows.
 *
 * The raw 128-char hex token is returned to the user only via the email URL;
 * the database stores hash('sha256', $token) exclusively.
 *
 * @see specs/04-auth.md
 */
final readonly class MagicLinkDispatcher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function dispatchFor(User $user): void
    {
        $rawToken = bin2hex(random_bytes(64));

        $magicLink = new MagicLink()->setTokenHash(hash('sha256', $rawToken))
            ->setMagicLinkTokenExpiresAt(new \DateTimeImmutable('+15 minutes'))
            ->setOwner($user);

        $this->entityManager->persist($magicLink);
        $this->entityManager->flush();

        $url = $this->urlGenerator->generate(
            'auth_login_verify',
            [
                'token' => $rawToken,
                'email' => $user->getEmail(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->bus->dispatch(new SendMagicLinkMessage(
            to: $user->getEmail(),
            subject: 'Connectez-vous à SysGC',
            template: 'emails/magic_link.html.twig',
            context: ['url' => $url],
        ));
    }
}
