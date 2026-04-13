<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ApprovalStepRepository;
use App\Repository\UserRepository;
use App\Service\ApprovalWorkflowService;
use App\Service\RateLimitService;
use App\Service\SlaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/approvals')]
class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly ApprovalStepRepository $stepRepository,
        private readonly UserRepository $userRepository,
        private readonly ApprovalWorkflowService $workflowService,
        private readonly SlaService $slaService,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * GET /api/approvals/queue — pending approval steps for the current approver.
     */
    #[Route('/queue', name: 'api_approvals_queue', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $pendingSteps = $this->stepRepository->findBy([
            'approver' => $user,
            'status' => 'PENDING',
        ]);

        $data = array_map(function ($step) {
            $request = $step->getExceptionRequest();
            $requester = $request->getUser();
            return [
                'stepId' => $step->getId(),
                'stepNumber' => $step->getStepNumber(),
                'requestId' => $request->getId(),
                'requestType' => $request->getRequestType(),
                'employeeName' => $requester->getFirstName() . ' ' . $requester->getLastName(),
                'employeeUsername' => $requester->getUsername(),
                'startDate' => $request->getStartDate()->format('Y-m-d'),
                'endDate' => $request->getEndDate()->format('Y-m-d'),
                'reason' => $request->getReason(),
                'filedAt' => $request->getFiledAt()->format(\DateTimeInterface::ATOM),
                'slaDeadline' => $step->getSlaDeadline()?->format(\DateTimeInterface::ATOM),
                'remainingMinutes' => $this->slaService->getRemainingMinutes($step),
                'isOverdue' => $this->slaService->isOverdue($step),
            ];
        }, $pendingSteps);

        return $this->json($data);
    }

    /**
     * POST /api/approvals/{stepId}/approve
     */
    #[Route('/{stepId}/approve', name: 'api_approvals_approve', methods: ['POST'], requirements: ['stepId' => '\d+'])]
    public function approve(int $stepId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $step = $this->stepRepository->find($stepId);
        if ($step === null) {
            return $this->json(['error' => 'Approval step not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->workflowService->approve($step, $user, $data['comment'] ?? '');
            return $this->json(['message' => 'Approved successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/approvals/{stepId}/reject
     */
    #[Route('/{stepId}/reject', name: 'api_approvals_reject', methods: ['POST'], requirements: ['stepId' => '\d+'])]
    public function reject(int $stepId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $step = $this->stepRepository->find($stepId);
        if ($step === null) {
            return $this->json(['error' => 'Approval step not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->workflowService->reject($step, $user, $data['comment'] ?? '');
            return $this->json(['message' => 'Rejected']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/approvals/{stepId}/reassign
     */
    #[Route('/{stepId}/reassign', name: 'api_approvals_reassign', methods: ['POST'], requirements: ['stepId' => '\d+'])]
    public function reassign(int $stepId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $step = $this->stepRepository->find($stepId);
        if ($step === null) {
            return $this->json(['error' => 'Approval step not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newApproverId = $data['newApproverId'] ?? null;

        if (!$newApproverId) {
            return $this->json(['error' => 'newApproverId required'], Response::HTTP_BAD_REQUEST);
        }

        $newApprover = $this->userRepository->find($newApproverId);
        if ($newApprover === null) {
            return $this->json(['error' => 'New approver not found'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->workflowService->reassign($step, $newApprover, $user, $data['reason'] ?? '');
            return $this->json(['message' => 'Reassigned']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
