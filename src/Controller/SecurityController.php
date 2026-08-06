<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LoginType;
use App\Repository\UserRepository;
use App\Service\MagicLinkDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagicLinkDispatcher $magicLinkDispatcher,
        private readonly RateLimiterFactory $loginPerEmailLimiterFactory,
        private readonly RateLimiterFactory $loginPerIpLimiterFactory,
    ) {}

    #[Route('/connexion', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // IP-based brute-force protection.
        $ipLimiter = $this->loginPerIpLimiterFactory->create($request->getClientIp());
        $ipLimit = $ipLimiter->consume();
        if (! $ipLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $ipLimit->getRetryAfter()->getTimestamp() - time(),
                'Trop de tentatives depuis cette adresse IP. Réessayez plus tard.',
            );
        }

        $form = $this->createForm(LoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();
            $email = $data['email'];

            // Enforce per‑email rate limit (3 attempts per 15 minutes).
            $limiter = $this->loginPerEmailLimiterFactory->create($email);
            $rateLimit = $limiter->consume();
            if (! $rateLimit->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    $rateLimit->getRetryAfter()->getTimestamp() - time(),
                    'Trop de tentatives. Réessayez dans 15 minutes.',
                );
            }

            // Always show the same "check your email" confirmation to avoid
            // user enumeration — see specs/10-security.md.
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user !== null) {
                $this->magicLinkDispatcher->dispatchFor($user);
            }

            return $this->render('security/check_email.html.twig');
        }

        return $this->render('security/login.html.twig', [
            'form' => $form,
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Entry point for the magic-link URL. The MagicLinkAuthenticator intercepts
     * the request before this action runs.
     */
    #[Route('/connexion/verifier', name: 'login_verify', methods: ['GET'])]
    public function verify(): never
    {
        throw new \LogicException('Intercepted by MagicLinkAuthenticator.');
    }

    #[Route('/deconnexion', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }
}
