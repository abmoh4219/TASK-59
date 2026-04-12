<?php

namespace App\Repository;

use App\Entity\IdempotencyKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdempotencyKey>
 */
class IdempotencyKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdempotencyKey::class);
    }

    /**
     * Find a valid (non-expired) idempotency key by its client-supplied key string.
     */
    public function findValidKey(string $clientKey): ?IdempotencyKey
    {
        return $this->createQueryBuilder('k')
            ->where('k.clientKey = :clientKey')
            ->andWhere('k.expiresAt > :now')
            ->setParameter('clientKey', $clientKey)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
