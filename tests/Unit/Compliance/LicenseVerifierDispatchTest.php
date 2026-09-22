<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\LicenseVerifier;
use App\Compliance\Infrastructure\RbqScraper;
use App\Compliance\Message\BatchRbqCheckMessage;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Compliance\Domain\Model\Subcontractor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpClient\MockHttpClient;

final class LicenseVerifierDispatchTest extends TestCase
{
    public function test_verify_project_dispatches_one_batch_for_licensed_subcontractors(): void
    {
        $project = new Project(new Organization(), '100 rue Test', 'Client');
        $first = (new Subcontractor())->setName('A')->setRbqLicenseNumber('RBQ-1111-1111-11');
        $second = (new Subcontractor())->setName('B')->setRbqLicenseNumber('RBQ-2222-2222-22');
        $project->addSubcontractor($first)->addSubcontractor($second);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (BatchRbqCheckMessage $message): bool => $message->projectId === (string) $project->getId() && $message->licenseNumbers === ['RBQ-1111-1111-11', 'RBQ-2222-2222-22']))
            ->willReturn(new Envelope(new \stdClass()));

        $verifier = new LicenseVerifier(
            new RbqScraper(new MockHttpClient(), $this->createStub(LoggerInterface::class)),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $bus,
        );

        self::assertSame(['accepted' => 2, 'queued' => 2], $verifier->verifyProject($project));
    }
}
