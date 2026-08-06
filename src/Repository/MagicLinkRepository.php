<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MagicLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MagicLink>
 */
class MagicLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MagicLink::class);
    }

    /**
     * Find a non-expired magic link for a given user email + token hash.
     *
     * @param string $email     the user's email (user identifier)
     * @param string $tokenHash hash('sha256', $rawToken)
     */
    public function findValid(string $email, string $tokenHash): ?MagicLink
    {
        /** @var ?MagicLink $result */
        return $this->createQueryBuilder('m')
            ->join('m.owner', 'u')
            ->andWhere('u.email = :email')
            ->andWhere('m.tokenHash = :hash')
            ->andWhere('m.magicLinkTokenExpiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
