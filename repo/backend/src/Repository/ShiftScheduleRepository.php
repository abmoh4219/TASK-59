<?php

namespace App\Repository;

use App\Entity\ShiftSchedule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShiftSchedule>
 */
class ShiftScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShiftSchedule::class);
    }

    /**
     * Find all shift schedules assigned to a given user.
     *
     * @return ShiftSchedule[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
