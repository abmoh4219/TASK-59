<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WorkOrder;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * WorkOrderService — facilities work order lifecycle and state machine.
 *
 * State machine: submitted → dispatched → accepted → in_progress → completed → rated
 *
 * Transition rules:
 * - submitted → dispatched: Dispatcher only
 * - dispatched → accepted: Technician only (the one assigned)
 * - accepted → in_progress: Technician only
 * - in_progress → completed: Technician only
 * - completed → rated: Employee only (the submitter, within 72 hours)
 */
class WorkOrderService
{
    private const RATING_WINDOW_HOURS = 72;

    // State machine definition: from_state => [allowed_to_states => required_role]
    private const TRANSITIONS = [
        'submitted' => ['dispatched' => 'ROLE_DISPATCHER'],
        'dispatched' => ['accepted' => 'ROLE_TECHNICIAN'],
        'accepted' => ['in_progress' => 'ROLE_TECHNICIAN'],
        'in_progress' => ['completed' => 'ROLE_TECHNICIAN'],
        'completed' => ['rated' => 'ROLE_EMPLOYEE'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkOrderRepository $workOrderRepository,
        private readonly UserRepository $userRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Create a new work order.
     *
     * @param array $data {category, priority, description, building, room}
     * @param UploadedFile[] $photos up to 5 photos
     */
    public function create(User $user, array $data, array $photos = []): WorkOrder
    {
        // Validate required fields
        $required = ['category', 'priority', 'description', 'building', 'room'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        // Validate priority
        if (!in_array($data['priority'], ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
            throw new \InvalidArgumentException('Invalid priority');
        }

        // Validate max 5 photos
        if (count($photos) > 5) {
            throw new \InvalidArgumentException('Maximum 5 photos allowed');
        }

        $workOrder = new WorkOrder();
        $workOrder->setSubmittedBy($user);
        $workOrder->setCategory($data['category']);
        $workOrder->setPriority($data['priority']);
        $workOrder->setDescription($data['description']);
        $workOrder->setBuilding($data['building']);
        $workOrder->setRoom($data['room']);
        $workOrder->setStatus('submitted');

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        // Upload photos
        foreach ($photos as $photo) {
            $this->fileUploadService->createWorkOrderPhoto($photo, $workOrder->getId(), $user);
        }

        // Audit log
        $this->auditService->log(
            $user,
            'CREATE',
            'WorkOrder',
            $workOrder->getId(),
            null,
            [
                'category' => $workOrder->getCategory(),
                'priority' => $workOrder->getPriority(),
                'building' => $workOrder->getBuilding(),
                'room' => $workOrder->getRoom(),
                'status' => 'submitted',
                'photoCount' => count($photos),
            ],
        );

        return $workOrder;
    }

    /**
     * Transition a work order to a new status. Validates state machine rules.
     */
    public function transition(
        WorkOrder $workOrder,
        string $newStatus,
        User $actor,
        ?string $notes = null,
        ?int $technicianId = null,
    ): void {
        $currentStatus = $workOrder->getStatus();

        // Check if transition is valid
        if (!isset(self::TRANSITIONS[$currentStatus])) {
            throw new \InvalidArgumentException("No transitions allowed from status '$currentStatus'");
        }

        if (!isset(self::TRANSITIONS[$currentStatus][$newStatus])) {
            throw new \InvalidArgumentException("Invalid transition: $currentStatus → $newStatus");
        }

        $requiredRole = self::TRANSITIONS[$currentStatus][$newStatus];

        // Check role permission
        if (!in_array($requiredRole, $actor->getRoles(), true) && !in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            throw new \InvalidArgumentException("Transition $currentStatus → $newStatus requires $requiredRole");
        }

        // Special validations per transition
        if ($newStatus === 'dispatched') {
            if ($technicianId !== null) {
                $technician = $this->userRepository->find($technicianId);
                if ($technician === null || $technician->getRole() !== 'ROLE_TECHNICIAN') {
                    throw new \InvalidArgumentException('Invalid technician assignment');
                }
                $workOrder->setAssignedTechnician($technician);
            }
            $workOrder->setAssignedDispatcher($actor);
            $workOrder->setDispatchedAt(new \DateTimeImmutable());
        } elseif ($newStatus === 'accepted') {
            // Verify actor is the assigned technician
            $assigned = $workOrder->getAssignedTechnician();
            if ($assigned === null || $assigned->getId() !== $actor->getId()) {
                throw new \InvalidArgumentException('Only the assigned technician can accept');
            }
            $workOrder->setAcceptedAt(new \DateTimeImmutable());
        } elseif ($newStatus === 'in_progress') {
            $workOrder->setStartedAt(new \DateTimeImmutable());
        } elseif ($newStatus === 'completed') {
            $workOrder->setCompletedAt(new \DateTimeImmutable());
            if ($notes !== null) {
                $workOrder->setCompletionNotes($notes);
            }
        }

        $oldStatus = $workOrder->getStatus();
        $workOrder->setStatus($newStatus);

        $this->entityManager->flush();

        // Audit log
        $this->auditService->log(
            $actor,
            'WORK_ORDER_TRANSITION',
            'WorkOrder',
            $workOrder->getId(),
            ['status' => $oldStatus],
            ['status' => $newStatus, 'notes' => $notes],
        );
    }

    /**
     * Rate a completed work order. Must be within 72 hours of completion.
     */
    public function rate(WorkOrder $workOrder, User $user, int $rating): void
    {
        if ($workOrder->getSubmittedBy()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Only the submitter can rate');
        }

        if ($workOrder->getStatus() !== 'completed') {
            throw new \InvalidArgumentException('Work order must be completed before rating');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        // Check 72-hour window
        $completedAt = $workOrder->getCompletedAt();
        if ($completedAt === null) {
            throw new \InvalidArgumentException('Work order has no completion time');
        }

        $hoursSinceCompletion = (new \DateTimeImmutable())->getTimestamp() - $completedAt->getTimestamp();
        $hoursSinceCompletion = (int) ($hoursSinceCompletion / 3600);

        if ($hoursSinceCompletion > self::RATING_WINDOW_HOURS) {
            throw new \InvalidArgumentException(sprintf(
                'Rating window expired (%d hours since completion, max %d hours)',
                $hoursSinceCompletion,
                self::RATING_WINDOW_HOURS,
            ));
        }

        $workOrder->setRating($rating);
        $workOrder->setRatedAt(new \DateTimeImmutable());
        $workOrder->setStatus('rated');

        $this->entityManager->flush();

        $this->auditService->log(
            $user,
            'WORK_ORDER_RATE',
            'WorkOrder',
            $workOrder->getId(),
            null,
            ['rating' => $rating],
        );
    }

    /**
     * Get work orders filtered by role.
     *
     * @return WorkOrder[]
     */
    public function getQueue(User $user, ?string $statusFilter = null): array
    {
        $qb = $this->workOrderRepository->createQueryBuilder('wo')
            ->orderBy('wo.createdAt', 'DESC');

        $role = $user->getRole();

        if ($role === 'ROLE_EMPLOYEE') {
            // Employee sees only own orders
            $qb->where('wo.submittedBy = :user')->setParameter('user', $user);
        } elseif ($role === 'ROLE_DISPATCHER') {
            // Dispatcher sees unassigned + assigned (not filtered to self)
        } elseif ($role === 'ROLE_TECHNICIAN') {
            // Technician sees orders assigned to them
            $qb->where('wo.assignedTechnician = :user')->setParameter('user', $user);
        }
        // ROLE_ADMIN and ROLE_HR_ADMIN see all

        if ($statusFilter !== null) {
            $qb->andWhere('wo.status = :status')->setParameter('status', $statusFilter);
        }

        return $qb->getQuery()->getResult();
    }
}
