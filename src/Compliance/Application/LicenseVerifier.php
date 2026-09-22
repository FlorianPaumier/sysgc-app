<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Project\Domain\Model\Project;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Domain\ValueObject\RbqLicenseResult;
use App\Compliance\Infrastructure\RbqScraper;
use App\Compliance\Message\BatchRbqCheckMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Verifies subcontractor RBQ licenses.
 */
final readonly class LicenseVerifier
{
    public function __construct(
        private RbqScraper $rbqScraper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $rbqLogger,
        private MessageBusInterface $bus,
    ) {}

    /**
     * Verify all subcontractors on a project.
     * Dispatches async checks for each subcontractor.
     *
     * @return array{accepted: int, queued: int}
     */
    public function verifyProject(Project $project): array
    {
        $subcontractors = $project->getSubcontractors();
        $count = $subcontractors->count();
        $licenses = [];

        foreach ($subcontractors as $subcontractor) {
            if (! $subcontractor->getRbqLicenseNumber()) {
                continue;
            }

            $licenses[] = $subcontractor->getRbqLicenseNumber();
        }

        if ($licenses !== []) {
            $this->bus->dispatch(new BatchRbqCheckMessage((string) $project->getId(), $licenses));
        }

        return [
            'accepted' => $count,
            'queued' => count($licenses),
        ];
    }

    /**
     * Synchronously verify a single subcontractor.
     * Updates isVerified and lastVerifiedAt on the entity.
     *
     * @return RbqLicenseResult Verification result
     */
    public function verifySingle(Subcontractor $subcontractor): RbqLicenseResult
    {
        $licenseNumber = $subcontractor->getRbqLicenseNumber();

        $attemptedAt = new \DateTimeImmutable();

        if (! $licenseNumber) {
            $this->rbqLogger->warning('Subcontractor missing RBQ license', [
                'subcontractor_id' => $subcontractor->getId()->toRfc4122(),
            ]);

            $subcontractor->requireManualReview('missing_license', $attemptedAt);
            $this->entityManager->persist($subcontractor);
            $this->entityManager->flush();

            return new RbqLicenseResult(found: false);
        }

        $result = $this->rbqScraper->check($licenseNumber);

        $subcontractor->recordAutomatedVerification($result, $attemptedAt);

        $this->entityManager->persist($subcontractor);
        $this->entityManager->flush();

        return $result;
    }
}
