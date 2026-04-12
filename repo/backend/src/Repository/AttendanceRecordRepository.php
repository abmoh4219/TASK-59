<?php

namespace App\Repository;

use App\Entity\AttendanceRecord;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendanceRecord>
 */
class AttendanceRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceRecord::class);
    }

    /**
     * Find attendance records for a user on a specific calendar date.
     *
     * @return AttendanceRecord[]
     */
    public function findByUserAndDate(User $user, \DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end   = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
