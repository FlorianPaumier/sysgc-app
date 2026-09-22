<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Infrastructure\Doctrine\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrganizationRetentionService
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function purgeDueOrganizations(\DateTimeImmutable $now): int
    {
        $organizations = $this->organizationRepository->findDueForPurge($now);
        foreach ($organizations as $organization) {
            $this->entityManager->remove($organization);
        }

        if ($organizations !== []) {
            $this->entityManager->flush();
        }

        return \count($organizations);
    }
}
