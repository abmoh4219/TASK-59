<?php

namespace App\Tests\UnitTests;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\MaskingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * AuditServiceTest — verifies the append-only audit log contract.
 *
 * KEY INVARIANT: AuditService must ONLY call persist() + flush().
 * It must NEVER call update() or remove(). This is tested by ensuring
 * no forbidden EM methods are invoked on the mock EntityManager.
 */
class AuditServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private MaskingService $maskingService;
    private AuditService $service;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->maskingService = $this->createMock(MaskingService::class);

        // MaskingService::maskForLog should pass data through for these tests
        $this->maskingService
            ->method('maskForLog')
            ->willReturnArgument(0);

        $this->service = new AuditService($this->em, $this->maskingService);
    }

    public function testLogCallsPersistAndFlush(): void
    {
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(1);
        $actor->method('getUsername')->willReturn('admin');

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(AuditLog::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->log($actor, 'CREATE', 'User', 1, null, ['username' => 'newuser']);
    }

    public function testLogNeverCallsRemove(): void
    {
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(1);
        $actor->method('getUsername')->willReturn('admin');

        $this->em->expects($this->never())->method('remove');

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->log($actor, 'DELETE_ATTEMPT', 'User', 5, ['status' => 'old'], ['status' => 'new']);
    }

    public function testLogWithNullActorDoesNotThrow(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        // Null actor (system-initiated event such as escalation)
        $this->service->log(null, 'ESCALATE', 'ApprovalStep', 42, null, ['reason' => 'SLA breach']);
        $this->assertTrue(true, 'log() with null actor must not throw');
    }

    public function testLogWithNullEntityIdDoesNotThrow(): void
    {
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(1);
        $actor->method('getUsername')->willReturn('admin');

        $this->em->method('persist');
        $this->em->method('flush');

        // entityId = null is used for bulk operations (e.g., CSV import)
        $this->service->log($actor, 'CSV_IMPORT', 'PunchEvent', null, null, ['count' => 50]);
        $this->assertTrue(true);
    }

    public function testLogWithRequestContextDoesNotThrow(): void
    {
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(1);
        $actor->method('getUsername')->willReturn('admin');

        $this->em->method('persist');
        $this->em->method('flush');

        $request = Request::create('/api/users', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->service->log($actor, 'CREATE', 'User', 1, null, ['username' => 'x'], $request);
        $this->assertTrue(true);
    }

    public function testAuditServiceHasNoUpdateOrDeleteMethod(): void
    {
        $methods = get_class_methods($this->service);
        $forbidden = array_filter($methods, function (string $m) {
            $lower = strtolower($m);
            return str_contains($lower, 'update') || str_contains($lower, 'delete') || str_contains($lower, 'remove');
        });

        $this->assertEmpty(
            $forbidden,
            'AuditService must not expose update/delete/remove methods — audit log is append-only. Found: ' . implode(', ', $forbidden)
        );
    }

    public function testLogMasksOldValues(): void
    {
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(1);
        $actor->method('getUsername')->willReturn('admin');

        $sensitiveData = ['phone' => '+15551234567', 'action' => 'UPDATE'];

        $this->maskingService
            ->expects($this->atLeast(1))
            ->method('maskForLog')
            ->willReturnArgument(0);

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->log($actor, 'UPDATE', 'User', 2, $sensitiveData, $sensitiveData);
    }
}
