<?php

namespace App\Entity;

use App\Repository\ShiftScheduleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShiftScheduleRepository::class)]
#[ORM\Table(name: 'shift_schedule')]
class ShiftSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'integer')]
    private int $dayOfWeek;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $shiftStart;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $shiftEnd;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->shiftStart = new \DateTimeImmutable();
        $this->shiftEnd = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getShiftStart(): \DateTimeImmutable
    {
        return $this->shiftStart;
    }

    public function setShiftStart(\DateTimeImmutable $shiftStart): self
    {
        $this->shiftStart = $shiftStart;
        return $this;
    }

    public function getShiftEnd(): \DateTimeImmutable
    {
        return $this->shiftEnd;
    }

    public function setShiftEnd(\DateTimeImmutable $shiftEnd): self
    {
        $this->shiftEnd = $shiftEnd;
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
}
