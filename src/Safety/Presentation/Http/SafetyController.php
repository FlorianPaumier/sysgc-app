<?php

declare(strict_types=1);

namespace App\Safety\Presentation\Http;

use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Security\ProjectVoter;
use App\Safety\Application\DailyLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chantiers', name: 'safety_')]
final class SafetyController extends AbstractController
{
    #[Route('/{id}/securite', name: 'index', methods: ['GET'])]
    public function index(Project $project, DailyLogService $logs, Request $request, ?int $month = null, ?int $year = null): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $today = new \DateTimeImmutable('today');
        $selectedMonth = $month ?? $request->query->getInt('month', (int) $today->format('m'));
        $selectedYear = $year ?? $request->query->getInt('year', (int) $today->format('Y'));
        if ($selectedMonth < 1 || $selectedMonth > 12 || $selectedYear < 2000 || $selectedYear > 2100) {
            throw $this->createNotFoundException('Le mois du calendrier est invalide.');
        }

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
        $monthEnd = $monthStart->modify('last day of this month');
        $byDate = [];

        foreach ($logs->findByProject($project, $monthStart, $monthEnd) as $log) {
            $date = $log->getDate();
            if (null !== $date) {
                $byDate[$date->format('Y-m-d')] = $log;
            }
        }

        return $this->render('safety/index.html.twig', [
            'project' => $project,
            'today' => $today,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'previousMonth' => $monthStart->modify('-1 month'),
            'nextMonth' => $monthStart->modify('+1 month'),
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'logsByDate' => $byDate,
        ]);
    }

    #[Route('/{id}/securite/{date}', name: 'daily', methods: ['GET'], requirements: ['date' => '\\d{4}-\\d{2}-\\d{2}'])]
    public function daily(Project $project, string $date, DailyLogService $logs): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $day || (is_array($errors) && (0 < $errors['warning_count'] || 0 < $errors['error_count']))) {
            throw $this->createNotFoundException('La date du rapport est invalide.');
        }

        return $this->render('safety/daily.html.twig', [
            'project' => $project,
            'date' => $day,
            'log' => $logs->findByProject($project, $day, $day)[0] ?? null,
        ]);
    }
}
