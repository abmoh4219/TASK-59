<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\FailedLoginAttempt;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tracks failed login attempts and enforces temporary account lock-outs.
 *
 * NOTE: AuditService is intentionally NOT injected here to avoid a circular
 * dependency (AuditService -> EntityManager -> … -> AnomalyDetectionService).
 * Lock-out events are recorded solely through the FailedLoginAttempt table.
 */
class AnomalyDetectionService
{
    public function __construct(
        private readonly EntityManagerInterface       $entityManager,
        private readonly FailedLoginAttemptRepository $failedLoginAttemptRepository,
        private readonly UserRepository               $userRepository,
    ) {}

    /**
     * Persists a failed login attempt and locks the account when the threshold is reached.
     *
     * Lock policy: >= 5 attempts in the last 15 minutes → lock for 15 minutes.
     */
    public function recordFailedLogin(string $username, string $ip): void
    {
        // Persist the new failed-attempt record
        $attempt = new FailedLoginAttempt();
        $attempt->setUsername($username);
        $attempt->setIpAddress($ip);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        // Count recent attempts for this username (including the one just persisted)
        $recentCount = $this->failedLoginAttemptRepository->countRecentAttempts($username, 15);

        if ($recentCount >= 5) {
            $user = $this->userRepository->findActiveByUsername($username);

            if ($user !== null) {
                $lockedUntil = new \DateTimeImmutable('+15 minutes');
                $user->setLockedUntil($lockedUntil);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * Returns true when the user's account is currently locked out.
     */
    public function isLockedOut(string $username): bool
    {
        $user = $this->userRepository->findActiveByUsername($username);

        if ($user === null) {
            return false;
        }

        $lockedUntil = $user->getLockedUntil();

        if ($lockedUntil === null) {
            return false;
        }

        return $lockedUntil > new \DateTimeImmutable();
    }
}
