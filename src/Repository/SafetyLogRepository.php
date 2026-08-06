<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SafetyLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SafetyLog>
 */
class SafetyLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SafetyLog::class);
    }
}
