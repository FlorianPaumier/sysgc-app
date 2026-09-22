<?php

declare(strict_types=1);

namespace App\Compliance\Infrastructure\Doctrine;

use App\Compliance\Domain\Model\Subcontractor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Subcontractor>
 */
class SubcontractorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subcontractor::class);
    }

    /**
     * Find all subcontractors for a specific organization.
     *
     * @return array<int, Subcontractor>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        /** @var array<int, Subcontractor> */
        $result = $this->createQueryBuilder('s')
            ->join('s.projects', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find one subcontractor by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?Subcontractor
    {
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->join('s.projects', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Subcontractor ? $result : null;
    }

    public function findOneForProjectByLicense(\App\Project\Domain\Model\Project $project, string $license): ?Subcontractor
    {
        $result = $this->createQueryBuilder('s')
            ->join('s.projects', 'p')
            ->andWhere('p.id = :projectId')
            ->andWhere('s.rbqLicenseNumber = :license')
            ->setParameter('projectId', $project->getId(), 'uuid')
            ->setParameter('license', $license)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Subcontractor ? $result : null;
    }

    public function findOneForProjectById(\App\Project\Domain\Model\Project $project, Uuid $id): ?Subcontractor
    {
        $result = $this->createQueryBuilder('s')
            ->join('s.projects', 'p')
            ->andWhere('p.id = :projectId')
            ->andWhere('s.id = :id')
            ->setParameter('projectId', $project->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Subcontractor ? $result : null;
    }

    /** @return list<Subcontractor> */
    public function findExpiringOnOrBefore(\DateTimeImmutable $date): array
    {
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.expiryDate IS NOT NULL')
            ->andWhere('s.expiryDate <= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        if (!is_array($result)) {
            return [];
        }

        $subcontractors = [];
        foreach ($result as $subcontractor) {
            if ($subcontractor instanceof Subcontractor) {
                $subcontractors[] = $subcontractor;
            }
        }

        return $subcontractors;
    }
}
