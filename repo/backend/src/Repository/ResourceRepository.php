<?php

namespace App\Repository;

use App\Entity\Resource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resource>
 */
class ResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    /**
     * Find all resources currently marked as available.
     *
     * @return Resource[]
     */
    public function findAvailable(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isAvailable = true')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
