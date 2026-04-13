<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $username;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'string', length: 30)]
    private string $role;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $phoneEncrypted = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $backupApproverId = null;

    /**
     * Requester→supervisor linkage. Every ROLE_EMPLOYEE / non-supervisory
     * user should reference their own supervisor's user ID. Approval step 1
     * is routed to this specific supervisor rather than a global-first
     * lookup, enforcing real ownership semantics.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $supervisorId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isOut = false;

    #[ORM\Column(type: 'integer')]
    private int $failedLoginCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * Timestamp set when the user themselves requested data deletion.
     * An admin subsequently runs the retention-safe anonymization
     * (AdminController::deleteUserData) which sets $deletedAt. Keeping the
     * two columns separate preserves the audit trail of "requested" vs
     * "executed" deletion.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletionRequestedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $deletionRequestReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getRoles(): array
    {
        return [$this->role];
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPhoneEncrypted(): ?string
    {
        return $this->phoneEncrypted;
    }

    public function setPhoneEncrypted(?string $phoneEncrypted): self
    {
        $this->phoneEncrypted = $phoneEncrypted;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getBackupApproverId(): ?int
    {
        return $this->backupApproverId;
    }

    public function setBackupApproverId(?int $backupApproverId): self
    {
        $this->backupApproverId = $backupApproverId;
        return $this;
    }

    public function getSupervisorId(): ?int
    {
        return $this->supervisorId;
    }

    public function setSupervisorId(?int $supervisorId): self
    {
        $this->supervisorId = $supervisorId;
        return $this;
    }

    public function isOut(): bool
    {
        return $this->isOut;
    }

    public function setIsOut(bool $isOut): self
    {
        $this->isOut = $isOut;
        return $this;
    }

    public function getFailedLoginCount(): int
    {
        return $this->failedLoginCount;
    }

    public function setFailedLoginCount(int $failedLoginCount): self
    {
        $this->failedLoginCount = $failedLoginCount;
        return $this;
    }

    public function getLockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeImmutable $lockedUntil): self
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getDeletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    public function setDeletionRequestedAt(?\DateTimeImmutable $value): self
    {
        $this->deletionRequestedAt = $value;
        return $this;
    }

    public function getDeletionRequestReason(): ?string
    {
        return $this->deletionRequestReason;
    }

    public function setDeletionRequestReason(?string $value): self
    {
        $this->deletionRequestReason = $value;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function eraseCredentials(): void
    {
        // No plaintext credentials stored
    }
}
