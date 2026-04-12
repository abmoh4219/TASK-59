<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkOrder>
 */
class WorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrder::class);
    }

    /**
     * Find work orders visible to a given role, optionally scoped to a specific user.
     *
     * @return WorkOrder[]
     */
    public function findByRole(string $role, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->where('w.requiredRole = :role')
            ->setParameter('role', $role)
            ->orderBy('w.createdAt', 'DESC');

        if ($user !== null) {
            $qb->andWhere('w.assignedTo = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
