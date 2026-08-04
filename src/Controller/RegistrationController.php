<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Organization;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Service\MagicLinkDispatcher;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\Annotation\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MagicLinkDispatcher $magicLinkDispatcher,
        private readonly Security $security,
        private readonly RateLimiterFactory $loginPerEmailLimiterFactory,
    ) {}

    #[Route('/inscription', name: 'register', methods: ['GET', 'POST'])]
    #[RateLimit('login_per_ip', limit: 20, interval: '5 minutes')]
    public function register(Request $request): Response
    {
        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string, organizationName: string, rbqLicense: string} $data */
            $data = $form->getData();
            $email = $data['email'];

            // Enforce per‑email rate limit (3 attempts per 15 minutes).
            $limiter = $this->loginPerEmailLimiterFactory->create($email);
            $rateLimit = $limiter->consume();
            if (!$rateLimit->isAccepted()) {
                throw new TooManyRequestsHttpException($rateLimit->getRetryAfter()->getTimestamp() - time(), 'Trop de tentatives. Réessayez dans 15 minutes.');
            }

            $organization = (new Organization())
                ->setName($data['organizationName'])
                ->setRbqLicense($data['rbqLicense'])
                ->setAddress(new Address());

            $user = (new User())
                ->setEmail($email)
                ->setOrganization($organization);

            $this->entityManager->persist($organization);
            $this->entityManager->persist($user);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                // Email already registered — redirect to login with a hint
                // (register is a trust boundary; minor enumeration accepted).
                $this->addFlash('error', 'Un compte existe déjà avec ce courriel. Veuillez vous connecter.');

                return $this->redirectToRoute('login');
            }

            // Mail only dispatched after a successful commit.
            $this->magicLinkDispatcher->dispatchFor($user);

            // Auto-login to avoid churn (Reading A). The magic link email still
            // arrives to verify the address; clicking it flips isVerified.
            return $this->security->login($user, authenticatorName: null, firewallName: 'main');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
