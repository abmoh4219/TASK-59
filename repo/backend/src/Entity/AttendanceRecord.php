<?php

namespace App\Entity;

use App\Repository\AttendanceRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceRecordRepository::class)]
#[ORM\Table(name: 'attendance_record', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_attendance_record', columns: ['user_id', 'record_date']),
])]
class AttendanceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $recordDate;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $firstPunchIn = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastPunchOut = null;

    #[ORM\Column(type: 'integer')]
    private int $totalMinutes = 0;

    #[ORM\Column(type: 'json')]
    private array $exceptions = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $generatedAt;

    public function __construct()
    {
        $this->recordDate = new \DateTimeImmutable();
        $this->generatedAt = new \DateTimeImmutable();
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

    public function getRecordDate(): \DateTimeImmutable
    {
        return $this->recordDate;
    }

    public function setRecordDate(\DateTimeImmutable $recordDate): self
    {
        $this->recordDate = $recordDate;
        return $this;
    }

    public function getFirstPunchIn(): ?\DateTimeImmutable
    {
        return $this->firstPunchIn;
    }

    public function setFirstPunchIn(?\DateTimeImmutable $firstPunchIn): self
    {
        $this->firstPunchIn = $firstPunchIn;
        return $this;
    }

    public function getLastPunchOut(): ?\DateTimeImmutable
    {
        return $this->lastPunchOut;
    }

    public function setLastPunchOut(?\DateTimeImmutable $lastPunchOut): self
    {
        $this->lastPunchOut = $lastPunchOut;
        return $this;
    }

    public function getTotalMinutes(): int
    {
        return $this->totalMinutes;
    }

    public function setTotalMinutes(int $totalMinutes): self
    {
        $this->totalMinutes = $totalMinutes;
        return $this;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function setExceptions(array $exceptions): self
    {
        $this->exceptions = $exceptions;
        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }
}
