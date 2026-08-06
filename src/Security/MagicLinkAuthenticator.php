<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\MagicLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Passwordless magic-link authenticator.
 *
 * Request: GET /connexion/verifier?token=<raw>&email=<email>
 * The raw token is SHA-256 hashed and matched against the persisted hash.
 * A valid, non-expired link authenticates the user and marks the email
 * verified (Reading A: the magic link doubles as email verification).
 *
 * @see specs/04-auth.md
 */
final readonly class MagicLinkAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private MagicLinkRepository $magicLinkRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'login_verify';
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $rawToken = (string) $request->query->get('token', '');
        $email = \mb_strtolower((string) $request->query->get('email', ''));
        $tokenHash = hash('sha256', $rawToken);

        $magicLink = $this->magicLinkRepository->findValid($email, $tokenHash);

        if ($magicLink === null) {
            throw new BadCredentialsException('Invalid or expired magic link.');
        }

        $user = $magicLink->getOwner();
        \assert($user instanceof User, 'Expected User instance from MagicLink owner');

        // Single-use: consume the magic link immediately.
        $this->entityManager->remove($magicLink);
        $this->entityManager->flush();

        // Reading A: a successful magic link verifies the email address.
        if (! $user->isVerified()) {
            $user->setIsVerified(true);
            $this->entityManager->flush();
        }

        $userLoader = static fn (): UserInterface => $user;

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), $userLoader));
    }

    #[\Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();

        return new PostAuthenticationToken($user, $firewallName, $user->getRoles());
    }

    #[\Override]
    public function onAuthenticationSuccess(
        Request $request,
        #[\SensitiveParameter]
        TokenInterface $token,
        string $firewallName,
    ): Response {
        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        \assert($session instanceof Session, 'Expected Session instance from request');
        $session->getFlashBag()->add('error', 'Ce lien est invalide ou a expiré. Veuillez demander un nouveau lien.');

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }
}
