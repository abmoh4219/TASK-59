<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Enforces CSRF token validation for all state-mutating HTTP methods.
 *
 * The CSRF token is issued by AuthenticationSuccessHandler on login and
 * must be sent by the client in the X-CSRF-Token request header for every
 * POST, PUT, PATCH, and DELETE request (except the exempted paths below).
 */
#[AsEventListener(event: RequestEvent::class, priority: 10)]
class CsrfListener
{
    /** Paths that are exempt from CSRF verification. */
    private const EXEMPT_PATHS = [
        '/api/auth/login',
        '/api/auth/csrf-token',
        '/api/health',
    ];

    /** HTTP methods that do NOT mutate state and therefore need no CSRF check. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only enforce on the main request, not on sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $method  = strtoupper($request->getMethod());

        // Safe methods are always allowed without a CSRF token
        if (in_array($method, self::SAFE_METHODS, true)) {
            return;
        }

        // Paths that must remain publicly accessible without a CSRF token
        $path = $request->getPathInfo();
        foreach (self::EXEMPT_PATHS as $exemptPath) {
            if ($path === $exemptPath) {
                return;
            }
        }

        // --- Real CSRF validation ---
        $headerToken  = $request->headers->get('X-CSRF-Token');
        $sessionToken = $request->getSession()->get('csrf_token');

        // Reject when either value is absent or when they do not match.
        // hash_equals() provides a timing-safe comparison to prevent timing attacks.
        if (
            $headerToken === null
            || $sessionToken === null
            || !hash_equals((string) $sessionToken, (string) $headerToken)
        ) {
            $response = new JsonResponse(
                ['error' => 'CSRF token invalid or missing'],
                Response::HTTP_FORBIDDEN,
            );

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
