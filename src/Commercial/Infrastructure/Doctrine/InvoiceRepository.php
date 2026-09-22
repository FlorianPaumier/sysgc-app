<?php

declare(strict_types=1);

namespace App\Commercial\Infrastructure\Doctrine;

use App\Commercial\Domain\Model\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice, bool $flush = true): void
    {
        $this->getEntityManager()->persist($invoice);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all invoices for a specific organization.
     *
     * @return array<int, Invoice>
     */
    public function findAllForOrganization(Uuid $orgId): array
    {
        /** @var array<int, Invoice> */
        return $this->createQueryBuilder('i')
            ->join('i.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one invoice by ID scoped to an organization.
     */
    public function findOneForOrganization(Uuid $id, Uuid $orgId): ?Invoice
    {
        /** @var ?Invoice */
        return $this->createQueryBuilder('i')
            ->andWhere('i.id = :id')
            ->join('i.project', 'p')
            ->join('p.organization', 'o')
            ->andWhere('o.id = :orgId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('orgId', $orgId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
