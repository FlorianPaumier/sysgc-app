<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Auth;

use App\Identity\Domain\Model\Address;
use App\Identity\Domain\Model\Organization;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Enum\UserRole;
use App\Identity\Presentation\Form\RegistrationType;
use App\Identity\Application\MagicLink\MagicLinkDispatcher;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use function time;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MagicLinkDispatcher $magicLinkDispatcher,
        private readonly RateLimiterFactory $loginPerEmailLimiterFactory,
        private readonly RateLimiterFactory $loginPerIpLimiterFactory,
    ) {}

    #[Route('/inscription', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // IP-based brute-force protection — only consume on POST submission.
            $ipLimiter = $this->loginPerIpLimiterFactory->create($request->getClientIp());
            $ipLimit = $ipLimiter->consume();
            if (! $ipLimit->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    $ipLimit->getRetryAfter()->getTimestamp() - time(),
                    'Trop de tentatives depuis cette adresse IP. Réessayez plus tard.',
                );
            }

            /** @var array{email: string, organizationName: string, rbqLicense: string} $data */
            $data = $form->getData();
            $email = \mb_strtolower($data['email']);
            if ('' === $email) {
                throw new \LogicException('A valid registration email is required.');
            }

            // Enforce per‑email rate limit (3 attempts per 15 minutes).
            $limiter = $this->loginPerEmailLimiterFactory->create($email);
            $rateLimit = $limiter->consume();
            if (! $rateLimit->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    $rateLimit->getRetryAfter()->getTimestamp() - time(),
                    'Trop de tentatives. Réessayez dans 15 minutes.',
                );
            }

            $organization = new Organization()->setName($data['organizationName'])
                ->setRbqLicense($data['rbqLicense'])
                ->setAddress(new Address);

            $user = new User()->setEmail($email)
                ->setRoles([UserRole::OWNER->value])
                ->setOrganization($organization);

            $this->entityManager->persist($organization);
            $this->entityManager->persist($user);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                // Email already registered — redirect to login with a hint
                // (register is a trust boundary; minor enumeration accepted).
                $this->addFlash('error', 'Un compte existe déjà avec ce courriel. Veuillez vous connecter.');

                return $this->redirectToRoute('auth_login');
            }

            // Mail only dispatched after a successful commit.
            $this->magicLinkDispatcher->dispatchFor($user);

            return $this->redirectToRoute('auth_login_check_email');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
