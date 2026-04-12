<?php

namespace App\Entity;

use App\Repository\WorkOrderPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkOrderPhotoRepository::class)]
class WorkOrderPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkOrder::class)]
    #[ORM\JoinColumn(nullable: false)]
    private WorkOrder $workOrder;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 500)]
    private string $storedPath;

    #[ORM\Column(type: 'string', length: 50)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $sizeBytes;

    #[ORM\Column(type: 'string', length: 64)]
    private string $sha256Hash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkOrder(): WorkOrder
    {
        return $this->workOrder;
    }

    public function setWorkOrder(WorkOrder $workOrder): self
    {
        $this->workOrder = $workOrder;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    public function setStoredPath(string $storedPath): self
    {
        $this->storedPath = $storedPath;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function getSha256Hash(): string
    {
        return $this->sha256Hash;
    }

    public function setSha256Hash(string $sha256Hash): self
    {
        $this->sha256Hash = $sha256Hash;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
