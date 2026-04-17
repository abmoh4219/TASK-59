<?php

namespace App\Tests\UnitTests;

use App\Entity\User;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AnomalyDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * AnomalyDetectionServiceTest — unit tests for lockout logic.
 * All DB dependencies are mocked; no real database required.
 */
class AnomalyDetectionServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private FailedLoginAttemptRepository $failedLoginRepo;
    private UserRepository $userRepo;
    private AnomalyDetectionService $service;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->failedLoginRepo = $this->createMock(FailedLoginAttemptRepository::class);
        $this->userRepo       = $this->createMock(UserRepository::class);

        $this->service = new AnomalyDetectionService(
            $this->em,
            $this->failedLoginRepo,
            $this->userRepo,
        );
    }

    public function testRecordFailedLoginPersistsAttempt(): void
    {
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(1);
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $this->service->recordFailedLogin('employee', '127.0.0.1');
        $this->assertTrue(true, 'recordFailedLogin must persist the attempt');
    }

    public function testRecordFailedLoginLocksAccountAfterFiveAttempts(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('setLockedUntil')
            ->with($this->callback(fn($dt) => $dt instanceof \DateTimeImmutable && $dt > new \DateTimeImmutable()));

        $this->userRepo->method('findActiveByUsername')->willReturn($user);
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(5);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->recordFailedLogin('employee', '127.0.0.1');
    }

    public function testRecordFailedLoginDoesNotLockBeforeThreshold(): void
    {
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(4);
        $this->userRepo->expects($this->never())->method('findActiveByUsername');
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->recordFailedLogin('employee', '127.0.0.1');
        $this->assertTrue(true, '4 attempts must not trigger lock');
    }

    public function testRecordFailedLoginLocksOnExactlyFive(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('setLockedUntil');

        $this->userRepo->method('findActiveByUsername')->willReturn($user);
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(5);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->recordFailedLogin('employee', '127.0.0.1');
    }

    public function testRecordFailedLoginWithUnknownUserDoesNotThrow(): void
    {
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(6);
        $this->userRepo->method('findActiveByUsername')->willReturn(null);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->recordFailedLogin('ghost', '10.0.0.1');
        $this->assertTrue(true, 'Unknown username must not throw');
    }

    public function testIsLockedOutReturnsTrueWhenLocked(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getLockedUntil')->willReturn(new \DateTimeImmutable('+15 minutes'));
        $this->userRepo->method('findActiveByUsername')->willReturn($user);

        $this->assertTrue($this->service->isLockedOut('employee'));
    }

    public function testIsLockedOutReturnsFalseWhenNotLocked(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getLockedUntil')->willReturn(null);
        $this->userRepo->method('findActiveByUsername')->willReturn($user);

        $this->assertFalse($this->service->isLockedOut('employee'));
    }

    public function testIsLockedOutReturnsFalseWhenUserNotFound(): void
    {
        $this->userRepo->method('findActiveByUsername')->willReturn(null);
        $this->assertFalse($this->service->isLockedOut('nonexistent'));
    }

    public function testIsLockedOutReturnsFalseWhenLockExpired(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getLockedUntil')->willReturn(new \DateTimeImmutable('-1 minute'));
        $this->userRepo->method('findActiveByUsername')->willReturn($user);

        $this->assertFalse($this->service->isLockedOut('employee'));
    }

    public function testRecordFailedLoginAboveThresholdAlsoLocks(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('setLockedUntil');

        $this->userRepo->method('findActiveByUsername')->willReturn($user);
        $this->failedLoginRepo->method('countRecentAttempts')->willReturn(10);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->recordFailedLogin('employee', '127.0.0.1');
    }
}
