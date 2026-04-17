<?php

namespace App\Tests\UnitTests;

use App\Entity\ApprovalStep;
use App\Entity\ExceptionRequest;
use App\Entity\User;
use App\Repository\ApprovalStepRepository;
use App\Repository\ExceptionRequestRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\UserRepository;
use App\Service\ApprovalWorkflowService;
use App\Service\AuditService;
use App\Service\SlaService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * ApprovalWorkflowServiceTest — unit tests for approval workflow business logic.
 * Uses mocks for all DB dependencies; only pure service logic is exercised.
 */
class ApprovalWorkflowServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ExceptionRequestRepository $requestRepo;
    private ApprovalStepRepository $stepRepo;
    private IdempotencyKeyRepository $idempotencyRepo;
    private UserRepository $userRepo;
    private SlaService $slaService;
    private AuditService $auditService;
    private ApprovalWorkflowService $service;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->requestRepo    = $this->createMock(ExceptionRequestRepository::class);
        $this->stepRepo       = $this->createMock(ApprovalStepRepository::class);
        $this->idempotencyRepo = $this->createMock(IdempotencyKeyRepository::class);
        $this->userRepo       = $this->createMock(UserRepository::class);
        $this->slaService     = $this->createMock(SlaService::class);
        $this->auditService   = $this->createMock(AuditService::class);

        $this->service = new ApprovalWorkflowService(
            $this->em,
            $this->requestRepo,
            $this->stepRepo,
            $this->idempotencyRepo,
            $this->userRepo,
            $this->slaService,
            $this->auditService,
        );
    }

    // -------------------------------------------------------------------------
    // parseDate
    // -------------------------------------------------------------------------

    public function testParseDateAcceptsIsoFormat(): void
    {
        $date = $this->service->parseDate('2026-04-17', 'startDate');
        $this->assertSame('2026-04-17', $date->format('Y-m-d'));
    }

    public function testParseDateAcceptsUsFormat(): void
    {
        $date = $this->service->parseDate('04/17/2026', 'startDate');
        $this->assertSame('2026-04-17', $date->format('Y-m-d'));
    }

    public function testParseDateThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/startDate/i');
        $this->service->parseDate('not-a-date', 'startDate');
    }

    public function testParseDateThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseDate('', 'startDate');
    }

    public function testParseDateThrowsOnNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseDate(null, 'startDate');
    }

    public function testParseDateThrowsOnNonString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseDate(12345, 'startDate');
    }

    // -------------------------------------------------------------------------
    // parseTime
    // -------------------------------------------------------------------------

    public function testParseTimeAcceptsHHMM(): void
    {
        $time = $this->service->parseTime('09:30', 'startTime');
        $this->assertSame('09:30', $time->format('H:i'));
    }

    public function testParseTimeAcceptsHHMMSS(): void
    {
        $time = $this->service->parseTime('14:00:00', 'startTime');
        $this->assertSame('14:00', $time->format('H:i'));
    }

    public function testParseTimeThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseTime('bad-time', 'startTime');
    }

    public function testParseTimeThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseTime('', 'startTime');
    }

    // -------------------------------------------------------------------------
    // approve() guards
    // -------------------------------------------------------------------------

    public function testApproveThrowsIfStepNotPending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not pending/i');

        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('APPROVED');

        $this->service->approve($step, $this->createMock(User::class));
    }

    public function testApproveThrowsIfWrongApprover(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not the assigned approver/i');

        $approver = $this->createMock(User::class);
        $approver->method('getId')->willReturn(1);

        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(2);

        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('PENDING');
        $step->method('getApprover')->willReturn($approver);

        $this->service->approve($step, $actor);
    }

    // -------------------------------------------------------------------------
    // reject() guards
    // -------------------------------------------------------------------------

    public function testRejectThrowsIfStepNotPending(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('REJECTED');

        $this->service->reject($step, $this->createMock(User::class));
    }

    public function testRejectThrowsIfWrongApprover(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not the assigned approver/i');

        $approver = $this->createMock(User::class);
        $approver->method('getId')->willReturn(1);

        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(2);

        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('PENDING');
        $step->method('getApprover')->willReturn($approver);

        $this->service->reject($step, $actor);
    }

    // -------------------------------------------------------------------------
    // withdraw() guards
    // -------------------------------------------------------------------------

    public function testWithdrawThrowsIfNotRequester(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/own requests/i');

        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn(1);

        $other = $this->createMock(User::class);
        $other->method('getId')->willReturn(2);

        $request = $this->createMock(ExceptionRequest::class);
        $request->method('getUser')->willReturn($owner);
        $request->method('getStatus')->willReturn('PENDING');

        $this->service->withdraw($request, $other);
    }

    public function testWithdrawThrowsIfAlreadyApproved(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending/i');

        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn(1);

        $request = $this->createMock(ExceptionRequest::class);
        $request->method('getUser')->willReturn($owner);
        $request->method('getStatus')->willReturn('APPROVED');

        $this->service->withdraw($request, $owner);
    }

    public function testWithdrawThrowsIfAlreadyRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn(5);

        $request = $this->createMock(ExceptionRequest::class);
        $request->method('getUser')->willReturn($owner);
        $request->method('getStatus')->willReturn('REJECTED');

        $this->service->withdraw($request, $owner);
    }

    // -------------------------------------------------------------------------
    // reassign() guards
    // -------------------------------------------------------------------------

    public function testReassignThrowsIfStepNotPending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending/i');

        $approver = $this->createMock(User::class);
        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('APPROVED');
        $step->method('getApprover')->willReturn($approver);

        $this->service->reassign($step, $this->createMock(User::class), $this->createMock(User::class));
    }

    public function testReassignThrowsIfActorUnauthorized(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not authorized/i');

        $approver = $this->createMock(User::class);
        $approver->method('getId')->willReturn(1);

        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(99);
        $actor->method('getRole')->willReturn('ROLE_EMPLOYEE');

        $step = $this->createMock(ApprovalStep::class);
        $step->method('getStatus')->willReturn('PENDING');
        $step->method('getApprover')->willReturn($approver);

        $this->service->reassign($step, $this->createMock(User::class), $actor);
    }

    // -------------------------------------------------------------------------
    // requesterReassign() guards
    // -------------------------------------------------------------------------

    public function testRequesterReassignThrowsIfNotRequester(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requester/i');

        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn(1);

        $other = $this->createMock(User::class);
        $other->method('getId')->willReturn(2);

        $request = $this->createMock(ExceptionRequest::class);
        $request->method('getUser')->willReturn($owner);

        $step = $this->createMock(ApprovalStep::class);

        $this->service->requesterReassign($request, $step, $this->createMock(User::class), $other);
    }

    // -------------------------------------------------------------------------
    // createRequest() date validation guards
    // -------------------------------------------------------------------------

    public function testCreateRequestThrowsIfEndDateBeforeStartDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/endDate.*startDate/i');

        $this->idempotencyRepo->method('findValidKey')->willReturn(null);
        $this->em->method('persist');
        $this->em->method('flush');

        $user = $this->createMock(User::class);

        $this->service->createRequest($user, 'CORRECTION', [
            'startDate' => '2026-04-17',
            'endDate'   => '2026-04-16',
            'reason'    => 'test',
        ]);
    }

    public function testCreateRequestThrowsIfDateMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->idempotencyRepo->method('findValidKey')->willReturn(null);
        $user = $this->createMock(User::class);

        $this->service->createRequest($user, 'CORRECTION', [
            'startDate' => '',
            'endDate'   => '2026-04-17',
            'reason'    => 'test',
        ]);
    }

    public function testCreateRequestThrowsIfDateInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->idempotencyRepo->method('findValidKey')->willReturn(null);
        $user = $this->createMock(User::class);

        $this->service->createRequest($user, 'CORRECTION', [
            'startDate' => 'not-a-date',
            'endDate'   => '2026-04-17',
            'reason'    => 'test',
        ]);
    }
}
