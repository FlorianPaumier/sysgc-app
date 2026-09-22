<?php

declare(strict_types=1);

namespace App\Project\Presentation\Http;

use App\Identity\Domain\Model\User;
use App\Project\Application\ComplianceScoreCalculator;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Infrastructure\Doctrine\ProjectRepository;
use App\Safety\Application\DailyLogService;
use App\Safety\Domain\Model\SafetyLog;
use App\Workforce\Infrastructure\Doctrine\WorkerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        WorkerRepository $workerRepository,
        ComplianceScoreCalculator $scoreCalculator,
    ): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $organization = $user->getOrganization();
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }
        if ($user->isForeman()) {
            return $this->redirectToRoute('app_dashboard_terrain');
        }

        $filter = $request->query->getString('status') === ProjectStatus::ARCHIVED->value ? 'archived' : 'current';
        $allProjects = $projectRepository->findBy(['organization' => $organization], ['startDate' => 'DESC']);
        $currentProjects = array_values(array_filter(
            $allProjects,
            static fn ($project): bool => \in_array($project->getStatus(), [ProjectStatus::ACTIVE, ProjectStatus::ON_HOLD], true),
        ));
        $projects = $filter === 'archived'
            ? array_values(array_filter($allProjects, static fn ($project): bool => $project->getStatus() === ProjectStatus::ARCHIVED))
            : $currentProjects;
        $workers = $workerRepository->count(['organization' => $organization]);
        $scores = [];
        foreach ($projects as $project) {
            $scores[(string) $project->getId()] = $scoreCalculator->calculate($project);
        }
        usort($projects, static function ($left, $right) use ($scores): int {
            $risk = $scores[(string) $left->getId()] <=> $scores[(string) $right->getId()];
            return $risk !== 0 ? $risk : (($right->getStartDate()?->getTimestamp() ?? 0) <=> ($left->getStartDate()?->getTimestamp() ?? 0));
        });
        $alerts = $filter === 'archived' ? [] : $scoreCalculator->getAlertsForProjects($currentProjects);

        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects,
            'scores' => $scores,
            'averageScore' => [] === $scores ? null : (int) round(array_sum($scores) / count($scores)),
            'alerts' => $alerts,
            'workers' => $workers,
            'filter' => $filter,
            'activeCount' => count(array_filter($currentProjects, static fn ($project): bool => $project->getStatus() === ProjectStatus::ACTIVE)),
            'onHoldCount' => count(array_filter($currentProjects, static fn ($project): bool => $project->getStatus() === ProjectStatus::ON_HOLD)),
        ]);
    }

    #[Route('/dashboard/terrain', name: 'dashboard_terrain', methods: ['GET'])]
    public function terrain(DailyLogService $logs): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (! $user->isForeman()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $today = new \DateTimeImmutable('today');
        $since = $today->modify('-6 days');
        $projects = $user->getAssignedProjects()->toArray();
        $todayTasks = [];
        $missingLogs = [];
        $recentCompleted = [];

        foreach ($projects as $project) {
            $projectLogs = $logs->findByProject($project, $since, $today);
            $byDate = [];
            foreach ($projectLogs as $log) {
                $date = $log->getDate();
                if (null !== $date) {
                    $byDate[$date->format('Y-m-d')] = $log;
                }
            }

            $todayLog = $byDate[$today->format('Y-m-d')] ?? null;
            $todayTasks[] = [
                'project' => $project,
                'log' => $todayLog,
                'complete' => $todayLog instanceof SafetyLog && $this->isComplete($todayLog),
            ];

            for ($date = $since; $date < $today; $date = $date->modify('+1 day')) {
                $dateKey = $date->format('Y-m-d');
                $log = $byDate[$dateKey] ?? null;
                if (! $log instanceof SafetyLog || ! $this->isComplete($log)) {
                    $missingLogs[] = [
                        'project' => $project,
                        'date' => $date,
                        'log' => $log,
                    ];
                }
            }

            foreach ($projectLogs as $log) {
                if ($this->isComplete($log)) {
                    $recentCompleted[] = $log;
                }
            }
        }

        usort($recentCompleted, static fn (SafetyLog $left, SafetyLog $right): int =>
            ($right->getDate()?->getTimestamp() ?? 0) <=> ($left->getDate()?->getTimestamp() ?? 0));

        return $this->render('dashboard/terrain.html.twig', [
            'projects' => $projects,
            'today' => $today,
            'todayTasks' => $todayTasks,
            'missingLogs' => $missingLogs,
            'recentCompleted' => array_slice($recentCompleted, 0, 5),
        ]);
    }

    private function isComplete(SafetyLog $log): bool
    {
        $items = $log->getChecklistItems();

        return [] !== $items && array_reduce(
            $items,
            static fn (bool $complete, array $item): bool => $complete && $item['checked'],
            true,
        );
    }

    #[Route('/dashboard/alerts', name: 'dashboard_alerts', methods: ['GET'])]
    public function alerts(ComplianceScoreCalculator $scoreCalculator): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $organization = $user->getOrganization();
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        $projects = array_values(array_filter(
            $organization->getProjects()->toArray(),
            static fn ($project): bool => \in_array($project->getStatus(), [ProjectStatus::ACTIVE, ProjectStatus::ON_HOLD], true),
        ));

        return $this->render('dashboard/_alerts.html.twig', ['alerts' => $scoreCalculator->getAlertsForProjects($projects)]);
    }
}
