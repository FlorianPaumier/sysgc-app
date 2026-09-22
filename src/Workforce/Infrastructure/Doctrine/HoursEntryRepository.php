<?php

declare(strict_types=1);

namespace App\Workforce\Infrastructure\Doctrine;

use App\Compliance\Application\HoursEntryReader;
use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Model\HoursEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HoursEntry>
 */
class HoursEntryRepository extends ServiceEntityRepository implements HoursEntryReader
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HoursEntry::class);
    }

    /** @return list<HoursEntry> */
    public function findForProjectAndMonth(Project $project, int $month, int $year): iterable
    {
        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $until = $from->modify('first day of next month');

        $result = $this->createQueryBuilder('entry')
            ->andWhere('entry.project = :project')
            ->andWhere('entry.weekStart >= :from')
            ->andWhere('entry.weekStart < :until')
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('entry.weekStart', 'ASC')
            ->getQuery()
            ->getResult();

        if (!\is_array($result)) {
            throw new \UnexpectedValueException('Expected Doctrine to return an array of hours entries.');
        }

        $entries = [];
        foreach ($result as $entry) {
            if (!$entry instanceof HoursEntry) {
                throw new \UnexpectedValueException('Expected Doctrine to return only hours entries.');
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    //    /**
    //     * @return HoursEntry[] Returns an array of HoursEntry objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?HoursEntry
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
