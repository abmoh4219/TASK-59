<?php

namespace App\Service;

use App\Entity\User;

/**
 * IdentityAccessPolicy — single source of truth for identity-data tiering.
 *
 * Prompt rule: "full identity data is visible only to HR Admin".
 * System Administrator (ROLE_ADMIN) and every other role receive masked
 * identity fields regardless of the endpoint that serializes them.
 *
 * All identity-bearing controllers (AuthController::me, AdminController
 * user endpoints, any future profile endpoint) MUST route identity fields
 * through this policy rather than masking ad-hoc.
 */
class IdentityAccessPolicy
{
    public function __construct(
        private readonly EncryptionService $encryptionService,
        private readonly MaskingService $maskingService,
    ) {
    }

    /**
     * Does the given viewer receive unmasked identity data for the subject?
     *
     * Full identity is granted only when the viewer's primary role is
     * ROLE_HR_ADMIN. System Administrator and every other role — including
     * self-view via /api/auth/me — receive masked identity data. This
     * matches the Prompt rule ("full identity is HR-Admin-only") and the
     * existing AuditApi test expectations for /api/auth/me.
     */
    public function canViewFullIdentity(User $viewer, User $subject): bool
    {
        return $viewer->getRole() === 'ROLE_HR_ADMIN';
    }

    /**
     * Resolve the phone field for a given (viewer, subject) pair.
     * Returns null if the subject has no phone on file.
     * Returns the full plaintext phone only when policy allows; otherwise
     * returns the masked representation.
     */
    public function resolvePhone(User $viewer, User $subject): ?string
    {
        $phoneEnc = $subject->getPhoneEncrypted();
        if ($phoneEnc === null) {
            return null;
        }

        try {
            $plain = $this->encryptionService->decrypt($phoneEnc);
        } catch (\Throwable) {
            // Legacy/unencrypted value — still subject to the policy.
            $plain = $phoneEnc;
        }

        if ($this->canViewFullIdentity($viewer, $subject)) {
            return $plain;
        }

        return $this->maskingService->maskPhone($plain);
    }
}
