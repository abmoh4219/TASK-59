<?php

namespace App\Repository;

use App\Entity\FailedLoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FailedLoginAttempt>
 */
class FailedLoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FailedLoginAttempt::class);
    }

    /**
     * Count failed login attempts for a username within the last N minutes.
     */
    public function countRecentAttempts(string $username, int $minutes = 15): int
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.username = :username')
            ->andWhere('f.attemptedAt >= :since')
            ->setParameter('username', $username)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
