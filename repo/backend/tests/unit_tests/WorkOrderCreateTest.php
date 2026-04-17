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
 * WorkOrderCreateTest — unit tests for WorkOrderService::create() and getQueue().
 * All DB dependencies are mocked; no database required.
 */
class WorkOrderCreateTest extends TestCase
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

        $this->service = new WorkOrderService(
            $this->em,
            $this->woRepo,
            $this->userRepo,
            $this->fileService,
            $this->auditService,
        );
    }

    public function testCreateWorkOrderMissingCategoryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/category/i');

        $user = $this->createMock(User::class);

        $this->service->create($user, [
            // category intentionally missing
            'priority'    => 'MEDIUM',
            'description' => 'Test work order',
            'building'    => 'Block A',
            'room'        => '101',
        ], []);
    }

    public function testCreateWorkOrderMissingDescriptionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description/i');

        $user = $this->createMock(User::class);

        $this->service->create($user, [
            'category' => 'Plumbing',
            'priority' => 'LOW',
            // description missing
            'building' => 'Block B',
            'room'     => '202',
        ], []);
    }

    public function testCreateWorkOrderWithValidDataPersists(): void
    {
        $user = $this->createMock(User::class);

        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $workOrder = $this->service->create($user, [
            'category'    => 'Electrical',
            'priority'    => 'HIGH',
            'description' => 'Short circuit in room 303',
            'building'    => 'Block C',
            'room'        => '303',
        ], []);

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertSame('submitted', $workOrder->getStatus());
        $this->assertSame('Electrical', $workOrder->getCategory());
    }

    public function testCreateWithInvalidPriorityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/priority/i');

        $user = $this->createMock(User::class);

        $this->service->create($user, [
            'category'    => 'HVAC',
            'priority'    => 'INVALID',
            'description' => 'Bad priority test',
            'building'    => 'Block D',
            'room'        => '404',
        ], []);
    }

    public function testCreateWorkOrderSetsCorrectInitialState(): void
    {
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');

        $workOrder = $this->service->create($user, [
            'category'    => 'Plumbing',
            'priority'    => 'LOW',
            'description' => 'Dripping tap in room 101',
            'building'    => 'Block A',
            'room'        => '101',
        ], []);

        $this->assertSame('submitted', $workOrder->getStatus(), 'New work orders must start as "submitted"');
        $this->assertSame('LOW', $workOrder->getPriority());
        $this->assertSame('Plumbing', $workOrder->getCategory());
        $this->assertSame('Block A', $workOrder->getBuilding());
    }

    public function testCreateAllowsUrgentPriority(): void
    {
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');

        $wo = $this->service->create($user, [
            'category'    => 'Safety',
            'priority'    => 'URGENT',
            'description' => 'Fire door jammed',
            'building'    => 'Block X',
            'room'        => 'Exit',
        ], []);

        $this->assertSame('URGENT', $wo->getPriority());
    }

    public function testCreateTooManyPhotosThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Maximum 5 photos/i');

        $user = $this->createMock(User::class);
        $photos = array_fill(0, 6, $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class));

        $this->service->create($user, [
            'category'    => 'Electrical',
            'priority'    => 'HIGH',
            'description' => 'Many photos',
            'building'    => 'Block Y',
            'room'        => '2',
        ], $photos);
    }

    public function testTechnicianTransitionRequiresAssignment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/assigned technician/i');

        $assignedTech = $this->createMock(User::class);
        $assignedTech->method('getId')->willReturn(10);
        $assignedTech->method('getRoles')->willReturn(['ROLE_TECHNICIAN']);

        $otherTech = $this->createMock(User::class);
        $otherTech->method('getId')->willReturn(11);
        $otherTech->method('getRoles')->willReturn(['ROLE_TECHNICIAN']);

        $wo = $this->getMockBuilder(WorkOrder::class)
            ->onlyMethods(['getStatus', 'getId', 'getAssignedTechnician'])
            ->getMock();
        $wo->method('getStatus')->willReturn('dispatched');
        $wo->method('getId')->willReturn(1);
        $wo->method('getAssignedTechnician')->willReturn($assignedTech);

        // Other tech (not the assigned one) tries to accept
        $this->service->transition($wo, 'accepted', $otherTech);
    }
}
