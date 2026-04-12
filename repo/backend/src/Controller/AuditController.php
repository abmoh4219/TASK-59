<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Service\MaskingService;
use App\Service\RateLimitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AuditController — read-only access to append-only audit log.
 *
 * Accessible to ROLE_ADMIN and ROLE_HR_ADMIN only.
 * NO edit or delete endpoints — audit records are immutable.
 * AuditLogRepository only exposes read methods (findBy, findOneBy, findPaginated).
 */
#[Route('/api/audit')]
class AuditController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly MaskingService $maskingService,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * GET /api/audit/logs?entity=&actor=&from=&to=&page=&limit=
     */
    #[Route('/logs', name: 'api_audit_logs', methods: ['GET'])]
    public function logs(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Only ROLE_ADMIN and ROLE_HR_ADMIN can read audit logs
        $userRoles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $userRoles, true) && !in_array('ROLE_HR_ADMIN', $userRoles, true)) {
            return $this->json(['error' => 'Access denied — audit log is restricted'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        $entityType = $request->query->get('entity');
        $actorUsername = $request->query->get('actor');
        $from = null;
        $to = null;

        if ($fromStr = $request->query->get('from')) {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromStr)?->setTime(0, 0, 0) ?: null;
        }
        if ($toStr = $request->query->get('to')) {
            $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toStr)?->setTime(23, 59, 59) ?: null;
        }

        $logs = $this->auditLogRepository->findPaginated($page, $limit, $entityType, $actorUsername, $from, $to);
        $total = $this->auditLogRepository->countFiltered($entityType, $actorUsername, $from, $to);

        $data = array_map(function (AuditLog $log) {
            return [
                'id' => $log->getId(),
                'actorUsername' => $log->getActorUsername(),
                'actorId' => $log->getActorId(),
                'action' => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'entityId' => $log->getEntityId(),
                'oldValue' => $log->getOldValueMasked(),
                'newValue' => $log->getNewValueMasked(),
                'ipAddress' => $this->maskIpAddress($log->getIpAddress()),
                'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'immutable' => true, // Flag to indicate this record cannot be edited/deleted
            ];
        }, $logs);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'retention' => '7 years',
        ]);
    }

    /**
     * Mask the last octet of an IPv4 address (e.g., 192.168.1.*).
     */
    private function maskIpAddress(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = '*';
            return implode('.', $parts);
        }
        return $ip;
    }
}
