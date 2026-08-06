<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subcontractor;
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
        return $this->createQueryBuilder('s')
            ->join('s.projects', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one subcontractor by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?Subcontractor
    {
        /** @var ?Subcontractor */
        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->join('s.projects', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
