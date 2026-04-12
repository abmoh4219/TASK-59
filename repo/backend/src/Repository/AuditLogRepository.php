<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * APPEND-ONLY: no update or delete operations permitted.
 * This repository only provides read methods.
 *
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find paginated audit logs with optional filters.
     * READ-ONLY method — no mutations.
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $entityType = null,
        ?string $actorUsername = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($entityType !== null) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($actorUsername !== null) {
            $qb->andWhere('a.actorUsername = :actor')
                ->setParameter('actor', $actorUsername);
        }

        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')
                ->setParameter('to', $to);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total audit logs with optional filters.
     * READ-ONLY method — no mutations.
     */
    public function countFiltered(
        ?string $entityType = null,
        ?string $actorUsername = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($entityType !== null) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($actorUsername !== null) {
            $qb->andWhere('a.actorUsername = :actor')
                ->setParameter('actor', $actorUsername);
        }

        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // APPEND-ONLY: no update or delete operations permitted.
    // Do NOT add any methods that modify or remove AuditLog records.
}
