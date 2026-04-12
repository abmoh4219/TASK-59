<?php

namespace App\Entity;

use App\Repository\ApprovalStepRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApprovalStepRepository::class)]
#[ORM\Table(name: 'approval_step')]
class ApprovalStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ExceptionRequest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ExceptionRequest $exceptionRequest;

    #[ORM\Column(type: 'integer')]
    private int $stepNumber;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $approver;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $backupApprover = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $slaDeadline = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $escalatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $actedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExceptionRequest(): ExceptionRequest
    {
        return $this->exceptionRequest;
    }

    public function setExceptionRequest(ExceptionRequest $exceptionRequest): self
    {
        $this->exceptionRequest = $exceptionRequest;
        return $this;
    }

    public function getStepNumber(): int
    {
        return $this->stepNumber;
    }

    public function setStepNumber(int $stepNumber): self
    {
        $this->stepNumber = $stepNumber;
        return $this;
    }

    public function getApprover(): User
    {
        return $this->approver;
    }

    public function setApprover(User $approver): self
    {
        $this->approver = $approver;
        return $this;
    }

    public function getBackupApprover(): ?User
    {
        return $this->backupApprover;
    }

    public function setBackupApprover(?User $backupApprover): self
    {
        $this->backupApprover = $backupApprover;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSlaDeadline(): ?\DateTimeImmutable
    {
        return $this->slaDeadline;
    }

    public function setSlaDeadline(?\DateTimeImmutable $slaDeadline): self
    {
        $this->slaDeadline = $slaDeadline;
        return $this;
    }

    public function getEscalatedAt(): ?\DateTimeImmutable
    {
        return $this->escalatedAt;
    }

    public function setEscalatedAt(?\DateTimeImmutable $escalatedAt): self
    {
        $this->escalatedAt = $escalatedAt;
        return $this;
    }

    public function getActedAt(): ?\DateTimeImmutable
    {
        return $this->actedAt;
    }

    public function setActedAt(?\DateTimeImmutable $actedAt): self
    {
        $this->actedAt = $actedAt;
        return $this;
    }
}
