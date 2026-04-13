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
 * Negative + positive tests for ApprovalWorkflowService::reassign() authorization.
 */
class ReassignAuthorizationTest extends TestCase
{
    private ApprovalWorkflowService $service;

    protected function setUp(): void
    {
        $this->service = new ApprovalWorkflowService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ExceptionRequestRepository::class),
            $this->createMock(ApprovalStepRepository::class),
            $this->createMock(IdempotencyKeyRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(SlaService::class),
            $this->createMock(AuditService::class),
        );
    }

    private function makeUser(int $id, string $role, bool $active = true): User
    {
        $user = new User();
        $user->setUsername("user$id");
        $user->setEmail("user$id@example.test");
        $user->setFirstName('U');
        $user->setLastName((string) $id);
        $user->setRole($role);
        $user->setIsActive($active);
        $user->setPasswordHash('x');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
        return $user;
    }

    private function makeStep(User $approver): ApprovalStep
    {
        $request = new ExceptionRequest();
        $request->setUser($this->makeUser(99, 'ROLE_EMPLOYEE'));
        $request->setRequestType('PTO');
        $request->setStartDate(new \DateTimeImmutable());
        $request->setEndDate(new \DateTimeImmutable());
        $request->setReason('test');
        $request->setStatus('PENDING');
        $request->setStepNumber(1);

        $step = new ApprovalStep();
        $step->setExceptionRequest($request);
        $step->setStepNumber(1);
        $step->setApprover($approver);
        $step->setStatus('PENDING');
        return $step;
    }

    public function testRandomEmployeeCannotReassign(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $intruder = $this->makeUser(2, 'ROLE_EMPLOYEE');
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not authorized');

        $this->service->reassign($step, $newApprover, $intruder, 'try');
    }

    public function testTechnicianCannotReassign(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $tech = $this->makeUser(4, 'ROLE_TECHNICIAN');
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reassign($step, $newApprover, $tech, 'try');
    }

    public function testCurrentApproverCanReassign(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->service->reassign($step, $newApprover, $supervisor, 'out of office');

        $this->assertSame(3, $step->getApprover()->getId());
    }

    public function testAdminCanReassign(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $admin = $this->makeUser(5, 'ROLE_ADMIN');
        $newApprover = $this->makeUser(6, 'ROLE_SUPERVISOR');

        $this->service->reassign($step, $newApprover, $admin, 'reassigning');

        $this->assertSame(6, $step->getApprover()->getId());
    }

    public function testIneligibleTargetRoleRejected(): void
    {
        $hr = $this->makeUser(1, 'ROLE_HR_ADMIN');
        $step = $this->makeStep($hr);
        $admin = $this->makeUser(5, 'ROLE_ADMIN');
        $employee = $this->makeUser(7, 'ROLE_EMPLOYEE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not eligible');

        $this->service->reassign($step, $employee, $admin, 'bad target');
    }

    public function testInactiveTargetRejected(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $admin = $this->makeUser(5, 'ROLE_ADMIN');
        $inactiveTarget = $this->makeUser(8, 'ROLE_SUPERVISOR', false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not active');

        $this->service->reassign($step, $inactiveTarget, $admin, 'inactive');
    }

    public function testRequesterReassignAllowedWhenApproverIsOut(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $supervisor->setIsOut(true);
        $step = $this->makeStep($supervisor);
        $request = $step->getExceptionRequest();
        // Align the requester id on the step's request (makeStep seeds id=99).
        $requester = $request->getUser();
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->service->requesterReassign($request, $step, $newApprover, $requester, 'approver out');

        $this->assertSame(3, $step->getApprover()->getId());
    }

    public function testRequesterReassignDeniedWhenApproverAvailable(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $supervisor->setIsOut(false);
        $step = $this->makeStep($supervisor);
        $request = $step->getExceptionRequest();
        $requester = $request->getUser();
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('available');

        $this->service->requesterReassign($request, $step, $newApprover, $requester, 'try');
    }

    public function testRequesterReassignDeniedForNonOwner(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $supervisor->setIsOut(true);
        $step = $this->makeStep($supervisor);
        $request = $step->getExceptionRequest();
        $intruder = $this->makeUser(500, 'ROLE_EMPLOYEE');
        $newApprover = $this->makeUser(3, 'ROLE_SUPERVISOR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requester');

        $this->service->requesterReassign($request, $step, $newApprover, $intruder, 'try');
    }

    public function testAlreadyActedStepCannotBeReassigned(): void
    {
        $supervisor = $this->makeUser(1, 'ROLE_SUPERVISOR');
        $step = $this->makeStep($supervisor);
        $step->setStatus('APPROVED');
        $admin = $this->makeUser(5, 'ROLE_ADMIN');
        $newApprover = $this->makeUser(6, 'ROLE_SUPERVISOR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pending');

        $this->service->reassign($step, $newApprover, $admin, 'too late');
    }
}
