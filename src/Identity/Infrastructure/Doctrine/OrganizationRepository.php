<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Model\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function findOneByStripeCustomerId(string $stripeCustomerId): ?Organization
    {
        return $this->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }

    /** @return list<Organization> */
    public function findDueForPurge(\DateTimeImmutable $now): array
    {
        /** @var list<Organization> $organizations */
        $organizations = $this->createQueryBuilder('organization')
            ->andWhere('organization.deletionAt IS NOT NULL')
            ->andWhere('organization.deletionAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $organizations;
    }

    //    /**
    //     * @return Organization[] Returns an array of Organization objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }
    //    public function findOneBySomeField($value): ?Organization
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
