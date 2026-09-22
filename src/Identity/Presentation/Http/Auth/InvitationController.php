<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Auth;

use App\Identity\Application\Membership\AcceptInvitation;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\InvitationRepository;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvitationController extends AbstractController
{
    #[Route('/invitation/accepter', name: 'invitation_accept', methods: ['GET'])]
    public function accept(
        Request $request,
        InvitationRepository $invitations,
        UserRepository $users,
        AcceptInvitation $acceptor,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $rawToken = $request->query->getString('token');
        $invitation = $invitations->findAcceptableByRawToken($rawToken, new \DateTimeImmutable());
        if ($invitation === null) {
            throw $this->createNotFoundException('Invitation introuvable.');
        }

        $authenticatedUser = $this->getUser();
        $user = $authenticatedUser instanceof User
            ? $authenticatedUser
            : ($users->findOneBy(['email' => $invitation->getEmail()]) ?? new User()->setEmail($invitation->getEmail())->setIsVerified(true));
        try {
            $acceptor->accept($invitation, $user, hash('sha256', $rawToken), new \DateTimeImmutable());
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('Invitation introuvable.', $exception);
        }
        $entityManager->persist($user);
        $entityManager->flush();
        if (! $authenticatedUser instanceof User) {
            $security->login($user);
        }
        $this->addFlash('success', 'Vous avez rejoint l\'organisation.');

        return $this->redirectToRoute($user->isForeman() ? 'app_dashboard_terrain' : 'app_dashboard');
    }
}
