<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\AuditService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var \App\Entity\User $user */
        $user = $token->getUser();

        // Generate a cryptographically secure CSRF token and store it in the session
        $csrfToken = bin2hex(random_bytes(32));
        $request->getSession()->set('csrf_token', $csrfToken);

        // Write audit log entry for successful login
        $this->auditService->log(
            $user,
            'LOGIN_SUCCESS',
            'User',
            $user->getId(),
            null,
            ['username' => $user->getUsername()],
            $request,
        );

        return new JsonResponse([
            'user' => [
                'id'        => $user->getId(),
                'username'  => $user->getUsername(),
                'email'     => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'role'      => $user->getRole(),
                'isActive'  => $user->isActive(),
                'isOut'     => $user->isOut(),
            ],
            'csrfToken' => $csrfToken,
        ], Response::HTTP_OK);
    }
}
