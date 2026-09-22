<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\App;

use App\Identity\Application\Membership\IssueInvitation;
use App\Identity\Domain\Enum\UserRole;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\InvitationRepository;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Identity\Infrastructure\Security\MembershipVoter;
use App\Identity\Presentation\Form\InvitationType;
use App\Project\Infrastructure\Doctrine\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final class MembershipController extends AbstractController
{
    #[Route('/organisation/membres/invitations/{id}/revoquer', name: 'organization_invitation_revoke', methods: ['POST'])]
    public function revoke(string $id, InvitationRepository $invitations, EntityManagerInterface $entityManager): Response
    {
        $actor = $this->requireManager();
        try {
            $invitationId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException('Invitation introuvable.', $exception);
        }
        $invitation = $invitations->find($invitationId);
        if (!$invitation instanceof \App\Identity\Domain\Model\Invitation || $invitation->getOrganization() !== $actor->getOrganization()) {
            throw $this->createNotFoundException();
        }
        $invitation->revoke();
        $entityManager->flush();
        $this->addFlash('success', 'L\'invitation a été révoquée.');

        return $this->redirectToRoute('app_organization_members');
    }

    #[Route('/organisation/membres/{id}/desactiver', name: 'organization_member_deactivate', methods: ['POST'])]
    public function deactivate(string $id, UserRepository $users, EntityManagerInterface $entityManager): Response
    {
        $actor = $this->requireManager();
        $member = $users->find($this->uuidOrNotFound($id));
        if (!$member instanceof User || $member->getOrganization() !== $actor->getOrganization() || $member === $actor) {
            throw $this->createNotFoundException();
        }
        $member->deactivate();
        $entityManager->flush();
        $this->addFlash('success', 'Le membre a été désactivé.');

        return $this->redirectToRoute('app_organization_members');
    }

    #[Route('/organisation/membres/{id}/transferer-propriete', name: 'organization_transfer_ownership', methods: ['POST'])]
    public function transferOwnership(string $id, UserRepository $users, EntityManagerInterface $entityManager): Response
    {
        $actor = $this->requireManager();
        if (!$actor->isOwner()) {
            throw $this->createAccessDeniedException();
        }
        $member = $users->find($this->uuidOrNotFound($id));
        if (!$member instanceof User || $member->getOrganization() !== $actor->getOrganization()) {
            throw $this->createNotFoundException();
        }
        $actor->transferOwnershipTo($member);
        $entityManager->flush();
        $this->addFlash('success', 'La propriété de l’organisation a été transférée.');

        return $this->redirectToRoute('app_organization_members');
    }

    #[Route('/organisation/membres/{id}/chantiers', name: 'organization_member_projects', methods: ['POST'])]
    public function assignProjects(string $id, Request $request, UserRepository $users, ProjectRepository $projects, EntityManagerInterface $entityManager): Response
    {
        $actor = $this->requireManager();
        if (!$this->isCsrfTokenValid('organization_projects_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $member = $users->find($this->uuidOrNotFound($id));
        if (!$member instanceof User || $member->getOrganization() !== $actor->getOrganization() || !$member->isForeman()) {
            throw $this->createNotFoundException();
        }
        foreach ($member->getAssignedProjects()->toArray() as $project) {
            $member->unassignProject($project);
        }
        foreach ((array) $request->request->all('projects') as $projectId) {
            if (!is_string($projectId) || !Uuid::isValid($projectId)) {
                continue;
            }
            $project = $projects->find(Uuid::fromString($projectId));
            if ($project !== null && $project->getOrganization() === $actor->getOrganization()) {
                $member->assignProject($project);
            }
        }
        $entityManager->flush();
        $this->addFlash('success', 'Les chantiers assignés ont été mis à jour.');

        return $this->redirectToRoute('app_organization_members');
    }

    private function requireManager(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $this->denyAccessUnlessGranted(MembershipVoter::MANAGE, $user->getOrganization());
        if ($user->getOrganization()->isReadOnly()) {
            throw $this->createAccessDeniedException('Votre organisation est en lecture seule.');
        }

        return $user;
    }

    #[Route('/organisation/membres', name: 'organization_members', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $users,
        InvitationRepository $invitations,
        ProjectRepository $projects,
        IssueInvitation $issuer,
    ): Response {
        $user = $this->getUser();
        if (! $user instanceof User || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $organization = $user->getOrganization();
        $this->denyAccessUnlessGranted(MembershipVoter::MANAGE, $organization);
        $organizationProjects = $projects->findBy(['organization' => $organization], ['startDate' => 'DESC']);
        $form = $this->createForm(InvitationType::class, null, ['projects' => $organizationProjects]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($organization->isReadOnly()) {
                throw $this->createAccessDeniedException('Votre organisation est en lecture seule.');
            }
            /** @var array{email: string, role: UserRole, projects: iterable<\App\Project\Domain\Model\Project>} $data */
            $data = $form->getData();
            $issuer->issue($organization, $data['email'], $data['role'], $data['projects']);
            $this->addFlash('success', 'L\'invitation a été envoyée.');
            return $this->redirectToRoute('app_organization_members');
        }

        return $this->render('organization/members.html.twig', [
            'members' => $users->findBy(['organization' => $organization], ['email' => 'ASC']),
            'invitations' => $invitations->findBy(['organization' => $organization], ['expiresAt' => 'DESC']),
            'form' => $form,
        ]);
    }

    private function uuidOrNotFound(string $id): Uuid
    {
        try {
            return Uuid::fromString($id);
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException('Membre introuvable.', $exception);
        }
    }
}
