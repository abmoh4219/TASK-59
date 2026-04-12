<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditService;
use App\Service\EncryptionService;
use App\Service\MaskingService;
use App\Service\RateLimitService;
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

        // Tiered phone access: HR_ADMIN and ADMIN get decrypted full value; others get masked.
        $phone = null;
        $phoneEnc = $user->getPhoneEncrypted();
        if ($phoneEnc !== null) {
            // Attempt to decrypt; if value is plaintext (not yet encrypted), use as-is
            try {
                $phone = $this->encryptionService->decrypt($phoneEnc);
            } catch (\Throwable) {
                $phone = $phoneEnc;
            }

            $isHrAdmin = in_array('ROLE_HR_ADMIN', $user->getRoles(), true)
                || in_array('ROLE_ADMIN', $user->getRoles(), true);
            if (!$isHrAdmin) {
                $phone = $this->maskingService->maskPhone($phone);
            }
        }

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
