<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Http;

use App\Compliance\Domain\Model\CCQDeclaration;
use App\Compliance\Infrastructure\Doctrine\CCQDeclarationRepository;
use App\Identity\Domain\Model\User;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Security\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'ccq_')]
final class CcqController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory)
    {
    }

    #[Route('/chantiers/{id}/ccq', name: 'declaration', methods: ['GET'])]
    public function declaration(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $now = new \DateTimeImmutable();

        return $this->render('ccq/declaration.html.twig', [
            'project' => $project,
            'currentMonth' => (int) $now->format('n'),
            'currentYear' => (int) $now->format('Y'),
        ]);
    }

    #[Route('/declarations', name: 'history', methods: ['GET'])]
    public function history(CCQDeclarationRepository $declarations): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }
        $now = new \DateTimeImmutable();

        return $this->render('ccq/history.html.twig', [
            'declarations' => array_values(array_filter($declarations->findAllForOrganization($this->organizationId()), $this->canViewDeclaration(...))),
            'projects' => array_values(array_filter($user->getOrganization()->getProjects()->toArray(), fn (Project $project): bool => $this->isGranted(ProjectVoter::VIEW, $project))),
            'currentMonth' => (int) $now->format('n'),
            'currentYear' => (int) $now->format('Y'),
        ]);
    }

    #[Route('/declarations/{id}', name: 'show', methods: ['GET'])]
    public function show(CCQDeclaration $declaration): Response
    {
        if ($declaration->getOrganization()?->getId()->equals($this->organizationId()) !== true) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->canViewDeclaration($declaration)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('ccq/show.html.twig', ['declaration' => $declaration]);
    }

    #[Route('/declarations/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(CCQDeclaration $declaration): BinaryFileResponse
    {
        if ($declaration->getOrganization()?->getId()->equals($this->organizationId()) !== true) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->canViewDeclaration($declaration)) {
            throw $this->createAccessDeniedException();
        }
        $receiptPath = $declaration->getReceiptPath();
        if ($receiptPath === null) {
            throw $this->createNotFoundException();
        }
        $storageRoot = realpath($this->projectDirectory.'/var/uploads');
        $path = realpath($this->projectDirectory.'/var/uploads/'.ltrim($receiptPath, '/'));
        if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot.'/') || !is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = $this->file($path, basename($path));
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[Route('/declarations/{id}/outcome', name: 'outcome', methods: ['POST'])]
    public function outcome(Request $request, CCQDeclaration $declaration, EntityManagerInterface $entityManager): Response
    {
        if ($declaration->getOrganization()?->getId()->equals($this->organizationId()) !== true) {
            throw $this->createAccessDeniedException();
        }
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $declaration->getProject());
        if (!$this->isCsrfTokenValid('ccq_outcome_'.$declaration->getId()->toRfc4122(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $outcome = (string) $request->request->get('outcome');
        $declaration->recordOutcome($outcome);
        $entityManager->flush();

        return $this->redirectToRoute('app_ccq_show', ['id' => $declaration->getId()]);
    }

    private function canViewDeclaration(CCQDeclaration $declaration): bool
    {
        if (!$this->isGranted(ProjectVoter::VIEW, $declaration->getProject())) {
            return false;
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $assignedIds = array_map(static fn (Project $project): string => (string) $project->getId(), $user->getAssignedProjects()->toArray());
        return array_all($declaration->getDeclaration(), fn($row) => !(isset($row['projectId']) && !in_array($row['projectId'], $assignedIds, true)));
    }

    private function organizationId(): \Symfony\Component\Uid\Uuid
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getOrganization() === null) {
            throw $this->createAccessDeniedException();
        }

        return $user->getOrganization()->getId();
    }
}
