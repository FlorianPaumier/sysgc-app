<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Enum\ProjectStatus;
use App\Entity\Organization;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ProjectService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectService::class)]
class ProjectServiceTest extends TestCase
{
    public function testCreateProject(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createStub(ProjectRepository::class);
        $data = [
            'address' => '123 Main St',
            'clientName' => 'Acme Corp',
            'startDate' => new \DateTime('2025-01-15'),
            'endDate' => new \DateTime('2025-12-31'),
            'budget' => '50000.00',
        ];

        $service = new ProjectService($repository);
        $project = $service->create($data, $organization);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertSame('123 Main St', $project->getAddress());
        $this->assertSame('Acme Corp', $project->getClientName());
        $this->assertSame('50000.00', $project->getBudget());
        $this->assertSame(ProjectStatus::ACTIVE, $project->getStatus());
        $this->assertSame($organization, $project->getOrganization());
    }

    public function testCreateProjectWithoutEndDate(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createStub(ProjectRepository::class);
        $data = [
            'address' => '456 Oak Ave',
            'clientName' => 'Beta Inc',
            'startDate' => new \DateTime('2025-02-01'),
            'budget' => '75000.00',
        ];

        $service = new ProjectService($repository);
        $project = $service->create($data, $organization);

        $this->assertNull($project->getEndDate());
    }

    public function testUpdateProject(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createStub(ProjectRepository::class);
        $project = new Project($organization, 'Old Address', 'Old Client');

        $data = [
            'address' => 'New Address',
            'clientName' => 'New Client',
            'budget' => '100000.00',
        ];

        $service = new ProjectService($repository);
        $updated = $service->update($project, $data);

        $this->assertSame('New Address', $updated->getAddress());
        $this->assertSame('New Client', $updated->getClientName());
        $this->assertSame('100000.00', $updated->getBudget());
        $this->assertSame(ProjectStatus::ACTIVE, $updated->getStatus());
    }

    public function testUpdateProjectChangeStatus(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createStub(ProjectRepository::class);
        $project = new Project($organization, '123 Test St', 'Test Client');
        $project->setStatus(ProjectStatus::ACTIVE);

        $data = [
            'status' => ProjectStatus::ON_HOLD,
        ];

        $service = new ProjectService($repository);
        $updated = $service->update($project, $data);

        $this->assertSame(ProjectStatus::ON_HOLD, $updated->getStatus());
    }

    public function testArchiveProject(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createMock(ProjectRepository::class);
        $project = new Project($organization, '123 Test St', 'Test Client');
        $project->setStatus(ProjectStatus::ACTIVE);

        $repository
            ->expects($this->once())
            ->method('save')
            ->with($project, true);

        $service = new ProjectService($repository);
        $service->archive($project);

        $this->assertSame(ProjectStatus::ON_HOLD, $project->getStatus());
    }

    public function testArchiveCompletedProject(): void
    {
        $organization = $this->createStub(Organization::class);
        $repository = $this->createMock(ProjectRepository::class);
        $project = new Project($organization, '123 Test St', 'Test Client');
        $project->setStatus(ProjectStatus::COMPLETED);

        $repository
            ->expects($this->once())
            ->method('save')
            ->with($project, true);

        $service = new ProjectService($repository);
        $service->archive($project);

        $this->assertSame(ProjectStatus::COMPLETED, $project->getStatus());
    }
}
