<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditService;
use App\Service\EncryptionService;
use App\Service\IdentityAccessPolicy;
use App\Service\MaskingService;
use App\Service\RateLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly MaskingService $maskingService,
        private readonly RateLimitService $rateLimitService,
        private readonly EncryptionService $encryptionService,
        private readonly IdentityAccessPolicy $identityPolicy,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * POST /api/auth/login — handled by Symfony json_login authenticator.
     * Success/failure responses are returned by AuthenticationSuccessHandler/FailureHandler.
     * This route definition exists only to satisfy the router.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This will never be reached — json_login intercepts the request
        return $this->json(['error' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * POST /api/auth/logout — invalidate session.
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user !== null) {
            $this->auditService->log($user, 'LOGOUT', 'User', $user->getId(), null, null, $request);
        }

        $request->getSession()->invalidate();

        return $this->json(['message' => 'Logged out successfully']);
    }

    /**
     * GET /api/auth/me — returns current authenticated user data + CSRF token.
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Rate limit check
        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(
                ['error' => 'Rate limit exceeded'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $this->rateLimitService->getRetryAfter()]
            );
        }

        $csrfToken = $request->getSession()->get('csrf_token');
        if ($csrfToken === null) {
            $csrfToken = bin2hex(random_bytes(32));
            $request->getSession()->set('csrf_token', $csrfToken);
        }

        // Identity-data tiering is delegated to IdentityAccessPolicy so that
        // every identity-bearing endpoint applies the same rule. Viewing one's
        // own profile always returns unmasked phone; HR Admin sees unmasked
        // for any subject; all other roles (including System Administrator)
        // receive a masked phone.
        $phone = $this->identityPolicy->resolvePhone($user, $user);

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRole(),
                'phone' => $phone,
                'isActive' => $user->isActive(),
                'isOut' => $user->isOut(),
            ],
            'csrfToken' => $csrfToken,
        ]);
    }

    /**
     * POST /api/auth/me/deletion-request — user-initiated data deletion.
     *
     * Preserves the retention-safe anonymization semantics: this endpoint
     * does NOT delete any data. It records a deletion request on the user's
     * own account (timestamp + optional reason) and writes an audit log
     * entry. An administrator subsequently runs the retention-preserving
     * anonymization via POST /api/admin/users/{id}/delete-data.
     *
     * Authorization: session-authenticated users can only create a deletion
     * request for themselves — there is no path to request deletion of
     * another user's data via this endpoint.
     */
    #[Route('/me/deletion-request', name: 'api_auth_me_deletion_request', methods: ['POST'])]
    public function createDeletionRequest(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->rateLimitService->checkStandardLimit($user->getId())) {
            return $this->json(['error' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if ($user->getDeletionRequestedAt() !== null) {
            return $this->json([
                'error' => 'A deletion request is already pending for this account',
                'deletionRequestedAt' => $user->getDeletionRequestedAt()->format(\DateTimeInterface::ATOM),
            ], Response::HTTP_CONFLICT);
        }
        if ($user->getDeletedAt() !== null) {
            return $this->json(
                ['error' => 'Account data has already been anonymized'],
                Response::HTTP_CONFLICT,
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
        if (strlen($reason) > 2000) {
            return $this->json(['error' => 'reason must be under 2000 characters'], Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable();
        $user->setDeletionRequestedAt($now);
        $user->setDeletionRequestReason($reason !== '' ? $reason : null);
        $this->entityManager->flush();

        $this->auditService->log(
            $user,
            'DELETION_REQUEST',
            'User',
            $user->getId(),
            null,
            ['requestedAt' => $now->format(\DateTimeInterface::ATOM), 'reason' => $reason],
            $request,
        );

        return $this->json([
            'message' => 'Deletion request recorded. An administrator will process retention-safe anonymization.',
            'deletionRequestedAt' => $now->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/auth/me/deletion-request — check the status of the current
     * user's pending deletion request.
     */
    #[Route('/me/deletion-request', name: 'api_auth_me_deletion_request_status', methods: ['GET'])]
    public function getDeletionRequestStatus(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'pending' => $user->getDeletionRequestedAt() !== null && $user->getDeletedAt() === null,
            'requestedAt' => $user->getDeletionRequestedAt()?->format(\DateTimeInterface::ATOM),
            'anonymizedAt' => $user->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            'reason' => $user->getDeletionRequestReason(),
        ]);
    }

    /**
     * GET /api/auth/csrf-token — returns a fresh CSRF token (public endpoint).
     */
    #[Route('/csrf-token', name: 'api_auth_csrf_token', methods: ['GET'])]
    public function csrfToken(Request $request): JsonResponse
    {
        $csrfToken = bin2hex(random_bytes(32));
        $request->getSession()->set('csrf_token', $csrfToken);

        return $this->json(['csrfToken' => $csrfToken]);
    }
}
