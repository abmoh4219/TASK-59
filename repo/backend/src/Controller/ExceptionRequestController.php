<?php

namespace App\Controller;

use App\Entity\ExceptionRequest;
use App\Entity\User;
use App\Repository\ApprovalStepRepository;
use App\Repository\ExceptionRequestRepository;
use App\Service\ApprovalWorkflowService;
use App\Service\RateLimitService;
use App\Service\SlaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/requests')]
class ExceptionRequestController extends AbstractController
{
    public function __construct(
        private readonly ExceptionRequestRepository $requestRepository,
        private readonly ApprovalStepRepository $stepRepository,
        private readonly ApprovalWorkflowService $workflowService,
        private readonly SlaService $slaService,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * POST /api/requests — create exception request (idempotent by clientKey).
     */
    #[Route('', name: 'api_requests_create', methods: ['POST'])]
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

        $required = ['requestType', 'startDate', 'endDate', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Missing required field: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $validTypes = ['CORRECTION', 'PTO', 'LEAVE', 'BUSINESS_TRIP', 'OUTING'];
        if (!in_array($data['requestType'], $validTypes, true)) {
            return $this->json(['error' => 'Invalid request type'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $excRequest = $this->workflowService->createRequest(
                $user,
                $data['requestType'],
                $data,
                $data['clientKey'] ?? null,
            );

            return $this->json($this->serializeRequest($excRequest), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/requests — list current user's requests.
     */
    #[Route('', name: 'api_requests_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $requests = $this->requestRepository->findBy(
            ['user' => $user],
            ['filedAt' => 'DESC'],
        );

        return $this->json(array_map([$this, 'serializeRequest'], $requests));
    }

    /**
     * GET /api/requests/{id} — request detail with approval timeline.
     */
    #[Route('/{id}', name: 'api_requests_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $excRequest = $this->requestRepository->find($id);
        if ($excRequest === null) {
            return $this->json(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        // Employees can only see their own requests
        if ($user->getRole() === 'ROLE_EMPLOYEE' && $excRequest->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeRequest($excRequest));
    }

    /**
     * POST /api/requests/{id}/withdraw — withdraw before first approver acts.
     */
    #[Route('/{id}/withdraw', name: 'api_requests_withdraw', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function withdraw(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $excRequest = $this->requestRepository->find($id);
        if ($excRequest === null) {
            return $this->json(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->workflowService->withdraw($excRequest, $user);
            return $this->json(['message' => 'Request withdrawn']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/requests/{id}/reassign — request reassignment.
     */
    #[Route('/{id}/reassign', name: 'api_requests_reassign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reassign(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $excRequest = $this->requestRepository->find($id);
        if ($excRequest === null) {
            return $this->json(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newApproverId = $data['newApproverId'] ?? null;
        $reason = $data['reason'] ?? '';

        if (!$newApproverId) {
            return $this->json(['error' => 'newApproverId is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find current pending step
        $currentStep = $this->stepRepository->findOneBy([
            'exceptionRequest' => $excRequest,
            'stepNumber' => $excRequest->getStepNumber(),
            'status' => 'PENDING',
        ]);

        if ($currentStep === null) {
            return $this->json(['error' => 'No pending step to reassign'], Response::HTTP_BAD_REQUEST);
        }

        $newApprover = $this->requestRepository->getEntityManager()->getRepository(User::class)->find($newApproverId);
        if ($newApprover === null) {
            return $this->json(['error' => 'New approver not found'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->workflowService->reassign($currentStep, $newApprover, $user, $reason);
            return $this->json(['message' => 'Reassigned successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeRequest(ExceptionRequest $req): array
    {
        $steps = $this->stepRepository->findBy(
            ['exceptionRequest' => $req],
            ['stepNumber' => 'ASC'],
        );

        $stepsData = array_map(function ($step) {
            return [
                'id' => $step->getId(),
                'stepNumber' => $step->getStepNumber(),
                'approverName' => $step->getApprover()->getFirstName() . ' ' . $step->getApprover()->getLastName(),
                'approverRole' => $step->getApprover()->getRole(),
                'status' => $step->getStatus(),
                'slaDeadline' => $step->getSlaDeadline()?->format(\DateTimeInterface::ATOM),
                'remainingMinutes' => $this->slaService->getRemainingMinutes($step),
                'actedAt' => $step->getActedAt()?->format(\DateTimeInterface::ATOM),
                'escalatedAt' => $step->getEscalatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $steps);

        return [
            'id' => $req->getId(),
            'requestType' => $req->getRequestType(),
            'startDate' => $req->getStartDate()->format('Y-m-d'),
            'endDate' => $req->getEndDate()->format('Y-m-d'),
            'startTime' => $req->getStartTime()?->format('H:i'),
            'endTime' => $req->getEndTime()?->format('H:i'),
            'reason' => $req->getReason(),
            'status' => $req->getStatus(),
            'stepNumber' => $req->getStepNumber(),
            'filedAt' => $req->getFiledAt()->format(\DateTimeInterface::ATOM),
            'steps' => $stepsData,
        ];
    }
}
