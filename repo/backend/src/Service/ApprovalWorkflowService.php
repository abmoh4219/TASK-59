<?php

namespace App\Service;

use App\Entity\ApprovalAction;
use App\Entity\ApprovalStep;
use App\Entity\ExceptionRequest;
use App\Entity\IdempotencyKey;
use App\Entity\User;
use App\Repository\ApprovalStepRepository;
use App\Repository\ExceptionRequestRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ApprovalWorkflowService — manages exception request lifecycle and multi-level approvals.
 *
 * Workflow: create request → approval steps (up to 3) → approve/reject/escalate/withdraw
 * SLA: 24 business hours per step, auto-escalation after 2 hours overdue.
 */
class ApprovalWorkflowService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExceptionRequestRepository $exceptionRequestRepository,
        private readonly ApprovalStepRepository $approvalStepRepository,
        private readonly IdempotencyKeyRepository $idempotencyKeyRepository,
        private readonly UserRepository $userRepository,
        private readonly SlaService $slaService,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Create exception request with idempotency check and approval steps.
     */
    public function createRequest(
        User $user,
        string $requestType,
        array $data,
        ?string $clientKey = null,
    ): ExceptionRequest {
        // Idempotency check: if same clientKey within 10 minutes, return existing
        if ($clientKey !== null) {
            $existing = $this->idempotencyKeyRepository->findValidKey($clientKey);
            if ($existing !== null) {
                $request = $this->exceptionRequestRepository->find($existing->getEntityId());
                if ($request !== null) {
                    return $request;
                }
            }
        }

        // Validate 7-day filing window
        $startDate = new \DateTimeImmutable($data['startDate']);
        $daysSinceFiling = (int) (new \DateTimeImmutable())->diff($startDate)->days;
        $filingWindow = 7;
        if ($daysSinceFiling > $filingWindow) {
            throw new \InvalidArgumentException("Filing window expired. Requests must be filed within {$filingWindow} calendar days.");
        }

        // Create exception request
        $request = new ExceptionRequest();
        $request->setUser($user);
        $request->setRequestType($requestType);
        $request->setStartDate(new \DateTimeImmutable($data['startDate']));
        $request->setEndDate(new \DateTimeImmutable($data['endDate']));
        if (!empty($data['startTime'])) {
            $request->setStartTime(new \DateTimeImmutable($data['startTime']));
        }
        if (!empty($data['endTime'])) {
            $request->setEndTime(new \DateTimeImmutable($data['endTime']));
        }
        $request->setReason($data['reason'] ?? '');
        $request->setStatus('PENDING');
        $request->setStepNumber(1);
        $request->setClientKey($clientKey);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        // Create approval steps based on request type
        $this->createApprovalSteps($request, $user);

        // Store idempotency key
        if ($clientKey !== null) {
            $idempKey = new IdempotencyKey();
            $idempKey->setClientKey($clientKey);
            $idempKey->setEntityType('ExceptionRequest');
            $idempKey->setEntityId($request->getId());
            $idempKey->setExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $this->entityManager->persist($idempKey);
        }

        $this->entityManager->flush();

        // Audit log
        $this->auditService->log(
            $user,
            'CREATE',
            'ExceptionRequest',
            $request->getId(),
            null,
            ['requestType' => $requestType, 'status' => 'PENDING'],
        );

        return $request;
    }

    /**
     * Approve an approval step. Advances workflow or marks request APPROVED.
     */
    public function approve(ApprovalStep $step, User $actor, string $comment = ''): void
    {
        if ($step->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Step is not pending');
        }

        if ($step->getApprover()->getId() !== $actor->getId()) {
            throw new \InvalidArgumentException('You are not the assigned approver for this step');
        }

        $step->setStatus('APPROVED');
        $step->setActedAt(new \DateTimeImmutable());

        // Record action
        $action = new ApprovalAction();
        $action->setApprovalStep($step);
        $action->setActor($actor);
        $action->setAction('APPROVE');
        $action->setComment($comment);
        $this->entityManager->persist($action);

        $request = $step->getExceptionRequest();

        // Check if there's a next step
        $nextStep = $this->approvalStepRepository->findOneBy([
            'exceptionRequest' => $request,
            'stepNumber' => $step->getStepNumber() + 1,
        ]);

        if ($nextStep !== null) {
            // Advance to next step
            $request->setStepNumber($nextStep->getStepNumber());
            $request->setCurrentApprover($nextStep->getApprover());
            // Set SLA deadline for next step
            $nextStep->setSlaDeadline($this->slaService->calculateSlaDeadline(new \DateTimeImmutable(), 24));
        } else {
            // Final step — mark request as APPROVED
            $request->setStatus('APPROVED');
            $request->setCurrentApprover(null);
        }

        $this->entityManager->flush();

        $this->auditService->log(
            $actor,
            'APPROVE',
            'ApprovalStep',
            $step->getId(),
            ['status' => 'PENDING'],
            ['status' => 'APPROVED', 'comment' => $comment],
        );
    }

    /**
     * Reject an approval step. Marks entire request as REJECTED.
     */
    public function reject(ApprovalStep $step, User $actor, string $comment = ''): void
    {
        if ($step->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Step is not pending');
        }

        if ($step->getApprover()->getId() !== $actor->getId()) {
            throw new \InvalidArgumentException('You are not the assigned approver for this step');
        }

        $step->setStatus('REJECTED');
        $step->setActedAt(new \DateTimeImmutable());

        $action = new ApprovalAction();
        $action->setApprovalStep($step);
        $action->setActor($actor);
        $action->setAction('REJECT');
        $action->setComment($comment);
        $this->entityManager->persist($action);

        $request = $step->getExceptionRequest();
        $request->setStatus('REJECTED');
        $request->setCurrentApprover(null);

        $this->entityManager->flush();

        $this->auditService->log(
            $actor,
            'REJECT',
            'ApprovalStep',
            $step->getId(),
            ['status' => 'PENDING'],
            ['status' => 'REJECTED', 'comment' => $comment],
        );
    }

    /**
     * Escalate an overdue step to backup approver.
     */
    public function escalate(ApprovalStep $step): void
    {
        $currentApprover = $step->getApprover();
        $backup = $this->slaService->getBackupApprover($currentApprover->getId());

        if ($backup === null) {
            return; // No backup available
        }

        $step->setBackupApprover($backup);
        $step->setEscalatedAt(new \DateTimeImmutable());
        $step->setApprover($backup);

        // Update request's current approver
        $request = $step->getExceptionRequest();
        $request->setCurrentApprover($backup);

        $action = new ApprovalAction();
        $action->setApprovalStep($step);
        $action->setActor($currentApprover);
        $action->setAction('ESCALATE');
        $action->setComment('Auto-escalated due to SLA breach');
        $this->entityManager->persist($action);

        $this->entityManager->flush();

        $this->auditService->log(
            null,
            'ESCALATE',
            'ApprovalStep',
            $step->getId(),
            ['approver' => $currentApprover->getUsername()],
            ['approver' => $backup->getUsername(), 'reason' => 'SLA+2h overdue'],
        );
    }

    /**
     * Withdraw a request. Only allowed before step 1 approver acts.
     */
    public function withdraw(ExceptionRequest $request, User $user): void
    {
        if ($request->getUser()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('You can only withdraw your own requests');
        }

        if ($request->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Only pending requests can be withdrawn');
        }

        // Check step 1 not acted on
        $step1 = $this->approvalStepRepository->findOneBy([
            'exceptionRequest' => $request,
            'stepNumber' => 1,
        ]);

        if ($step1 !== null && $step1->getActedAt() !== null) {
            throw new \InvalidArgumentException('Cannot withdraw after first approver has acted');
        }

        $request->setStatus('WITHDRAWN');
        $request->setCurrentApprover(null);

        if ($step1 !== null) {
            $action = new ApprovalAction();
            $action->setApprovalStep($step1);
            $action->setActor($user);
            $action->setAction('WITHDRAW');
            $this->entityManager->persist($action);
        }

        $this->entityManager->flush();

        $this->auditService->log(
            $user,
            'WITHDRAW',
            'ExceptionRequest',
            $request->getId(),
            ['status' => 'PENDING'],
            ['status' => 'WITHDRAWN'],
        );
    }

    /**
     * Reassign an approval step to a different approver.
     */
    public function reassign(ApprovalStep $step, User $newApprover, User $actor, string $reason = ''): void
    {
        $oldApprover = $step->getApprover();

        $step->setApprover($newApprover);
        $step->setSlaDeadline($this->slaService->calculateSlaDeadline(new \DateTimeImmutable(), 24));

        $request = $step->getExceptionRequest();
        $request->setCurrentApprover($newApprover);

        $action = new ApprovalAction();
        $action->setApprovalStep($step);
        $action->setActor($actor);
        $action->setAction('REASSIGN');
        $action->setComment($reason);
        $this->entityManager->persist($action);

        $this->entityManager->flush();

        $this->auditService->log(
            $actor,
            'REASSIGN',
            'ApprovalStep',
            $step->getId(),
            ['approver' => $oldApprover->getUsername()],
            ['approver' => $newApprover->getUsername(), 'reason' => $reason],
        );
    }

    /**
     * Create approval steps based on request type.
     * Step 1: Supervisor, Step 2: HR Admin (for PTO/LEAVE), Step 3: Admin (optional)
     */
    private function createApprovalSteps(ExceptionRequest $request, User $requester): void
    {
        $now = new \DateTimeImmutable();

        // Step 1: Supervisor
        $supervisors = $this->userRepository->findByRole('ROLE_SUPERVISOR');
        $supervisor = $supervisors[0] ?? null;

        if ($supervisor === null) {
            return;
        }

        $step1 = new ApprovalStep();
        $step1->setExceptionRequest($request);
        $step1->setStepNumber(1);
        $step1->setApprover($supervisor);
        $step1->setStatus('PENDING');
        $step1->setSlaDeadline($this->slaService->calculateSlaDeadline($now, 24));
        $this->entityManager->persist($step1);

        $request->setCurrentApprover($supervisor);

        // Step 2: HR Admin (for PTO, LEAVE, and policy overrides)
        $needsHrApproval = in_array($request->getRequestType(), ['PTO', 'LEAVE', 'BUSINESS_TRIP'], true);

        if ($needsHrApproval) {
            $hrAdmins = $this->userRepository->findByRole('ROLE_HR_ADMIN');
            $hrAdmin = $hrAdmins[0] ?? null;

            if ($hrAdmin !== null) {
                $step2 = new ApprovalStep();
                $step2->setExceptionRequest($request);
                $step2->setStepNumber(2);
                $step2->setApprover($hrAdmin);
                $step2->setStatus('PENDING');
                $this->entityManager->persist($step2);
            }
        }

        $this->entityManager->flush();
    }
}
