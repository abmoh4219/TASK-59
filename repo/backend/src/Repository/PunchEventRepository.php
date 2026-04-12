<?php

namespace App\Repository;

use App\Entity\PunchEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PunchEvent>
 */
class PunchEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PunchEvent::class);
    }

    /**
     * Find all punch events for a user on a specific calendar date.
     *
     * @return PunchEvent[]
     */
    public function findByUserAndDate(User $user, \DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end   = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.punchedAt >= :start')
            ->andWhere('p.punchedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.punchedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
