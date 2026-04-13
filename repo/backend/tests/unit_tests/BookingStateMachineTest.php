<?php

namespace App\Tests\UnitTests;

use App\Entity\Booking;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Strict state-machine tests for BookingService::transitionTo().
 *
 * Allowed transitions:
 *   pending   -> active, cancelled
 *   active    -> completed, cancelled
 *   completed -> (terminal)
 *   cancelled -> (terminal)
 */
class BookingStateMachineTest extends TestCase
{
    private BookingService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->service = new BookingService(
            $em,
            $this->createMock(BookingRepository::class),
            $this->createMock(ResourceRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(IdempotencyKeyRepository::class),
            $this->createMock(AuditService::class),
        );
    }

    private function makeUser(int $id = 1, string $role = 'ROLE_EMPLOYEE'): User
    {
        $u = new User();
        $u->setUsername("u$id");
        $u->setEmail("u$id@t.invalid");
        $u->setFirstName('F');
        $u->setLastName('L');
        $u->setRole($role);
        $u->setIsActive(true);
        $u->setPasswordHash('x');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($u, $id);
        return $u;
    }

    private function makeBooking(string $status = BookingService::STATE_ACTIVE): Booking
    {
        $b = new Booking();
        $b->setRequester($this->makeUser(1));
        $resource = new Resource();
        $resource->setName('R');
        $resource->setType('vehicle');
        $resource->setCostCenter('CC-1');
        $resource->setCapacity(1);
        $resource->setIsAvailable(true);
        $b->setResource($resource);
        $b->setStartDatetime(new \DateTimeImmutable('+1 hour'));
        $b->setEndDatetime(new \DateTimeImmutable('+2 hours'));
        $b->setPurpose('t');
        $b->setStatus($status);
        return $b;
    }

    public function testActiveCanTransitionToCancelled(): void
    {
        $b = $this->makeBooking(BookingService::STATE_ACTIVE);
        $this->service->transitionTo($b, BookingService::STATE_CANCELLED, $this->makeUser());
        $this->assertSame(BookingService::STATE_CANCELLED, $b->getStatus());
    }

    public function testActiveCanTransitionToCompleted(): void
    {
        $b = $this->makeBooking(BookingService::STATE_ACTIVE);
        $this->service->transitionTo($b, BookingService::STATE_COMPLETED, $this->makeUser());
        $this->assertSame(BookingService::STATE_COMPLETED, $b->getStatus());
    }

    public function testCancelledCannotTransitionBackToActive(): void
    {
        $b = $this->makeBooking(BookingService::STATE_CANCELLED);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid booking transition');
        $this->service->transitionTo($b, BookingService::STATE_ACTIVE, $this->makeUser());
    }

    public function testCompletedIsTerminal(): void
    {
        $b = $this->makeBooking(BookingService::STATE_COMPLETED);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transitionTo($b, BookingService::STATE_CANCELLED, $this->makeUser());
    }

    public function testCancelBookingRejectsNonOwner(): void
    {
        $b = $this->makeBooking(BookingService::STATE_ACTIVE);
        $intruder = $this->makeUser(99);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only cancel your own');
        $this->service->cancelBooking($b, $intruder);
    }

    public function testCancelBookingHappyPath(): void
    {
        $b = $this->makeBooking(BookingService::STATE_ACTIVE);
        $this->service->cancelBooking($b, $this->makeUser(1));
        $this->assertSame(BookingService::STATE_CANCELLED, $b->getStatus());
    }

    public function testCancelAlreadyCancelledIsRejectedByStateMachine(): void
    {
        $b = $this->makeBooking(BookingService::STATE_CANCELLED);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancelBooking($b, $this->makeUser(1));
    }
}
