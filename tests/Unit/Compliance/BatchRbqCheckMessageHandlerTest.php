<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\MessageHandler\BatchRbqCheckMessageHandler;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Compliance\Infrastructure\RbqScraper;
use App\Compliance\Message\BatchRbqCheckMessage;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BatchRbqCheckMessageHandlerTest extends TestCase
{
    public function test_batch_reports_manual_review_without_erasing_previous_success(): void
    {
        $project = new Project(new Organization(), '100 rue Test', 'Client');
        $lastSuccess = new \DateTimeImmutable('2026-08-01 10:00:00');
        $subcontractor = (new Subcontractor())
            ->setName('Construction Exemple')
            ->setRbqLicenseNumber('RBQ-1234-5678-90')
            ->setVerified(true)
            ->setLastVerifiedAt($lastSuccess);
        $project->addSubcontractor($subcontractor);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($project);
        $entityManager->expects(self::once())->method('flush');
        $repository = $this->createMock(SubcontractorRepository::class);
        $repository->method('findOneForProjectByLicense')->willReturn($subcontractor);
        $cache = new ArrayAdapter();
        $scraper = new RbqScraper(
            new MockHttpClient(new MockResponse('<html><body>structure inconnue</body></html>', ['http_code' => 200])),
            $this->createStub(LoggerInterface::class),
        );
        $handler = new BatchRbqCheckMessageHandler($entityManager, $repository, $scraper, $cache);

        $handler(new BatchRbqCheckMessage((string) $project->getId(), ['RBQ-1234-5678-90']));

        self::assertTrue($subcontractor->requiresManualReview());
        self::assertTrue($subcontractor->isVerified());
        self::assertSame($lastSuccess, $subcontractor->getLastVerifiedAt());
        self::assertSame([
            'status' => 'complete',
            'total' => 1,
            'completed' => 1,
            'verified' => 0,
            'manualReview' => 1,
            'invalid' => 0,
        ], $cache->get('rbq.batch.'.(string) $project->getId(), static fn (): array => []));
    }
}
