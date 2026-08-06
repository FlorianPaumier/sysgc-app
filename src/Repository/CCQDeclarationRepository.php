<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CCQDeclaration;
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
}
