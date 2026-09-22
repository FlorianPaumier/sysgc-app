<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Project\Domain\Enum\ProjectStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectStatus::class)]
class ProjectStatusTest extends TestCase
{
    public function testActiveStatus(): void
    {
        $status = ProjectStatus::ACTIVE;
        $this->assertSame('active', $status->value);
    }

    public function testCompletedStatus(): void
    {
        $status = ProjectStatus::COMPLETED;
        $this->assertSame('completed', $status->value);
    }

    public function testOnHoldStatus(): void
    {
        $status = ProjectStatus::ON_HOLD;
        $this->assertSame('on_hold', $status->value);
    }

    public function testArchivedStatusCanBeRestoredFromPersistence(): void
    {
        $this->assertSame(ProjectStatus::ARCHIVED, ProjectStatus::tryFrom('archived'));
    }

    public function testAllStatusesExist(): void
    {
        $statuses = [
            ProjectStatus::ACTIVE,
            ProjectStatus::COMPLETED,
            ProjectStatus::ON_HOLD,
            ProjectStatus::tryFrom('archived'),
        ];

        $this->assertCount(4, $statuses);
    }
}
