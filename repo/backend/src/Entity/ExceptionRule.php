<?php

namespace App\Entity;

use App\Repository\ExceptionRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExceptionRuleRepository::class)]
#[ORM\Table(name: 'exception_rule')]
class ExceptionRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $ruleType;

    #[ORM\Column(type: 'integer')]
    private int $toleranceMinutes = 5;

    #[ORM\Column(type: 'integer')]
    private int $missedPunchWindowMinutes = 30;

    #[ORM\Column(type: 'integer')]
    private int $filingWindowDays = 7;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updatedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function setRuleType(string $ruleType): self
    {
        $this->ruleType = $ruleType;
        return $this;
    }

    public function getToleranceMinutes(): int
    {
        return $this->toleranceMinutes;
    }

    public function setToleranceMinutes(int $toleranceMinutes): self
    {
        $this->toleranceMinutes = $toleranceMinutes;
        return $this;
    }

    public function getMissedPunchWindowMinutes(): int
    {
        return $this->missedPunchWindowMinutes;
    }

    public function setMissedPunchWindowMinutes(int $missedPunchWindowMinutes): self
    {
        $this->missedPunchWindowMinutes = $missedPunchWindowMinutes;
        return $this;
    }

    public function getFilingWindowDays(): int
    {
        return $this->filingWindowDays;
    }

    public function setFilingWindowDays(int $filingWindowDays): self
    {
        $this->filingWindowDays = $filingWindowDays;
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

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
