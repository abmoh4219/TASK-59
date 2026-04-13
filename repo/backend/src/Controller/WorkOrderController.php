<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkOrder;
use App\Entity\WorkOrderPhoto;
use App\Repository\WorkOrderPhotoRepository;
use App\Repository\WorkOrderRepository;
use App\Service\RateLimitService;
use App\Service\WorkOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/work-orders')]
class WorkOrderController extends AbstractController
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly WorkOrderRepository $workOrderRepository,
        private readonly WorkOrderPhotoRepository $photoRepository,
        private readonly RateLimitService $rateLimitService,
        private readonly \App\Repository\UserRepository $userRepository,
    ) {
    }

    /**
     * GET /api/work-orders/technicians — list available technicians (for dispatchers).
     */
    #[Route('/technicians', name: 'api_work_orders_technicians', methods: ['GET'], priority: 10)]
    public function listTechnicians(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Allow dispatchers, admins, and HR admins to see technician list
        $allowedRoles = ['ROLE_DISPATCHER', 'ROLE_ADMIN', 'ROLE_HR_ADMIN'];
        $userRoles = $user->getRoles();
        $allowed = false;
        foreach ($allowedRoles as $role) {
            if (in_array($role, $userRoles, true)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $technicians = $this->userRepository->findByRole('ROLE_TECHNICIAN');

        return $this->json(array_map(fn($t) => [
            'id' => $t->getId(),
            'name' => $t->getFirstName() . ' ' . $t->getLastName(),
            'isOut' => $t->isOut(),
        ], $technicians));
    }

    /**
     * POST /api/work-orders — submit a new work order (multipart with up to 5 photos).
     */
    #[Route('', name: 'api_work_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = [
            'category' => $request->request->get('category'),
            'priority' => $request->request->get('priority'),
            'description' => $request->request->get('description'),
            'building' => $request->request->get('building'),
            'room' => $request->request->get('room'),
        ];

        // Fallback to JSON body if form data is empty (for pure JSON API usage without photos)
        if (empty($data['category']) && $request->getContent()) {
            $jsonData = json_decode($request->getContent(), true);
            if (is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        }

        $photos = $request->files->get('photos') ?? [];
        if (!is_array($photos)) {
            $photos = [$photos];
        }

        try {
            $workOrder = $this->workOrderService->create($user, $data, $photos);
            return $this->json($this->serializeWorkOrder($workOrder), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/work-orders — list work orders filtered by role.
     */
    #[Route('', name: 'api_work_orders_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $status = $request->query->get('status');
        $workOrders = $this->workOrderService->getQueue($user, $status);

        return $this->json([
            'data' => array_map([$this, 'serializeWorkOrder'], $workOrders),
            'total' => count($workOrders),
        ]);
    }

    /**
     * GET /api/work-orders/{id} — detail + photos + history.
     */
    #[Route('/{id}', name: 'api_work_orders_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $workOrder = $this->workOrderRepository->find($id);
        if ($workOrder === null) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        // Employee can only see own
        if ($user->getRole() === 'ROLE_EMPLOYEE' && $workOrder->getSubmittedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Technician can only see orders assigned to them
        if ($user->getRole() === 'ROLE_TECHNICIAN') {
            $assigned = $workOrder->getAssignedTechnician();
            if ($assigned === null || $assigned->getId() !== $user->getId()) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        }

        return $this->json($this->serializeWorkOrder($workOrder, true));
    }

    /**
     * PATCH /api/work-orders/{id}/status — transition state.
     */
    #[Route('/{id}/status', name: 'api_work_orders_transition', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function transition(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $workOrder = $this->workOrderRepository->find($id);
        if ($workOrder === null) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newStatus = $data['status'] ?? null;
        $notes = $data['notes'] ?? null;
        $technicianId = $data['technicianId'] ?? null;

        if (!$newStatus) {
            return $this->json(['error' => 'status is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->workOrderService->transition($workOrder, $newStatus, $user, $notes, $technicianId);
            return $this->json($this->serializeWorkOrder($workOrder, true));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/work-orders/{id}/rate — employee rates completed work.
     */
    #[Route('/{id}/rate', name: 'api_work_orders_rate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rate(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $workOrder = $this->workOrderRepository->find($id);
        if ($workOrder === null) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $rating = (int) ($data['rating'] ?? 0);

        try {
            $this->workOrderService->rate($workOrder, $user, $rating);
            return $this->json(['message' => 'Rating submitted']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/work-orders/{id}/photos/{photoId} — serve photo file.
     */
    #[Route('/{id}/photos/{photoId}', name: 'api_work_orders_photo', methods: ['GET'], requirements: ['id' => '\d+', 'photoId' => '\d+'])]
    public function photo(int $id, int $photoId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $photo = $this->photoRepository->find($photoId);
        if ($photo === null || $photo->getWorkOrder()->getId() !== $id) {
            return $this->json(['error' => 'Photo not found'], Response::HTTP_NOT_FOUND);
        }

        $workOrder = $photo->getWorkOrder();

        // Access control — mirrors detail() rules
        if ($user->getRole() === 'ROLE_EMPLOYEE' && $workOrder->getSubmittedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }
        if ($user->getRole() === 'ROLE_TECHNICIAN') {
            $assigned = $workOrder->getAssignedTechnician();
            if ($assigned === null || $assigned->getId() !== $user->getId()) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        }

        $path = $photo->getStoredPath();
        if (!file_exists($path)) {
            return $this->json(['error' => 'Photo file missing'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path);
    }

    private function serializeWorkOrder(WorkOrder $wo, bool $includeDetails = false): array
    {
        $data = [
            'id' => $wo->getId(),
            'category' => $wo->getCategory(),
            'priority' => $wo->getPriority(),
            'description' => $wo->getDescription(),
            'building' => $wo->getBuilding(),
            'room' => $wo->getRoom(),
            'status' => $wo->getStatus(),
            'submittedByName' => $wo->getSubmittedBy()->getFirstName() . ' ' . $wo->getSubmittedBy()->getLastName(),
            'submittedById' => $wo->getSubmittedBy()->getId(),
            'assignedTechnicianName' => $wo->getAssignedTechnician()?->getFirstName() . ' ' . $wo->getAssignedTechnician()?->getLastName(),
            'assignedTechnicianId' => $wo->getAssignedTechnician()?->getId(),
            'assignedDispatcherName' => $wo->getAssignedDispatcher()?->getFirstName() . ' ' . $wo->getAssignedDispatcher()?->getLastName(),
            'rating' => $wo->getRating(),
            'completionNotes' => $wo->getCompletionNotes(),
            'createdAt' => $wo->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'dispatchedAt' => $wo->getDispatchedAt()?->format(\DateTimeInterface::ATOM),
            'acceptedAt' => $wo->getAcceptedAt()?->format(\DateTimeInterface::ATOM),
            'startedAt' => $wo->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $wo->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'ratedAt' => $wo->getRatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($includeDetails) {
            $photos = $this->photoRepository->findBy(['workOrder' => $wo]);
            $data['photos'] = array_map(fn(WorkOrderPhoto $p) => [
                'id' => $p->getId(),
                'originalFilename' => $p->getOriginalFilename(),
                'url' => "/api/work-orders/{$wo->getId()}/photos/{$p->getId()}",
            ], $photos);
        }

        return $data;
    }
}
