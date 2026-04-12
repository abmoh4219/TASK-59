<?php

namespace App\Security;

use App\Entity\IdempotencyKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiSignatureAuthenticator — validates HMAC-SHA256 signatures for privileged admin endpoints.
 *
 * For /api/admin/** endpoints:
 * - Reads X-Api-Signature and X-Timestamp headers
 * - Signature = HMAC-SHA256(method + path + timestamp + sha256(body), APP_SIGNING_KEY)
 * - Validates timestamp within ±5 minutes to prevent replay
 * - Checks nonce (via X-Idempotency-Key header) not reused in IdempotencyKey table
 * - Returns 401 if signature invalid, timestamp expired, or nonce reused
 */
class ApiSignatureAuthenticator
{
    private string $signingKey;

    public function __construct(
        string $appSigningKey,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->signingKey = $appSigningKey;
    }

    /**
     * Validate the API signature for a request.
     * Returns null if valid, or a JsonResponse with error if invalid.
     */
    public function validate(Request $request): ?JsonResponse
    {
        $signature = $request->headers->get('X-Api-Signature');
        $timestamp = $request->headers->get('X-Timestamp');
        $nonce = $request->headers->get('X-Idempotency-Key');

        // Signature and timestamp are required for admin endpoints
        if (empty($signature) || empty($timestamp)) {
            return new JsonResponse(
                ['error' => 'API signature and timestamp required for admin endpoints'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Validate timestamp within ±5 minutes
        $requestTime = (int) $timestamp;
        $currentTime = time();
        if (abs($currentTime - $requestTime) > 300) {
            return new JsonResponse(
                ['error' => 'Request timestamp expired (±5 minutes allowed)'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Compute expected signature: HMAC-SHA256(method + path + timestamp + body_hash)
        $bodyHash = hash('sha256', $request->getContent());
        $payload = $request->getMethod() . $request->getPathInfo() . $timestamp . $bodyHash;
        $expectedSignature = hash_hmac('sha256', $payload, $this->signingKey);

        // Timing-safe comparison
        if (!hash_equals($expectedSignature, $signature)) {
            return new JsonResponse(
                ['error' => 'Invalid API signature'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Check nonce not reused (optional — only if nonce header provided)
        if (!empty($nonce)) {
            $existing = $this->entityManager->getRepository(IdempotencyKey::class)
                ->findOneBy(['clientKey' => $nonce]);

            if ($existing !== null) {
                return new JsonResponse(
                    ['error' => 'Nonce already used (replay attack prevention)'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Store nonce to prevent reuse
            $idempotencyKey = new IdempotencyKey();
            $idempotencyKey->setClientKey($nonce);
            $idempotencyKey->setEntityType('api_nonce');
            $idempotencyKey->setEntityId(0);
            $idempotencyKey->setExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $this->entityManager->persist($idempotencyKey);
            $this->entityManager->flush();
        }

        return null; // Signature valid
    }
}
