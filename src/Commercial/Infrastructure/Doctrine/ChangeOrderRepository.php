<?php

declare(strict_types=1);

namespace App\Commercial\Infrastructure\Doctrine;

use App\Commercial\Domain\Model\ChangeOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ChangeOrder>
 */
class ChangeOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeOrder::class);
    }

    public function save(ChangeOrder $changeOrder, bool $flush = true): void
    {
        $this->getEntityManager()->persist($changeOrder);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all change orders for a specific organization.
     *
     * @return array<int, ChangeOrder>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        /** @var array<int, ChangeOrder> */
        return $this->createQueryBuilder('co')
            ->join('co.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one change order by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?ChangeOrder
    {
        /** @var ?ChangeOrder */
        return $this->createQueryBuilder('co')
            ->andWhere('co.id = :id')
            ->join('co.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
