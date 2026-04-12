<?php

namespace App\Repository;

use App\Entity\ExceptionRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExceptionRule>
 */
class ExceptionRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExceptionRule::class);
    }

    /**
     * Find all currently active exception rules.
     *
     * @return ExceptionRule[]
     */
    public function findActiveRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->orderBy('r.ruleType', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
