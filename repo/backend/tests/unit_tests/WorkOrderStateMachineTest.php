<?php

namespace App\Tests\UnitTests;

use App\Entity\User;
use App\Entity\WorkOrder;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Service\AuditService;
use App\Service\FileUploadService;
use App\Service\WorkOrderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * WorkOrderStateMachineTest — unit tests for WorkOrderService state machine logic.
 *
 * All DB dependencies are mocked. Business rules (allowed transitions, role guards,
 * rating window, rating range) are tested against the real service implementation.
 */
class WorkOrderStateMachineTest extends TestCase
{
    private EntityManagerInterface $em;
    private WorkOrderRepository $woRepo;
    private UserRepository $userRepo;
    private FileUploadService $fileService;
    private AuditService $auditService;
    private WorkOrderService $service;

    protected function setUp(): void
    {
        $this->em          = $this->createMock(EntityManagerInterface::class);
        $this->woRepo      = $this->createMock(WorkOrderRepository::class);
        $this->userRepo    = $this->createMock(UserRepository::class);
        $this->fileService = $this->createMock(FileUploadService::class);
        $this->auditService = $this->createMock(AuditService::class);

        // EntityManager::flush() is called by the service; suppress it silently.
        $this->em->method('flush')->willReturn(null);
        // AuditService::log() is called after every transition; suppress it.
        $this->auditService->method('log')->willReturn(null);

        $this->service = new WorkOrderService(
            $this->em,
            $this->woRepo,
            $this->userRepo,
            $this->fileService,
            $this->auditService,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a partial WorkOrder mock that reports the given status.
     * The mock also stores calls to setStatus/setAssignedDispatcher/setDispatchedAt etc.
     * via real method recording so the service can call them without blowing up.
     */
    private function mockWorkOrder(string $status, ?User $submitter = null, ?\DateTimeImmutable $completedAt = null): WorkOrder
    {
        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getSubmittedBy', 'getCompletedAt', 'getAssignedTechnician'])
            ->getMock();

        $wo->method('getStatus')->willReturn($status);
        $wo->method('getId')->willReturn(42);
        $wo->method('getCompletedAt')->willReturn($completedAt);

        if ($submitter !== null) {
            $wo->method('getSubmittedBy')->willReturn($submitter);
        }

        // getAssignedTechnician — default to null (no assigned tech) unless overridden
        $wo->method('getAssignedTechnician')->willReturn(null);

        return $wo;
    }

    /**
     * Build a User mock with a fixed ID and role list.
     *
     * @param string[] $roles
     */
    private function mockUser(int $id, array $roles): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRoles')->willReturn($roles);
        return $user;
    }

    // -------------------------------------------------------------------------
    // Transition: submitted → dispatched (Dispatcher only)
    // -------------------------------------------------------------------------

    /**
     * A Dispatcher actor should be allowed to move a work order from
     * 'submitted' to 'dispatched' without throwing any exception.
     */
    public function testValidTransitionSubmittedToDispatched(): void
    {
        $wo      = $this->mockWorkOrder('submitted');
        $dispatcher = $this->mockUser(10, ['ROLE_DISPATCHER']);

        // No technicianId passed — service skips the assignedTechnician lookup.
        // Expect no exception.
        $this->service->transition($wo, 'dispatched', $dispatcher);

        // If we reach this point the transition was accepted.
        $this->assertTrue(true, 'Dispatcher must be allowed to dispatch a submitted work order');
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    /**
     * Jumping directly from 'submitted' to 'completed' is not a defined
     * transition and must throw InvalidArgumentException.
     */
    public function testInvalidTransitionSubmittedToCompleted(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wo      = $this->mockWorkOrder('submitted');
        $admin   = $this->mockUser(1, ['ROLE_ADMIN']);

        $this->service->transition($wo, 'completed', $admin);
    }

    /**
     * An Employee must NOT be allowed to dispatch a work order even if the
     * target status 'dispatched' is a valid next state — role guard must fire.
     */
    public function testOnlyDispatcherCanDispatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/dispatched|ROLE_DISPATCHER/i');

        $wo       = $this->mockWorkOrder('submitted');
        $employee = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $this->service->transition($wo, 'dispatched', $employee);
    }

    /**
     * An Employee must NOT be allowed to accept a dispatched work order.
     * Only ROLE_TECHNICIAN (specifically the assigned one) is allowed.
     */
    public function testOnlyTechnicianCanAccept(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wo       = $this->mockWorkOrder('dispatched');
        $employee = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $this->service->transition($wo, 'accepted', $employee);
    }

    /**
     * Trying to transition from a terminal / unknown state must throw because
     * there are no entries in TRANSITIONS for that state.
     */
    public function testTransitionFromRatedStateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wo      = $this->mockWorkOrder('rated');
        $admin   = $this->mockUser(1, ['ROLE_ADMIN']);

        $this->service->transition($wo, 'completed', $admin);
    }

    // -------------------------------------------------------------------------
    // Rating: window & range validation
    // -------------------------------------------------------------------------

    /**
     * A rating submitted 24 hours after completion is within the 72-hour window
     * and must succeed.
     */
    public function testRatingWindowValid(): void
    {
        $completedAt = new \DateTimeImmutable('-24 hours');
        $submitter   = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getSubmittedBy', 'getCompletedAt'])
            ->getMock();
        $wo->method('getStatus')->willReturn('completed');
        $wo->method('getId')->willReturn(42);
        $wo->method('getCompletedAt')->willReturn($completedAt);
        $wo->method('getSubmittedBy')->willReturn($submitter);

        // Expect no exception — rate() should return without throwing.
        $this->service->rate($wo, $submitter, 5);

        $this->assertTrue(true, 'Rating within 72-hour window must be accepted');
    }

    /**
     * A rating submitted 100 hours after completion is outside the 72-hour window
     * and must throw with a message mentioning "Rating window expired".
     */
    public function testRatingWindowExpired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Rating window expired/i');

        $completedAt = new \DateTimeImmutable('-100 hours');
        $submitter   = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getSubmittedBy', 'getCompletedAt'])
            ->getMock();
        $wo->method('getStatus')->willReturn('completed');
        $wo->method('getId')->willReturn(42);
        $wo->method('getCompletedAt')->willReturn($completedAt);
        $wo->method('getSubmittedBy')->willReturn($submitter);

        $this->service->rate($wo, $submitter, 4);
    }

    /**
     * Rating value 0 is below the minimum of 1 and must be rejected.
     */
    public function testRatingTooLowThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Rating must be between 1 and 5/i');

        $completedAt = new \DateTimeImmutable('-1 hour');
        $submitter   = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getSubmittedBy', 'getCompletedAt'])
            ->getMock();
        $wo->method('getStatus')->willReturn('completed');
        $wo->method('getId')->willReturn(42);
        $wo->method('getCompletedAt')->willReturn($completedAt);
        $wo->method('getSubmittedBy')->willReturn($submitter);

        $this->service->rate($wo, $submitter, 0);
    }

    /**
     * Rating value 6 is above the maximum of 5 and must be rejected.
     */
    public function testRatingTooHighThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Rating must be between 1 and 5/i');

        $completedAt = new \DateTimeImmutable('-1 hour');
        $submitter   = $this->mockUser(5, ['ROLE_EMPLOYEE']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getSubmittedBy', 'getCompletedAt'])
            ->getMock();
        $wo->method('getStatus')->willReturn('completed');
        $wo->method('getId')->willReturn(42);
        $wo->method('getCompletedAt')->willReturn($completedAt);
        $wo->method('getSubmittedBy')->willReturn($submitter);

        $this->service->rate($wo, $submitter, 6);
    }

    // -------------------------------------------------------------------------
    // Assigned-technician constraint
    // -------------------------------------------------------------------------

    /**
     * The 'accepted' transition checks that the actor IS the assigned technician.
     * If a different technician (different ID) tries to accept, it must throw.
     */
    public function testOnlyAssignedTechnicianCanAccept(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/assigned technician/i');

        // Assigned technician has ID 20.
        $assignedTech = $this->mockUser(20, ['ROLE_TECHNICIAN']);

        // A different technician with ID 21 tries to accept.
        $otherTech = $this->mockUser(21, ['ROLE_TECHNICIAN']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getAssignedTechnician'])
            ->getMock();
        $wo->method('getStatus')->willReturn('dispatched');
        $wo->method('getId')->willReturn(42);
        $wo->method('getAssignedTechnician')->willReturn($assignedTech);

        $this->service->transition($wo, 'accepted', $otherTech);
    }

    /**
     * The assigned technician (same ID as actor) must be allowed to accept.
     */
    public function testAssignedTechnicianCanAccept(): void
    {
        $tech = $this->mockUser(20, ['ROLE_TECHNICIAN']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getAssignedTechnician'])
            ->getMock();
        $wo->method('getStatus')->willReturn('dispatched');
        $wo->method('getId')->willReturn(42);
        $wo->method('getAssignedTechnician')->willReturn($tech);

        $this->service->transition($wo, 'accepted', $tech);

        $this->assertTrue(true, 'Assigned technician must be allowed to accept the work order');
    }
}
