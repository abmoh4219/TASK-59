<?php

namespace App\Repository;

use App\Entity\ApprovalStep;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApprovalStep>
 */
class ApprovalStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApprovalStep::class);
    }

    /**
     * Find all pending approval steps assigned to a given approver.
     *
     * @return ApprovalStep[]
     */
    public function findPendingByApprover(User $approver): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.approver = :approver')
            ->andWhere('s.status = :status')
            ->setParameter('approver', $approver)
            ->setParameter('status', 'pending')
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
