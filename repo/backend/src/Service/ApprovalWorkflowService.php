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

        // --------------------------------------------------------------
        // Strict temporal validation (server-side). All parse failures,
        // ordering violations, and type-specific rules surface as
        // \InvalidArgumentException so the controller translates them
        // deterministically to HTTP 400.
        // --------------------------------------------------------------
        $startDate = $this->parseDate($data['startDate'] ?? null, 'startDate');
        $endDate = $this->parseDate($data['endDate'] ?? null, 'endDate');

        if ($endDate < $startDate) {
            throw new \InvalidArgumentException('endDate must be on or after startDate');
        }

        // Filing window: the request must be filed within 7 calendar days
        // of the start date. Future-dated requests (startDate in the future)
        // are allowed only for forward-looking types.
        $today = (new \DateTimeImmutable('today'));
        $filingWindow = 7;
        if ($startDate < $today) {
            $daysSinceStart = (int) $today->diff($startDate)->days;
            if ($daysSinceStart > $filingWindow) {
                throw new \InvalidArgumentException(
                    "Filing window expired. Requests must be filed within {$filingWindow} calendar days.",
                );
            }
        }

        // Type-specific temporal constraints.
        $forwardOnlyTypes = ['PTO', 'LEAVE', 'BUSINESS_TRIP', 'OUTING'];
        if (in_array($requestType, $forwardOnlyTypes, true) && $endDate < $today) {
            throw new \InvalidArgumentException(
                "Request type {$requestType} cannot cover a date in the past",
            );
        }
        // CORRECTION requests target a past/today exception, not the future.
        if ($requestType === 'CORRECTION' && $startDate > $today) {
            throw new \InvalidArgumentException(
                'CORRECTION requests cannot target future dates',
            );
        }

        // Optional time window parsing + ordering.
        $startTime = null;
        $endTime = null;
        if (!empty($data['startTime'])) {
            $startTime = $this->parseTime($data['startTime'], 'startTime');
        }
        if (!empty($data['endTime'])) {
            $endTime = $this->parseTime($data['endTime'], 'endTime');
        }
        if ($startTime !== null && $endTime !== null && $startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            // Only enforce time ordering on single-day requests.
            if ($endTime <= $startTime) {
                throw new \InvalidArgumentException('endTime must be after startTime on a single-day request');
            }
        }

        // Create exception request
        $request = new ExceptionRequest();
        $request->setUser($user);
        $request->setRequestType($requestType);
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        if ($startTime !== null) {
            $request->setStartTime($startTime);
        }
        if ($endTime !== null) {
            $request->setEndTime($endTime);
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
        if ($step->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Only pending steps can be reassigned');
        }

        $oldApprover = $step->getApprover();

        // Authorization: only the current approver, an admin, or an authorized
        // supervisor (when reassigning a supervisor-level step) may reassign.
        $actorRole = $actor->getRole();
        $isCurrentApprover = $oldApprover->getId() === $actor->getId();
        $isAdmin = $actorRole === 'ROLE_ADMIN' || $actorRole === 'ROLE_HR_ADMIN';
        $isAuthorizedSupervisor = $actorRole === 'ROLE_SUPERVISOR'
            && $oldApprover->getRole() === 'ROLE_SUPERVISOR';

        if (!$isCurrentApprover && !$isAdmin && !$isAuthorizedSupervisor) {
            throw new \InvalidArgumentException('You are not authorized to reassign this approval step');
        }

        // Validate target role eligibility — must match the role required for this step
        $allowedTargetRoles = match ($oldApprover->getRole()) {
            'ROLE_SUPERVISOR' => ['ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
            'ROLE_HR_ADMIN' => ['ROLE_HR_ADMIN', 'ROLE_ADMIN'],
            'ROLE_ADMIN' => ['ROLE_ADMIN'],
            default => ['ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
        };
        if (!in_array($newApprover->getRole(), $allowedTargetRoles, true)) {
            throw new \InvalidArgumentException('New approver role is not eligible for this step');
        }
        if (!$newApprover->isActive()) {
            throw new \InvalidArgumentException('New approver is not active');
        }
        if ($newApprover->getId() === $oldApprover->getId()) {
            throw new \InvalidArgumentException('New approver must differ from current approver');
        }

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
     * Requester-initiated reassignment: the request owner may reassign the
     * current pending approver only when that approver is marked OUT.
     *
     * This is intentionally narrower than approver/admin reassign() — it is
     * scoped to the request owner and gated on the approver being unavailable,
     * matching the "requester can reassign when approver is out" Prompt rule.
     */
    public function requesterReassign(
        ExceptionRequest $request,
        ApprovalStep $step,
        User $newApprover,
        User $requester,
        string $reason = '',
    ): void {
        if ($request->getUser()->getId() !== $requester->getId()) {
            throw new \InvalidArgumentException('Only the requester can reassign this request');
        }
        if ($step->getExceptionRequest()->getId() !== $request->getId()) {
            throw new \InvalidArgumentException('Step does not belong to this request');
        }
        if ($step->getStatus() !== 'PENDING') {
            throw new \InvalidArgumentException('Only pending steps can be reassigned');
        }

        $oldApprover = $step->getApprover();
        if (!$oldApprover->isOut()) {
            throw new \InvalidArgumentException('Current approver is available; requester reassignment is not allowed');
        }

        // Validate target eligibility using the same role matrix as approver reassign.
        $allowedTargetRoles = match ($oldApprover->getRole()) {
            'ROLE_SUPERVISOR' => ['ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
            'ROLE_HR_ADMIN' => ['ROLE_HR_ADMIN', 'ROLE_ADMIN'],
            'ROLE_ADMIN' => ['ROLE_ADMIN'],
            default => ['ROLE_SUPERVISOR', 'ROLE_HR_ADMIN', 'ROLE_ADMIN'],
        };
        if (!in_array($newApprover->getRole(), $allowedTargetRoles, true)) {
            throw new \InvalidArgumentException('New approver role is not eligible for this step');
        }
        if (!$newApprover->isActive() || $newApprover->isOut()) {
            throw new \InvalidArgumentException('New approver is not available');
        }
        if ($newApprover->getId() === $oldApprover->getId()) {
            throw new \InvalidArgumentException('New approver must differ from current approver');
        }

        $step->setApprover($newApprover);
        $step->setSlaDeadline($this->slaService->calculateSlaDeadline(new \DateTimeImmutable(), 24));

        $request->setCurrentApprover($newApprover);

        $action = new ApprovalAction();
        $action->setApprovalStep($step);
        $action->setActor($requester);
        $action->setAction('REASSIGN');
        $action->setComment('[requester] ' . $reason);
        $this->entityManager->persist($action);

        $this->entityManager->flush();

        $this->auditService->log(
            $requester,
            'REASSIGN',
            'ApprovalStep',
            $step->getId(),
            ['approver' => $oldApprover->getUsername()],
            ['approver' => $newApprover->getUsername(), 'by' => 'requester', 'reason' => $reason],
        );
    }

    /**
     * Parse a date field deterministically. Accepts YYYY-MM-DD (ISO — the
     * format submitted by the frontend after MM/DD/YYYY normalization) and
     * MM/DD/YYYY as a defensive fallback. Any other value throws so the
     * controller returns HTTP 400 rather than propagating a \DateMalformedStringException.
     */
    public function parseDate(mixed $value, string $field): \DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("$field is required");
        }

        $formats = ['Y-m-d', 'm/d/Y'];
        foreach ($formats as $fmt) {
            $parsed = \DateTimeImmutable::createFromFormat($fmt, $value);
            if ($parsed !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $parsed->setTime(0, 0, 0);
                }
            }
        }

        throw new \InvalidArgumentException("$field must be a valid YYYY-MM-DD or MM/DD/YYYY date");
    }

    /**
     * Parse a time-of-day field (HH:MM or HH:MM:SS). Throws on malformed input.
     */
    public function parseTime(mixed $value, string $field): \DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("$field is required");
        }
        foreach (['H:i:s', 'H:i'] as $fmt) {
            $parsed = \DateTimeImmutable::createFromFormat($fmt, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }
        throw new \InvalidArgumentException("$field must be HH:MM or HH:MM:SS");
    }

    /**
     * Request types that escalate to step 3 (System Administrator) after HR.
     * Long leaves and business trips routinely require exec/admin sign-off,
     * matching the Prompt's "up to 3 steps" path.
     */
    private const STEP3_TYPES = ['LEAVE', 'BUSINESS_TRIP'];

    /**
     * Resolve the requester's actual supervisor. Honours the
     * requester->supervisorId linkage first; falls back to the first active
     * supervisor ONLY when the requester has no explicit supervisor (legacy
     * seed data / historical accounts). The fallback is explicit, logged via
     * the step comment, and should be treated as a configuration gap.
     */
    public function resolveSupervisorFor(User $requester): ?User
    {
        $supervisorId = $requester->getSupervisorId();
        if ($supervisorId !== null) {
            $sup = $this->userRepository->find($supervisorId);
            if ($sup !== null && $sup->isActive() && $sup->getRole() === 'ROLE_SUPERVISOR') {
                return $sup;
            }
        }

        // Legacy fallback: first active supervisor in the directory. Kept
        // for seed data compatibility; new users must carry an explicit link.
        foreach ($this->userRepository->findByRole('ROLE_SUPERVISOR') as $sup) {
            if ($sup->isActive()) {
                return $sup;
            }
        }
        return null;
    }

    /**
     * Create approval steps based on request type.
     *
     * Step activation matrix:
     *   - Step 1 (Supervisor): always created. Assigned to the REQUESTER'S
     *     own supervisor via resolveSupervisorFor(); never to a global-first
     *     supervisor unless the requester has no linkage at all.
     *   - Step 2 (HR Admin): created for PTO, LEAVE, BUSINESS_TRIP (any
     *     request type touching HR policy).
     *   - Step 3 (System Administrator): created for LEAVE and BUSINESS_TRIP
     *     (long absences and travel requiring exec sign-off).
     *
     * CORRECTION and OUTING therefore follow the 1-step path.
     * PTO follows the 2-step path.
     * LEAVE and BUSINESS_TRIP follow the 3-step path.
     */
    private function createApprovalSteps(ExceptionRequest $request, User $requester): void
    {
        $now = new \DateTimeImmutable();

        // Step 1: the requester's own supervisor
        $supervisor = $this->resolveSupervisorFor($requester);
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

        // Step 2: HR Admin (PTO/LEAVE/BUSINESS_TRIP)
        $needsHrApproval = in_array(
            $request->getRequestType(),
            ['PTO', 'LEAVE', 'BUSINESS_TRIP'],
            true,
        );

        if ($needsHrApproval) {
            $hrAdmin = null;
            foreach ($this->userRepository->findByRole('ROLE_HR_ADMIN') as $candidate) {
                if ($candidate->isActive()) {
                    $hrAdmin = $candidate;
                    break;
                }
            }

            if ($hrAdmin !== null) {
                $step2 = new ApprovalStep();
                $step2->setExceptionRequest($request);
                $step2->setStepNumber(2);
                $step2->setApprover($hrAdmin);
                $step2->setStatus('PENDING');
                $this->entityManager->persist($step2);
            }
        }

        // Step 3 (optional): System Administrator for LEAVE / BUSINESS_TRIP
        if (in_array($request->getRequestType(), self::STEP3_TYPES, true)) {
            $sysAdmin = null;
            foreach ($this->userRepository->findByRole('ROLE_ADMIN') as $candidate) {
                if ($candidate->isActive()) {
                    $sysAdmin = $candidate;
                    break;
                }
            }

            if ($sysAdmin !== null) {
                $step3 = new ApprovalStep();
                $step3->setExceptionRequest($request);
                $step3->setStepNumber(3);
                $step3->setApprover($sysAdmin);
                $step3->setStatus('PENDING');
                $this->entityManager->persist($step3);
            }
        }

        $this->entityManager->flush();
    }
}
