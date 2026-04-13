<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks authentication for accounts that are inactive, soft-deleted, or
 * currently locked out — even when the supplied credentials are valid.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->isActive() || $user->getDeletedAt() !== null) {
            throw new CustomUserMessageAccountStatusException('Account is inactive.');
        }

        $lockedUntil = $user->getLockedUntil();
        if ($lockedUntil !== null && $lockedUntil > new \DateTimeImmutable()) {
            throw new CustomUserMessageAccountStatusException(
                'Account locked. Too many failed attempts. Please try again later.',
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
