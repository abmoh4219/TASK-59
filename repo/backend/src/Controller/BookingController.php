<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ResourceRepository;
use App\Service\BookingService;
use App\Service\RateLimitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingRepository $bookingRepository,
        private readonly ResourceRepository $resourceRepository,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * GET /api/resources — list available bookable resources.
     */
    #[Route('/api/resources', name: 'api_resources_list', methods: ['GET'])]
    public function listResources(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $resources = $this->resourceRepository->findAvailable();

        return $this->json(array_map([$this, 'serializeResource'], $resources));
    }

    /**
     * GET /api/resources/{id}/availability?date=YYYY-MM-DD
     */
    #[Route('/api/resources/{id}/availability', name: 'api_resources_availability', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function availability(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $dateStr = $request->query->get('date');
        if (!$dateStr) {
            return $this->json(['error' => 'date query parameter required (YYYY-MM-DD)'], Response::HTTP_BAD_REQUEST);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date === false) {
            return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $availability = $this->bookingService->getAvailability($id, $date->setTime(0, 0, 0));
            return $this->json($availability);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/bookings — create booking (idempotent).
     */
    #[Route('/api/bookings', name: 'api_bookings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);
        }

        $resourceId = (int) ($data['resourceId'] ?? 0);
        if ($resourceId === 0) {
            return $this->json(['error' => 'resourceId is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $booking = $this->bookingService->createBooking(
                $user,
                $resourceId,
                $data,
                $data['travelers'] ?? [],
                $data['clientKey'] ?? null,
            );

            return $this->json($this->serializeBooking($booking), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/bookings — list current user's bookings.
     */
    #[Route('/api/bookings', name: 'api_bookings_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $bookings = $this->bookingRepository->findByRequester($user);

        return $this->json(array_map([$this, 'serializeBooking'], $bookings));
    }

    /**
     * GET /api/bookings/{id}
     */
    #[Route('/api/bookings/{id}', name: 'api_bookings_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $booking = $this->bookingRepository->find($id);
        if ($booking === null) {
            return $this->json(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        // Object-level access: only the requester, listed travelers, or
        // privileged admin/HR roles can view a booking detail.
        $role = $user->getRole();
        $isRequester = $booking->getRequester()->getId() === $user->getId();
        $isPrivileged = in_array($role, ['ROLE_HR_ADMIN', 'ROLE_ADMIN'], true);
        $isTraveler = false;
        foreach ((array) $booking->getAllocations() as $alloc) {
            if (is_array($alloc) && (int) ($alloc['travelerId'] ?? 0) === $user->getId()) {
                $isTraveler = true;
                break;
            }
        }
        if (!$isRequester && !$isPrivileged && !$isTraveler) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeBooking($booking));
    }

    /**
     * DELETE /api/bookings/{id} — cancel booking.
     */
    #[Route('/api/bookings/{id}', name: 'api_bookings_cancel', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $booking = $this->bookingRepository->find($id);
        if ($booking === null) {
            return $this->json(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bookingService->cancelBooking($booking, $user);
            return $this->json(['message' => 'Booking cancelled']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeResource(Resource $r): array
    {
        return [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'type' => $r->getType(),
            'costCenter' => $r->getCostCenter(),
            'capacity' => $r->getCapacity(),
            'isAvailable' => $r->isAvailable(),
            'description' => $r->getDescription(),
        ];
    }

    private function serializeBooking(Booking $b): array
    {
        return [
            'id' => $b->getId(),
            'resourceName' => $b->getResource()->getName(),
            'resourceId' => $b->getResource()->getId(),
            'startDatetime' => $b->getStartDatetime()->format(\DateTimeInterface::ATOM),
            'endDatetime' => $b->getEndDatetime()->format(\DateTimeInterface::ATOM),
            'purpose' => $b->getPurpose(),
            'status' => $b->getStatus(),
            'allocations' => $b->getAllocations(),
            'createdAt' => $b->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
