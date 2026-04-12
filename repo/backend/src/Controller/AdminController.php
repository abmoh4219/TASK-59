<?php

namespace App\Controller;

use App\Service\AuditService;
use App\Service\RateLimitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * GET /api/admin/health — admin health check.
     * Protected by ROLE_ADMIN via security.yaml access_control.
     */
    #[Route('/health', name: 'api_admin_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'role' => 'ROLE_ADMIN',
        ]);
    }
}
