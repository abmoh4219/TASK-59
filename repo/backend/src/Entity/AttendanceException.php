<?php

namespace App\Entity;

use App\Repository\AttendanceExceptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceExceptionRepository::class)]
#[ORM\Table(name: 'attendance_exception')]
class AttendanceException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AttendanceRecord::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AttendanceRecord $attendanceRecord;

    #[ORM\Column(type: 'string', length: 50)]
    private string $exceptionType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $detectedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $resolvedBy = null;

    public function __construct()
    {
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttendanceRecord(): AttendanceRecord
    {
        return $this->attendanceRecord;
    }

    public function setAttendanceRecord(AttendanceRecord $attendanceRecord): self
    {
        $this->attendanceRecord = $attendanceRecord;
        return $this;
    }

    public function getExceptionType(): string
    {
        return $this->exceptionType;
    }

    public function setExceptionType(string $exceptionType): self
    {
        $this->exceptionType = $exceptionType;
        return $this;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTimeImmutable $detectedAt): self
    {
        $this->detectedAt = $detectedAt;
        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): self
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }
}
