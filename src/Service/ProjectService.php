<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enum\ProjectStatus;
use App\Entity\Organization;
use App\Entity\Project;
use App\Repository\ProjectRepository;

readonly class ProjectService
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, Organization $org): Project
    {
        $address = \is_string($data['address'] ?? null) ? $data['address'] : 'Unknown';
        $clientName = \is_string($data['clientName'] ?? null) ? $data['clientName'] : 'Unknown';

        $project = new Project($org, $address, $clientName);

        if ($data['startDate'] instanceof \DateTimeInterface) {
            $project->setStartDate($data['startDate']);
        }

        if (\is_string($data['budget'])) {
            $project->setBudget($data['budget']);
        }

        if ($data['endDate'] instanceof \DateTimeInterface) {
            $project->setEndDate($data['endDate']);
        }

        if ($data['status'] instanceof ProjectStatus) {
            $project->setStatus($data['status']);
        }

        $this->projectRepository->save($project, true);

        return $project;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Project $project, array $data): Project
    {
        if (\is_string($data['address'] ?? null)) {
            $project->setAddress($data['address']);
        }

        if (\is_string($data['clientName'] ?? null)) {
            $project->setClientName($data['clientName']);
        }

        if ($data['startDate'] instanceof \DateTimeInterface) {
            $project->setStartDate($data['startDate']);
        }

        if ($data['endDate'] instanceof \DateTimeInterface) {
            $project->setEndDate($data['endDate']);
        }

        if (\is_string($data['budget'] ?? null)) {
            $project->setBudget($data['budget']);
        }

        if ($data['status'] instanceof ProjectStatus) {
            $project->setStatus($data['status']);
        }

        $this->projectRepository->save($project, true);

        return $project;
    }

    public function archive(Project $project): void
    {
        if ($project->getStatus() === ProjectStatus::ACTIVE) {
            $project->setStatus(ProjectStatus::ON_HOLD);
        }

        $this->projectRepository->save($project, true);
    }
}
