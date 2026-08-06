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

        if (isset($data['endDate']) && $data['endDate'] instanceof \DateTimeInterface) {
            $project->setEndDate($data['endDate']);
        }

        if (isset($data['status']) && $data['status'] instanceof ProjectStatus) {
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
        if (isset($data['address']) && \is_string($data['address'])) {
            $project->setAddress($data['address']);
        }

        if (isset($data['clientName']) && \is_string($data['clientName'])) {
            $project->setClientName($data['clientName']);
        }

        if (isset($data['startDate']) && $data['startDate'] instanceof \DateTimeInterface) {
            $project->setStartDate($data['startDate']);
        }

        if (isset($data['endDate']) && $data['endDate'] instanceof \DateTimeInterface) {
            $project->setEndDate($data['endDate']);
        }

        if (isset($data['budget']) && \is_string($data['budget'])) {
            $project->setBudget($data['budget']);
        }

        if (isset($data['status']) && $data['status'] instanceof ProjectStatus) {
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
