<?php

namespace App\Service;

use App\Entity\FileUpload;
use App\Entity\User;
use App\Entity\WorkOrderPhoto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * FileUploadService — validates, hashes, and stores uploaded files.
 *
 * Security rules:
 * - MIME type: only image/jpeg, image/png
 * - Extension: only .jpg, .jpeg, .png
 * - Size: max 10 MB (10485760 bytes)
 * - SHA-256 fingerprint: reject duplicates for the same entity
 * - Rate limit: 10 uploads per minute per user
 * - Storage: /app/uploads/{entityType}/{entityId}/{hash}.{ext}
 */
class FileUploadService
{
    private const MAX_SIZE_BYTES = 10485760; // 10 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const UPLOAD_DIR = '/app/uploads';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RateLimitService $rateLimitService,
        private readonly AuditService $auditService,
        // Wired via services.yaml ($signingKey -> %env(APP_SIGNING_KEY)%)
        // — nullable so unit tests can explicitly pass null.
        private readonly ?string $signingKey = null,
    ) {
    }

    /**
     * Upload and validate a file. Returns the FileUpload entity.
     *
     * @throws \InvalidArgumentException if validation fails
     */
    public function upload(
        UploadedFile $file,
        string $entityType,
        int $entityId,
        User $uploader,
    ): FileUpload {
        // Rate limit check (10 uploads per minute per user)
        if (!$this->rateLimitService->checkUploadLimit($uploader->getId())) {
            throw new \InvalidArgumentException('Upload rate limit exceeded (10 uploads/minute)');
        }

        // Size validation
        $size = $file->getSize();
        if ($size === false || $size > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                'File too large: %d bytes (max %d bytes = 10 MB)',
                $size,
                self::MAX_SIZE_BYTES,
            ));
        }

        // MIME type validation
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid MIME type: %s (allowed: %s)',
                $mimeType,
                implode(', ', self::ALLOWED_MIME_TYPES),
            ));
        }

        // Extension validation
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid extension: .%s (allowed: %s)',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        // Compute SHA-256 hash
        $hash = hash_file('sha256', $file->getPathname());
        if ($hash === false) {
            throw new \InvalidArgumentException('Failed to compute file hash');
        }

        // Duplicate check for this entity
        $existing = $this->entityManager->getRepository(FileUpload::class)->findOneBy([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'sha256Hash' => $hash,
        ]);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Duplicate file (same content already uploaded for this entity)');
        }

        // Store file
        $storageDir = self::UPLOAD_DIR . '/' . $entityType . '/' . $entityId;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $storedFilename = $hash . '.' . $extension;
        $storedPath = $storageDir . '/' . $storedFilename;
        $file->move($storageDir, $storedFilename);

        // Create FileUpload entity
        $fileUpload = new FileUpload();
        $fileUpload->setUploader($uploader);
        $fileUpload->setEntityType($entityType);
        $fileUpload->setEntityId($entityId);
        $fileUpload->setOriginalFilename($file->getClientOriginalName() ?: 'upload.' . $extension);
        $fileUpload->setStoredPath($storedPath);
        $fileUpload->setMimeType($mimeType);
        $fileUpload->setSizeBytes($size);
        $fileUpload->setSha256Hash($hash);

        $this->entityManager->persist($fileUpload);
        $this->entityManager->flush();

        // Audit log
        $this->auditService->log(
            $uploader,
            'FILE_UPLOAD',
            $entityType,
            $entityId,
            null,
            ['filename' => $fileUpload->getOriginalFilename(), 'size' => $size, 'hash' => substr($hash, 0, 16) . '...'],
        );

        return $fileUpload;
    }

    /**
     * Create a WorkOrderPhoto from an uploaded file.
     */
    public function createWorkOrderPhoto(
        UploadedFile $file,
        int $workOrderId,
        User $uploader,
    ): WorkOrderPhoto {
        $fileUpload = $this->upload($file, 'WorkOrder', $workOrderId, $uploader);

        $workOrder = $this->entityManager->getRepository(\App\Entity\WorkOrder::class)->find($workOrderId);

        $photo = new WorkOrderPhoto();
        $photo->setWorkOrder($workOrder);
        $photo->setOriginalFilename($fileUpload->getOriginalFilename());
        $photo->setStoredPath($fileUpload->getStoredPath());
        $photo->setMimeType($fileUpload->getMimeType());
        $photo->setSizeBytes($fileUpload->getSizeBytes());
        $photo->setSha256Hash($fileUpload->getSha256Hash());

        $this->entityManager->persist($photo);
        $this->entityManager->flush();

        return $photo;
    }

    /**
     * Generate a signed URL for serving a file (time-limited).
     * Fails closed: throws if APP_SIGNING_KEY is missing, empty, or clearly
     * a placeholder. No predictable fallback is used.
     *
     * @throws \RuntimeException when signing configuration is invalid
     */
    public function getSignedUrl(int $fileId, int $expirySeconds = 3600): string
    {
        $key = $this->signingKey;
        if ($key === null || $key === '' || strlen($key) < 16) {
            throw new \RuntimeException(
                'APP_SIGNING_KEY is not configured; refusing to sign file URLs with a fallback key',
            );
        }
        // Reject obvious placeholder values that would give attackers a free pass.
        $placeholderPatterns = ['default-key', 'change-me', 'changeme', 'placeholder'];
        foreach ($placeholderPatterns as $bad) {
            if (stripos($key, $bad) !== false) {
                throw new \RuntimeException(
                    'APP_SIGNING_KEY appears to be a placeholder; rotate it before signing file URLs',
                );
            }
        }

        $expiry = time() + $expirySeconds;
        $signature = hash_hmac('sha256', "file:$fileId:$expiry", $key);

        return "/api/files/$fileId?expires=$expiry&signature=$signature";
    }
}
