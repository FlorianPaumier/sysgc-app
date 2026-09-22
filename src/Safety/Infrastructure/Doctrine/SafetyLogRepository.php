<?php

declare(strict_types=1);

namespace App\Safety\Infrastructure\Doctrine;

use App\Project\Domain\Model\Project;
use App\Safety\Domain\Model\SafetyLog;
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

    public function findOneByProjectAndDate(Project $project, \DateTimeInterface $date): ?SafetyLog
    {
        /** @var SafetyLog|null */
        return $this->findOneBy([
            'project' => $project,
            'date' => $this->toDoctrineDate($date),
        ]);
    }

    /**
     * @return list<SafetyLog>
     */
    public function findByProject(Project $project, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = $this->createQueryBuilder('log')
            ->andWhere('log.project = :project')
            ->setParameter('project', $project)
            ->orderBy('log.date', 'DESC');

        /** @var list<SafetyLog> */
        $logs = $query->getQuery()->getResult();
        if (null === $from && null === $to) {
            return $logs;
        }

        $fromKey = $from?->format('Y-m-d');
        $toKey = $to?->format('Y-m-d');

        return array_values(array_filter($logs, static function (SafetyLog $log) use ($fromKey, $toKey): bool {
            $dateKey = $log->getDate()?->format('Y-m-d');

            return null !== $dateKey
                && (null === $fromKey || $dateKey >= $fromKey)
                && (null === $toKey || $dateKey <= $toKey);
        }));
    }

    public function save(SafetyLog $log, bool $flush = false): void
    {
        $manager = $this->getEntityManager();
        $manager->persist($log);

        if ($flush) {
            $manager->flush();
        }
    }

    private function toDoctrineDate(\DateTimeInterface $date): \DateTime
    {
        return $date instanceof \DateTime ? $date : \DateTime::createFromImmutable($date);
    }
}
