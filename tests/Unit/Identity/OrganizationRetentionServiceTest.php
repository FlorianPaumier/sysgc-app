<?php

declare(strict_types=1);

namespace AppTests\Unit\Identity;

use App\Identity\Domain\Model\Organization;
use App\Identity\Infrastructure\Doctrine\OrganizationRepository;
use App\Identity\Application\OrganizationRetentionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OrganizationRetentionServiceTest extends TestCase
{
    public function test_purge_removes_organizations_past_recovery_deadline(): void
    {
        $organization = new Organization();
        $organization->scheduleDeletion(new \DateTimeImmutable('2026-09-01'));

        $repository = $this->createMock(OrganizationRepository::class);
        $repository->expects(self::once())->method('findDueForPurge')->willReturn([$organization]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($organization);
        $entityManager->expects(self::once())->method('flush');

        $service = new OrganizationRetentionService($repository, $entityManager);

        self::assertSame(1, $service->purgeDueOrganizations(new \DateTimeImmutable('2026-10-01')));
    }
}
