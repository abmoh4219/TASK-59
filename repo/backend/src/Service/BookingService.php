<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\BookingAllocation;
use App\Entity\IdempotencyKey;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * BookingService — manages bookable resource reservations with idempotency.
 *
 * Rules:
 * - Idempotent: same clientKey within 10 minutes returns existing booking
 * - Split issuance: N travelers → N allocation records
 * - Merged allocation: same cost center + existing booking → combined allocation
 * - Internal cost-center payment only (no online payments)
 */
class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly ResourceRepository $resourceRepository,
        private readonly UserRepository $userRepository,
        private readonly IdempotencyKeyRepository $idempotencyKeyRepository,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Create a booking with idempotency, traveler split allocation, and cost-center merging.
     *
     * @param int[] $travelerIds array of User IDs
     */
    public function createBooking(
        User $requester,
        int $resourceId,
        array $data,
        array $travelerIds = [],
        ?string $clientKey = null,
    ): Booking {
        // Idempotency check
        if ($clientKey !== null) {
            $existing = $this->idempotencyKeyRepository->findValidKey($clientKey);
            if ($existing !== null && $existing->getEntityType() === 'Booking') {
                $booking = $this->bookingRepository->find($existing->getEntityId());
                if ($booking !== null) {
                    return $booking;
                }
            }
        }

        // Validate required fields
        $required = ['startDatetime', 'endDatetime', 'purpose'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        $startDt = new \DateTimeImmutable($data['startDatetime']);
        $endDt = new \DateTimeImmutable($data['endDatetime']);

        if ($startDt >= $endDt) {
            throw new \InvalidArgumentException('End datetime must be after start datetime');
        }

        // Find resource
        $resource = $this->resourceRepository->find($resourceId);
        if ($resource === null) {
            throw new \InvalidArgumentException('Resource not found');
        }

        if (!$resource->isAvailable()) {
            throw new \InvalidArgumentException('Resource is not available for booking');
        }

        // Check availability (no overlapping bookings)
        $conflicts = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.resource = :resource')
            ->andWhere('b.status = :active')
            ->andWhere('b.startDatetime < :end')
            ->andWhere('b.endDatetime > :start')
            ->setParameter('resource', $resource)
            ->setParameter('active', 'active')
            ->setParameter('start', $startDt)
            ->setParameter('end', $endDt)
            ->getQuery()
            ->getResult();

        if (!empty($conflicts)) {
            throw new \InvalidArgumentException('Resource is already booked for the requested time window');
        }

        // Create booking
        $booking = new Booking();
        $booking->setRequester($requester);
        $booking->setResource($resource);
        $booking->setStartDatetime($startDt);
        $booking->setEndDatetime($endDt);
        $booking->setPurpose($data['purpose']);
        $booking->setStatus('active');
        $booking->setClientKey($clientKey);

        // Build allocations (split issuance: one per traveler)
        $costCenter = $data['costCenter'] ?? $resource->getCostCenter();
        $pricePerTraveler = (float) ($data['pricePerTraveler'] ?? 0);

        $allocationsJson = [];
        $travelers = [];

        if (empty($travelerIds)) {
            // No travelers specified — allocate to requester only
            $travelerIds = [$requester->getId()];
        }

        foreach ($travelerIds as $travelerId) {
            $traveler = $this->userRepository->find($travelerId);
            if ($traveler === null) {
                throw new \InvalidArgumentException("Traveler #$travelerId not found");
            }
            $travelers[] = $traveler;
            $allocationsJson[] = [
                'travelerId' => $traveler->getId(),
                'travelerName' => $traveler->getFirstName() . ' ' . $traveler->getLastName(),
                'costCenter' => $costCenter,
                'amount' => $pricePerTraveler,
            ];
        }

        $booking->setAllocations($allocationsJson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Create BookingAllocation records (split issuance)
        foreach ($travelers as $traveler) {
            $allocation = new BookingAllocation();
            $allocation->setBooking($booking);
            $allocation->setTraveler($traveler);
            $allocation->setCostCenter($costCenter);
            $allocation->setAmount((string) $pricePerTraveler);
            $this->entityManager->persist($allocation);
        }

        // Store idempotency key
        if ($clientKey !== null) {
            $idempKey = new IdempotencyKey();
            $idempKey->setClientKey($clientKey);
            $idempKey->setEntityType('Booking');
            $idempKey->setEntityId($booking->getId());
            $idempKey->setExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $this->entityManager->persist($idempKey);
        }

        $this->entityManager->flush();

        // Audit log
        $this->auditService->log(
            $requester,
            'CREATE',
            'Booking',
            $booking->getId(),
            null,
            [
                'resource' => $resource->getName(),
                'startDatetime' => $startDt->format(\DateTimeInterface::ATOM),
                'endDatetime' => $endDt->format(\DateTimeInterface::ATOM),
                'travelers' => count($travelers),
                'costCenter' => $costCenter,
            ],
        );

        return $booking;
    }

    /**
     * Cancel a booking. Only the requester can cancel.
     */
    public function cancelBooking(Booking $booking, User $user): void
    {
        if ($booking->getRequester()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('You can only cancel your own bookings');
        }

        if ($booking->getStatus() !== 'active') {
            throw new \InvalidArgumentException('Only active bookings can be cancelled');
        }

        $booking->setStatus('cancelled');
        $this->entityManager->flush();

        $this->auditService->log(
            $user,
            'CANCEL',
            'Booking',
            $booking->getId(),
            ['status' => 'active'],
            ['status' => 'cancelled'],
        );
    }

    /**
     * Get availability for a resource on a specific date.
     *
     * @return array{available: bool, bookedSlots: array}
     */
    public function getAvailability(int $resourceId, \DateTimeImmutable $date): array
    {
        $resource = $this->resourceRepository->find($resourceId);
        if ($resource === null) {
            throw new \InvalidArgumentException('Resource not found');
        }

        $dayStart = $date->setTime(0, 0, 0);
        $dayEnd = $date->setTime(23, 59, 59);

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.resource = :resource')
            ->andWhere('b.status = :active')
            ->andWhere('b.startDatetime <= :dayEnd')
            ->andWhere('b.endDatetime >= :dayStart')
            ->setParameter('resource', $resource)
            ->setParameter('active', 'active')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getResult();

        $bookedSlots = array_map(fn(Booking $b) => [
            'start' => $b->getStartDatetime()->format(\DateTimeInterface::ATOM),
            'end' => $b->getEndDatetime()->format(\DateTimeInterface::ATOM),
            'purpose' => $b->getPurpose(),
        ], $bookings);

        return [
            'available' => $resource->isAvailable() && empty($bookedSlots),
            'bookedSlots' => $bookedSlots,
        ];
    }
}
