<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Project\Domain\Enum\ProjectStatus;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Project::class)]
class ProjectTest extends TestCase
{
    private Organization $organization;

    protected function setUp(): void
    {
        $this->organization = $this->createStub(Organization::class);
    }

    public function testCreateProject(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertInstanceOf(Project::class, $project);
        $this->assertSame($this->organization, $project->getOrganization());
    }

    public function testAddressGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $project->setAddress('123 Main St');

        $this->assertSame('123 Main St', $project->getAddress());
    }

    public function testClientNameGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $project->setClientName('Acme Corp');

        $this->assertSame('Acme Corp', $project->getClientName());
    }

    public function testStartDateGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $date = new \DateTime('2025-01-15');
        $project->setStartDate($date);

        $this->assertSame($date, $project->getStartDate());
    }

    public function testEndDateGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $date = new \DateTime('2025-12-31');
        $project->setEndDate($date);

        $this->assertSame($date, $project->getEndDate());
    }

    public function testEndDateNullable(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertNull($project->getEndDate());
    }

    public function testStatusGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $project->setStatus(ProjectStatus::ACTIVE);

        $this->assertSame(ProjectStatus::ACTIVE, $project->getStatus());
        $this->assertSame(ProjectStatus::ACTIVE, $project->getStatus());
    }

    public function testDefaultStatus(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertSame(ProjectStatus::ACTIVE, $project->getStatus());
    }

    public function testBudgetGetterSetter(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');
        $project->setBudget('50000.00');

        $this->assertSame('50000.00', $project->getBudget());
    }

    public function testSubcontractorsCollection(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertCount(0, $project->getSubcontractors());
        $this->assertTrue($project->getSubcontractors()->isEmpty());
    }

    public function testSafetyLogsCollection(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertCount(0, $project->getSafetyLogs());
        $this->assertTrue($project->getSafetyLogs()->isEmpty());
    }

    public function testChangeOrdersCollection(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertCount(0, $project->getChangeOrders());
        $this->assertTrue($project->getChangeOrders()->isEmpty());
    }

    public function testInvoicesCollection(): void
    {
        $project = new Project($this->organization, '123 Test St', 'Test Client');

        $this->assertCount(0, $project->getInvoices());
        $this->assertTrue($project->getInvoices()->isEmpty());
    }
}
