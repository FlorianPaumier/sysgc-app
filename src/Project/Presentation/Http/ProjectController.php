<?php

declare(strict_types=1);

namespace App\Project\Presentation\Http;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\Organization;
use App\Project\Application\ComplianceScoreCalculator;
use App\Project\Application\ProjectService;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Doctrine\ProjectRepository;
use App\Project\Infrastructure\Security\ProjectVoter;
use App\Project\Presentation\Form\ProjectType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chantiers', name: 'project_')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projects, ComplianceScoreCalculator $scores): Response
    {
        $user = $this->requireUser();
        $filter = $request->query->getString('status') === ProjectStatus::ARCHIVED->value ? 'archived' : 'current';
        $allowedStatuses = $filter === 'archived'
            ? [ProjectStatus::ARCHIVED]
            : [ProjectStatus::ACTIVE, ProjectStatus::ON_HOLD];
        $items = $user->isForeman()
            ? array_values(array_filter(
                $user->getAssignedProjects()->toArray(),
                static fn (Project $project): bool => in_array($project->getStatus(), $allowedStatuses, true),
            ))
            : $projects->findBy(
                ['organization' => $user->getOrganization(), 'status' => $allowedStatuses],
                ['startDate' => 'DESC'],
            );

        return $this->render('project/index.html.twig', [
            'projects' => $items,
            'scores' => $this->scoresByProject($items, $scores),
            'filter' => $filter,
            'isForeman' => $user->isForeman(),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProjectService $projects): Response
    {
        if (! $this->requireUser()->isAdmin() || $this->requireOrganization()->isReadOnly()) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(ProjectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->projectData($form->getData());
            $projects->create($data, $this->requireOrganization());
            $this->addFlash('success', 'Le chantier a été créé.');

            return $this->redirectToRoute('app_project_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, Project $project, ComplianceScoreCalculator $scores): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $activeTab = $request->query->getString('tab', 'overview');
        if (!in_array($activeTab, ['overview', 'conformite', 'securite', 'finances', 'documents'], true)) {
            $activeTab = 'overview';
        }

        $view = [
            'project' => $project,
            'score' => $scores->calculate($project),
            'activeTab' => $activeTab,
            'tabs' => $this->detailTabs($project),
            'isArchived' => $project->getStatus() === ProjectStatus::ARCHIVED,
        ];

        if ($request->headers->get('Turbo-Frame') === 'project-detail') {
            return $this->render('project/_detail_frame.html.twig', $view);
        }

        return $this->render('project/show.html.twig', $view);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, ProjectService $projects): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        if ($project->getStatus() === ProjectStatus::ARCHIVED) {
            throw $this->createAccessDeniedException('Un chantier archivé est en lecture seule.');
        }
        $form = $this->createForm(ProjectType::class, [
            'address' => $project->getAddress(),
            'clientName' => $project->getClientName(),
            'startDate' => $project->getStartDate(),
            'endDate' => $project->getEndDate(),
            'budget' => $project->getBudget(),
            'status' => $project->getStatus(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->projectData($form->getData());
            $projects->update($project, $data);
            $this->addFlash('success', 'Le chantier a été mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/edit.html.twig', ['project' => $project, 'form' => $form]);
    }

    #[Route('/{id}/archiver', name: 'archive', methods: ['POST'])]
    public function archive(Request $request, Project $project, ProjectService $projects): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        if ($project->getStatus() === ProjectStatus::ARCHIVED) {
            throw $this->createAccessDeniedException('Un chantier archivé est en lecture seule.');
        }
        if ($this->isCsrfTokenValid('archive-'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $projects->archive($project);
            $this->addFlash('success', 'Le chantier a été archivé.');
        }

        return $this->redirectToRoute('app_project_index', status: Response::HTTP_SEE_OTHER);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || null === $user->getOrganization()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function requireOrganization(): Organization
    {
        $organization = $this->requireUser()->getOrganization();
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        return $organization;
    }

    /** @return array{address: string, clientName: string, startDate: \DateTimeInterface, endDate: \DateTimeInterface|null, budget: string, status: ProjectStatus} */
    private function projectData(mixed $data): array
    {
        $endDate = is_array($data) ? ($data['endDate'] ?? null) : null;
        if (!is_array($data)
            || !is_string($data['address'] ?? null)
            || !is_string($data['clientName'] ?? null)
            || !($data['startDate'] ?? null) instanceof \DateTimeInterface
            || !(null === $endDate || $endDate instanceof \DateTimeInterface)
            || !is_string($data['budget'] ?? null)
            || !($data['status'] ?? null) instanceof ProjectStatus
        ) {
            throw new \LogicException('Project form returned invalid data.');
        }

        return [
            'address' => $data['address'],
            'clientName' => $data['clientName'],
            'startDate' => $data['startDate'],
            'endDate' => $endDate,
            'budget' => $data['budget'],
            'status' => $data['status'],
        ];
    }

    /** @return list<array{id: string, label: string, url: string, frameId: string}> */
    private function detailTabs(Project $project): array
    {
        $labels = [
            'overview' => 'Aperçu',
            'conformite' => 'Conformité',
            'securite' => 'Sécurité',
            'finances' => 'Finances',
            'documents' => 'Documents',
        ];
        $tabs = [];
        foreach ($labels as $id => $label) {
            $parameters = ['id' => $project->getId()];
            if ($id !== 'overview') {
                $parameters['tab'] = $id;
            }
            $tabs[] = [
                'id' => $id,
                'label' => $label,
                'url' => $this->generateUrl('app_project_show', $parameters),
                'frameId' => 'project-detail',
            ];
        }

        return $tabs;
    }

    /**
     * @param list<Project> $projects
     * @return array<string, int>
     */
    private function scoresByProject(array $projects, ComplianceScoreCalculator $calculator): array
    {
        $scores = [];
        foreach ($projects as $project) {
            $scores[(string) $project->getId()] = $calculator->calculate($project);
        }

        return $scores;
    }
}
