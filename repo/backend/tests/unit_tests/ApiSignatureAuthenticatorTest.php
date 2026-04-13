<?php

namespace App\Tests\UnitTests;

use App\Security\ApiSignatureAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiSignatureAuthenticatorTest extends TestCase
{
    private const KEY = 'test-signing-key';

    private function authenticator(): ApiSignatureAuthenticator
    {
        return new ApiSignatureAuthenticator(
            self::KEY,
            $this->createMock(EntityManagerInterface::class),
        );
    }

    private function buildSignedRequest(string $method, string $path, string $body, string $key = self::KEY, ?string $signature = null, ?string $timestamp = null): Request
    {
        $timestamp = $timestamp ?? (string) time();
        $bodyHash = hash('sha256', $body);
        $payload = $method . $path . $timestamp . $bodyHash;
        $signature = $signature ?? hash_hmac('sha256', $payload, $key);

        $request = Request::create($path, $method, [], [], [], [], $body);
        $request->headers->set('X-Api-Signature', $signature);
        $request->headers->set('X-Timestamp', $timestamp);
        return $request;
    }

    public function testMissingSignatureRejected(): void
    {
        $request = Request::create('/api/admin/users', 'POST', [], [], [], [], '{}');
        $response = $this->authenticator()->validate($request);
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMalformedSignatureRejected(): void
    {
        $request = $this->buildSignedRequest('POST', '/api/admin/users', '{}', signature: 'not-hex!!');
        $response = $this->authenticator()->validate($request);
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testInvalidSignatureRejected(): void
    {
        $request = $this->buildSignedRequest('POST', '/api/admin/users', '{}', key: 'wrong-key');
        $response = $this->authenticator()->validate($request);
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testExpiredTimestampRejected(): void
    {
        $oldTs = (string) (time() - 3600);
        $request = $this->buildSignedRequest('POST', '/api/admin/users', '{}', timestamp: $oldTs);
        $response = $this->authenticator()->validate($request);
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testValidSignatureAccepted(): void
    {
        $request = $this->buildSignedRequest('POST', '/api/admin/users', '{"role":"ROLE_EMPLOYEE"}');
        $response = $this->authenticator()->validate($request);
        $this->assertNull($response, 'Valid signature should pass through');
    }
}
