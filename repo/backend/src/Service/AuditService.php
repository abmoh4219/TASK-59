<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * AuditService — Append-only by design.
 *
 * This service ONLY creates new AuditLog records. It never updates, deletes,
 * or modifies existing audit entries. The AuditLogRepository similarly has
 * NO update or delete methods.
 *
 * All sensitive values are masked via MaskingService before storage.
 * Audit records are retained for 7 years per compliance policy.
 */
class AuditService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MaskingService $maskingService,
    ) {
    }

    /**
     * Log an auditable action. Append-only — this is the ONLY mutation method.
     *
     * @param User|null    $actor     The user performing the action (null for system actions)
     * @param string       $action    Action name (e.g., LOGIN_SUCCESS, CREATE, UPDATE, APPROVE)
     * @param string       $entityType Entity class/table name being acted upon
     * @param int|null     $entityId  ID of the entity being acted upon
     * @param array|null   $oldData   Previous state (will be masked before storage)
     * @param array|null   $newData   New state (will be masked before storage)
     * @param Request|null $request   HTTP request for IP and user-agent extraction
     */
    public function log(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?Request $request = null,
    ): void {
        $auditLog = new AuditLog();

        $auditLog->setActorId($actor?->getId());
        $auditLog->setActorUsername($actor?->getUsername() ?? 'system');
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);

        // Mask sensitive values before persisting — never store raw passwords, tokens, phones
        $auditLog->setOldValueMasked(
            $oldData !== null ? $this->maskingService->maskForLog($oldData) : null
        );
        $auditLog->setNewValueMasked(
            $newData !== null ? $this->maskingService->maskForLog($newData) : null
        );

        // Extract request metadata
        if ($request !== null) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent(substr((string) $request->headers->get('User-Agent', ''), 0, 500));
        }

        // Append-only: persist + flush. No update. No delete. Ever.
        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }
}
