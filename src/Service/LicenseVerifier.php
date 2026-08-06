<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\Subcontractor;
use App\Service\ValueObject\RbqLicenseResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Verifies subcontractor RBQ licenses.
 */
final readonly class LicenseVerifier
{
    public function __construct(
        private RbqScraper $rbqScraper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $rbqLogger,
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
        $queued = 0;

        foreach ($subcontractors as $subcontractor) {
            if (! $subcontractor->getRbqLicenseNumber()) {
                continue;
            }

            // TODO: Dispatch BatchRbqCheckMessage (next card)
            ++$queued;
        }

        return [
            'accepted' => $count,
            'queued' => $queued,
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

        $subcontractor->setLastVerifiedAt(new \DateTimeImmutable);

        if (! $licenseNumber) {
            $this->rbqLogger->warning('Subcontractor missing RBQ license', [
                'subcontractor_id' => $subcontractor->getId()->toRfc4122(),
            ]);

            $this->entityManager->persist($subcontractor);
            $this->entityManager->flush();

            return new RbqLicenseResult(found: false);
        }

        $result = $this->rbqScraper->check($licenseNumber);

        // Only mark verified if the license exists and is in good standing.
        $subcontractor->setVerified(
            $result->found
            && $result->error === null
            && $result->status === 'valid',
        );
        $subcontractor->setLastVerifiedAt(new \DateTimeImmutable);

        $this->entityManager->persist($subcontractor);
        $this->entityManager->flush();

        return $result;
    }
}
