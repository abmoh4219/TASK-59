<?php

namespace App\Entity;

use App\Repository\PunchEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PunchEventRepository::class)]
#[ORM\Table(name: 'punch_event', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_punch_event', columns: ['user_id', 'event_date', 'event_time', 'event_type']),
])]
class PunchEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $eventDate;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $eventTime;

    #[ORM\Column(type: 'string', length: 10)]
    private string $eventType;

    #[ORM\Column(type: 'string', length: 10)]
    private string $source;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct()
    {
        $this->eventDate = new \DateTimeImmutable();
        $this->eventTime = new \DateTimeImmutable();
        $this->importedAt = new \DateTimeImmutable();
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

    public function getEventDate(): \DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): self
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    public function getEventTime(): \DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function setEventTime(\DateTimeImmutable $eventTime): self
    {
        $this->eventTime = $eventTime;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;
        return $this;
    }
}
