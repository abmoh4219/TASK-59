<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiSignatureAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Enforces ApiSignatureAuthenticator on privileged state-mutating routes.
 *
 * Privileged action matrix (Prompt: "signatures required for privileged
 * actions"). GET reads remain session+CSRF only; writes additionally require
 * an HMAC signature bound either to APP_SIGNING_KEY (machine clients) or the
 * user's session CSRF token (browser).
 *
 * Protected surfaces:
 *   - /api/admin/*                         — all administrative writes
 *   - /api/approvals/{id}/approve|reject|reassign — approval decisions
 *   - /api/requests/{id}/reassign          — requester reassignment
 *   - /api/work-orders/{id}/status         — dispatch/state transitions
 *
 * Non-privileged writes (ordinary request creation, attendance punches,
 * booking CRUD, work-order creation) remain CSRF-only — signature is required
 * only where the action changes other users' workflows or system state.
 */
#[AsEventListener(event: RequestEvent::class, priority: 5)]
class ApiSignatureListener
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** @var array<int, string> regex patterns for privileged paths */
    private const PROTECTED_PATTERNS = [
        '#^/api/admin(/|$)#',
        '#^/api/approvals/\d+/(approve|reject|reassign)$#',
        '#^/api/requests/\d+/reassign$#',
        '#^/api/work-orders/\d+/status$#',
    ];

    public function __construct(
        private readonly ApiSignatureAuthenticator $authenticator,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return;
        }

        $matched = false;
        foreach (self::PROTECTED_PATTERNS as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return;
        }

        $response = $this->authenticator->validate($request);
        if ($response !== null) {
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
