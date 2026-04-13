<?php

namespace App\Tests\UnitTests;

use App\Entity\Booking;
use App\Entity\BookingAllocation;
use App\Entity\IdempotencyKey;
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
 * BookingIdempotencyTest — unit tests for BookingService business logic.
 *
 * All Doctrine dependencies are mocked so no database is required.
 * Tests cover idempotency early-return, missing-resource guard,
 * unavailable-resource guard, and invalid-date-range guard.
 */
class BookingIdempotencyTest extends TestCase
{
    private EntityManagerInterface $em;
    private BookingRepository $bookingRepo;
    private ResourceRepository $resourceRepo;
    private UserRepository $userRepo;
    private IdempotencyKeyRepository $idempRepo;
    private AuditService $auditService;
    private BookingService $service;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->bookingRepo  = $this->createMock(BookingRepository::class);
        $this->resourceRepo = $this->createMock(ResourceRepository::class);
        $this->userRepo     = $this->createMock(UserRepository::class);
        $this->idempRepo    = $this->createMock(IdempotencyKeyRepository::class);
        $this->auditService = $this->createMock(AuditService::class);

        // flush(), persist(), log() return void — no willReturn needed for void methods.

        $this->service = new BookingService(
            $this->em,
            $this->bookingRepo,
            $this->resourceRepo,
            $this->userRepo,
            $this->idempRepo,
            $this->auditService,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a minimal User mock with the given ID.
     */
    private function mockUser(int $id, string $role = 'ROLE_EMPLOYEE'): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRoles')->willReturn([$role]);
        $user->method('getFirstName')->willReturn('Jane');
        $user->method('getLastName')->willReturn('Doe');
        return $user;
    }

    /**
     * Create a Booking mock that reports the given ID.
     */
    private function mockBooking(int $id): Booking
    {
        $booking = $this->createMock(Booking::class);
        $booking->method('getId')->willReturn($id);
        return $booking;
    }

    /**
     * Create an IdempotencyKey mock wired to a specific entity.
     */
    private function mockIdempKey(string $type, int $entityId): IdempotencyKey
    {
        $key = $this->createMock(IdempotencyKey::class);
        $key->method('getEntityType')->willReturn($type);
        $key->method('getEntityId')->willReturn($entityId);
        return $key;
    }

    // -------------------------------------------------------------------------
    // Test 1 — Same clientKey within 10 min returns existing Booking
    // -------------------------------------------------------------------------

    /**
     * When findValidKey() returns a valid IdempotencyKey pointing to Booking #42,
     * createBooking() must return that same Booking without touching the EntityManager.
     */
    public function testSameClientKeyReturnsExistingBooking(): void
    {
        $existingBooking = $this->mockBooking(42);
        $idempKey        = $this->mockIdempKey('Booking', 42);

        $this->idempRepo
            ->expects($this->once())
            ->method('findValidKey')
            ->with('idempotent-key-abc')
            ->willReturn($idempKey);

        $this->bookingRepo
            ->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($existingBooking);

        // EntityManager::persist must NOT be called — no new booking is created.
        $this->em
            ->expects($this->never())
            ->method('persist');

        $requester = $this->mockUser(1);

        $result = $this->service->createBooking(
            $requester,
            99, // resourceId irrelevant — idempotency guard fires first
            [
                'startDatetime' => '+1 day',
                'endDatetime'   => '+2 days',
                'purpose'       => 'Team offsite',
            ],
            [],
            'idempotent-key-abc',
        );

        $this->assertSame(42, $result->getId(), 'createBooking must return the existing booking when clientKey matches');
    }

    // -------------------------------------------------------------------------
    // Test 2 — null clientKey skips idempotency lookup entirely
    // -------------------------------------------------------------------------

    /**
     * When clientKey is null, findValidKey() must never be invoked.
     * We terminate the test early by making resourceRepo->find return null,
     * which causes the service to throw — that is the expected path here.
     */
    public function testNullClientKeySkipsIdempotencyLookup(): void
    {
        // findValidKey must NOT be called at all.
        $this->idempRepo
            ->expects($this->never())
            ->method('findValidKey');

        // Return null resource to trigger an early InvalidArgumentException.
        $this->resourceRepo
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resource not found');

        $requester = $this->mockUser(1);

        $this->service->createBooking(
            $requester,
            1,
            [
                'startDatetime' => '+1 day',
                'endDatetime'   => '+2 days',
                'purpose'       => 'No key test',
            ],
            [],
            null, // <-- no clientKey
        );
    }

    // -------------------------------------------------------------------------
    // Test 3 — Resource not found throws InvalidArgumentException
    // -------------------------------------------------------------------------

    /**
     * When the requested resource does not exist (repository returns null),
     * createBooking() must throw InvalidArgumentException with 'Resource not found'.
     */
    public function testResourceNotFoundThrowsException(): void
    {
        // No valid idempotency key — proceed to resource lookup.
        $this->idempRepo
            ->method('findValidKey')
            ->willReturn(null);

        $this->resourceRepo
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resource not found');

        $requester = $this->mockUser(1);

        $this->service->createBooking(
            $requester,
            999,
            [
                'startDatetime' => '+1 day',
                'endDatetime'   => '+2 days',
                'purpose'       => 'Resource not found test',
            ],
            [],
            'key-with-no-match',
        );
    }

    // -------------------------------------------------------------------------
    // Test 4 — Unavailable resource throws InvalidArgumentException
    // -------------------------------------------------------------------------

    /**
     * When the resource exists but isAvailable() returns false, createBooking()
     * must throw with 'Resource is not available for booking'.
     */
    public function testResourceUnavailableThrowsException(): void
    {
        $this->idempRepo
            ->method('findValidKey')
            ->willReturn(null);

        $resource = $this->createMock(Resource::class);
        $resource->method('isAvailable')->willReturn(false);

        $this->resourceRepo
            ->method('find')
            ->willReturn($resource);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resource is not available for booking');

        $requester = $this->mockUser(1);

        $this->service->createBooking(
            $requester,
            5,
            [
                'startDatetime' => '+1 day',
                'endDatetime'   => '+2 days',
                'purpose'       => 'Unavailable resource test',
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Test 5 — End datetime not after start datetime throws
    // -------------------------------------------------------------------------

    /**
     * When endDatetime <= startDatetime, createBooking() must throw
     * 'End datetime must be after start datetime' before touching the DB.
     */
    public function testInvalidDateRangeThrowsException(): void
    {
        $this->idempRepo
            ->method('findValidKey')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('End datetime must be after start datetime');

        $requester = $this->mockUser(1);

        $this->service->createBooking(
            $requester,
            1,
            [
                'startDatetime' => '2030-06-15 14:00:00',
                'endDatetime'   => '2030-06-15 10:00:00', // end is before start
                'purpose'       => 'Date range test',
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Test 6 — cancelBooking: requester can cancel own active booking
    // -------------------------------------------------------------------------

    /**
     * cancelBooking() must succeed (no exception) when the requesting user is
     * the booking's requester and the booking status is 'active'.
     */
    public function testCancelOwnActiveBookingSucceeds(): void
    {
        $requester = $this->mockUser(7);

        // Build a real (non-mock) Booking so setStatus actually works.
        $booking = new Booking();
        $booking->setRequester($requester);
        $booking->setStatus('active');

        // Expect flush() to be called once when the status is saved.
        $this->em
            ->expects($this->once())
            ->method('flush');

        $this->service->cancelBooking($booking, $requester);

        $this->assertSame('cancelled', $booking->getStatus(), 'Booking status must be "cancelled" after cancelBooking()');
    }

    // -------------------------------------------------------------------------
    // Test 7 — cancelBooking: another user cannot cancel someone else's booking
    // -------------------------------------------------------------------------

    /**
     * cancelBooking() must throw 'You can only cancel your own bookings' when the
     * actor is not the booking's requester.
     */
    public function testCancelOtherUsersBookingThrows(): void
    {
        $owner = $this->mockUser(10);
        $other = $this->mockUser(20);

        $booking = new Booking();
        $booking->setRequester($owner);
        $booking->setStatus('active');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You can only cancel your own bookings');

        $this->service->cancelBooking($booking, $other);
    }

    // -------------------------------------------------------------------------
    // Test 8 — cancelBooking: already-cancelled booking cannot be cancelled again
    // -------------------------------------------------------------------------

    /**
     * cancelBooking() must throw 'Only active bookings can be cancelled' when the
     * booking status is already 'cancelled'.
     */
    public function testCancelAlreadyCancelledBookingThrows(): void
    {
        $requester = $this->mockUser(5);

        $booking = new Booking();
        $booking->setRequester($requester);
        $booking->setStatus('cancelled');

        // The strict state machine rejects cancelled -> cancelled.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid booking transition');

        $this->service->cancelBooking($booking, $requester);
    }
}
