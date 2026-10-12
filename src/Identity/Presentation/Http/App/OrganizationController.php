<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\App;

use App\Identity\Domain\Model\User;
use App\Identity\Presentation\Form\OrganizationSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/organisation', name: 'organisation', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->getUser();
        if (! $user instanceof User || ! $user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $organization = $user->getOrganization();
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(OrganizationSettingsType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$organization->isReadOnly()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Vos modifications ont été enregistrées.');

            return $this->redirectToRoute('app_organisation');
        }

        return $this->render('organization/settings.html.twig', [
            'form' => $form,
            'organization' => $organization,
            'subscriptionStatus' => $organization->subscriptionStatus(new \DateTimeImmutable(), $organization->getWorkers()->count(), $organization->getUsers()->count()),
            'workerCount' => $organization->getWorkers()->count(),
            'userCount' => $organization->getUsers()->count(),
        ]);
    }

    #[Route('/organisation/exporter', name: 'organization_export', methods: ['GET'])]
    public function export(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isOwner() || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $organization = $user->getOrganization();
        $payload = [
            'organisation' => ['nom' => $organization->getName(), 'licence_rbq' => $organization->getRbqLicense()],
            'membres' => array_map(static fn (User $member): array => ['courriel' => $member->getEmail(), 'role' => $member->getRoleLabel(), 'actif' => $member->isActive()], $organization->getUsers()->toArray()),
            'chantiers' => array_map(static fn ($project): array => ['nom' => $project->getName(), 'adresse' => $project->getAddress()], $organization->getProjects()->toArray()),
        ];

        $response = new Response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'export-organisation.json'));

        return $response;
    }

    #[Route('/organisation/suppression', name: 'organization_schedule_deletion', methods: ['POST'])]
    public function scheduleDeletion(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('organization_delete', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isOwner() || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $user->getOrganization()->scheduleDeletion();
        $this->entityManager->flush();
        $this->addFlash('success', 'La suppression est planifiée dans 30 jours. Vous pouvez encore récupérer votre organisation.');

        return $this->redirectToRoute('app_organisation');
    }

    #[Route('/organisation/suppression/annuler', name: 'organization_recover_deletion', methods: ['POST'])]
    public function recoverDeletion(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('organization_recover', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isOwner() || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $organization = $user->getOrganization();
        if (!$organization->canRecoverDeletion(new \DateTimeImmutable())) {
            throw $this->createAccessDeniedException();
        }
        $organization->recoverDeletion();
        $this->entityManager->flush();
        $this->addFlash('success', 'La suppression de l’organisation a été annulée.');

        return $this->redirectToRoute('app_organisation');
    }
}
