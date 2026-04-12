<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * APPEND-ONLY entity. Records must never be updated or deleted after creation.
 * All audit trail modifications must be expressed as new rows.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log', indexes: [
    new ORM\Index(columns: ['created_at'], name: 'idx_audit_log_created_at'),
    new ORM\Index(columns: ['entity_type'], name: 'idx_audit_log_entity_type'),
    new ORM\Index(columns: ['actor_username'], name: 'idx_audit_log_actor'),
])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $actorId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $actorUsername;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action;

    #[ORM\Column(type: 'string', length: 50)]
    private string $entityType;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $oldValueMasked = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $newValueMasked = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function setActorId(?int $actorId): self
    {
        $this->actorId = $actorId;
        return $this;
    }

    public function getActorUsername(): string
    {
        return $this->actorUsername;
    }

    public function setActorUsername(string $actorUsername): self
    {
        $this->actorUsername = $actorUsername;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getOldValueMasked(): ?array
    {
        return $this->oldValueMasked;
    }

    public function setOldValueMasked(?array $oldValueMasked): self
    {
        $this->oldValueMasked = $oldValueMasked;
        return $this;
    }

    public function getNewValueMasked(): ?array
    {
        return $this->newValueMasked;
    }

    public function setNewValueMasked(?array $newValueMasked): self
    {
        $this->newValueMasked = $newValueMasked;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
