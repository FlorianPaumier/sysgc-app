<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SafetyLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SafetyLog>
 */
class SafetyLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SafetyLog::class);
    }

    /**
     * Find all safety logs for a specific organization.
     *
     * @return array<int, SafetyLog>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        /** @var array<int, SafetyLog> */
        return $this->createQueryBuilder('s')
            ->join('s.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one safety log by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?SafetyLog
    {
        /** @var ?SafetyLog */
        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->join('s.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
