<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\BatchCsvImporter;
use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class BatchCsvImporterTest extends TestCase
{
    public function test_import_creates_subcontractors_and_queues_each_license(): void
    {
        $project = new Project(new Organization(), '100 rue Test, Montréal', 'Client');
        $repository = $this->createStub(SubcontractorRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $importer = new BatchCsvImporter($repository, $entityManager, $bus);

        $result = $importer->import($project, "name,rbq_license,category\nABC Construction,RBQ-1234-5678-90,General\nSans licence,,Specialty\n");

        self::assertSame(['created' => 2, 'updated' => 0, 'queued' => 1], $result);
        self::assertCount(2, $project->getSubcontractors());
    }
}
