<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\AnomalyDetectionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly AnomalyDetectionService $anomalyDetectionService,
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $body     = json_decode((string) $request->getContent(), true) ?? [];
        $username = (string) ($body['username'] ?? '');
        $ip       = (string) ($request->getClientIp() ?? '');

        // Record the failed attempt; this may trigger an account lock-out
        $this->anomalyDetectionService->recordFailedLogin($username, $ip);

        // After recording, check whether the account is now (or was already) locked
        if ($username !== '' && $this->anomalyDetectionService->isLockedOut($username)) {
            return new JsonResponse(
                ['error' => 'Account locked. Too many failed attempts. Please try again later.'],
                Response::HTTP_LOCKED, // 423
            );
        }

        return new JsonResponse(
            ['error' => 'Invalid credentials.'],
            Response::HTTP_UNAUTHORIZED, // 401
        );
    }
}
