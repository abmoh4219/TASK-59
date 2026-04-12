<?php

namespace App\Entity;

use App\Repository\ApprovalActionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApprovalActionRepository::class)]
#[ORM\Table(name: 'approval_action')]
class ApprovalAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ApprovalStep::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ApprovalStep $approvalStep;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $actor;

    #[ORM\Column(type: 'string', length: 20)]
    private string $action;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $actedAt;

    public function __construct()
    {
        $this->actedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApprovalStep(): ApprovalStep
    {
        return $this->approvalStep;
    }

    public function setApprovalStep(ApprovalStep $approvalStep): self
    {
        $this->approvalStep = $approvalStep;
        return $this;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function setActor(User $actor): self
    {
        $this->actor = $actor;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getActedAt(): \DateTimeImmutable
    {
        return $this->actedAt;
    }

    public function setActedAt(\DateTimeImmutable $actedAt): self
    {
        $this->actedAt = $actedAt;
        return $this;
    }
}
