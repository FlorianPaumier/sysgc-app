<?php

declare(strict_types=1);

namespace App\Compliance\Infrastructure\Doctrine;

use App\Compliance\Domain\Model\CCQDeclaration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<CCQDeclaration>
 */
class CCQDeclarationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CCQDeclaration::class);
    }

    /**
     * Find all CCQ declarations for a specific organization.
     *
     * @return array<int, CCQDeclaration>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        /** @var array<int, CCQDeclaration> */
        return $this->createQueryBuilder('c')
            ->join('c.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->orderBy('c.periodYear', 'DESC')
            ->addOrderBy('c.periodMonth', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one CCQ declaration by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?CCQDeclaration
    {
        /** @var ?CCQDeclaration */
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->join('c.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsSubmittedForProjectPeriod(CCQDeclaration $declaration): bool
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.project = :project')
            ->andWhere('c.periodMonth = :month')
            ->andWhere('c.periodYear = :year')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('project', $declaration->getProject())
            ->setParameter('month', $declaration->getPeriodMonth())
            ->setParameter('year', $declaration->getPeriodYear())
            ->setParameter('statuses', ['exported', 'filed', 'accepted', 'rejected', 'submitted'])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
