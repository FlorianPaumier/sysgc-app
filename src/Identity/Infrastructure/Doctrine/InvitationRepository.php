<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Model\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Invitation> */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findAcceptableByRawToken(#[\SensitiveParameter] string $rawToken, ?\DateTimeImmutable $now = null): ?Invitation
    {
        $invitation = $this->createQueryBuilder('invitation')
            ->andWhere('invitation.tokenHash = :tokenHash')
            ->andWhere('invitation.acceptedAt IS NULL')
            ->andWhere('invitation.revokedAt IS NULL')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('tokenHash', hash('sha256', $rawToken))
            ->setParameter('now', $now ?? new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        return $invitation instanceof Invitation ? $invitation : null;
    }
}
