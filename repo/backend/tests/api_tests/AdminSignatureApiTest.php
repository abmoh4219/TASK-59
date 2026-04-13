<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration coverage for ApiSignatureListener against real /api/admin/*
 * state-mutating endpoints. Complements the unit tests in
 * tests/unit_tests/ApiSignatureAuthenticatorTest.php by exercising the request
 * layer (security firewall + event listener + controller wiring).
 */
class AdminSignatureApiTest extends WebTestCase
{
    /**
     * Authenticate as admin and return [client, csrfToken]. The CSRF token
     * doubles as the session-bound HMAC key understood by
     * ApiSignatureAuthenticator::validate() for browser clients.
     *
     * @return array{0: KernelBrowser, 1: string}
     */
    private function loginAdmin(): array
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin', 'password' => 'Admin@WFOps2024!'])
        );

        $this->assertResponseStatusCodeSame(200, 'Admin login must succeed');
        $data = json_decode($client->getResponse()->getContent(), true) ?? [];
        $csrfToken = (string) ($data['csrfToken'] ?? '');
        $this->assertNotEmpty($csrfToken, 'Login response must include csrfToken');

        return [$client, $csrfToken];
    }

    /**
     * Compute HMAC-SHA256 signature using the same scheme the browser client
     * uses: key = session CSRF token, payload = METHOD+path+timestamp+sha256(body).
     *
     * @return array{signature: string, timestamp: string}
     */
    private function sign(string $method, string $path, string $body, string $key): array
    {
        $timestamp = (string) time();
        $bodyHash = hash('sha256', $body);
        $payload = $method . $path . $timestamp . $bodyHash;
        return [
            'signature' => hash_hmac('sha256', $payload, $key),
            'timestamp' => $timestamp,
        ];
    }

    public function testAdminWriteWithoutSignatureIsRejected(): void
    {
        [$client, $csrfToken] = $this->loginAdmin();

        $body = json_encode([
            'username' => 'sig-test-' . uniqid(),
            'email' => 'sigtest@example.invalid',
            'password' => 'TempPass@2024!',
            'firstName' => 'Sig',
            'lastName' => 'Test',
            'role' => 'ROLE_EMPLOYEE',
        ]);

        $client->request(
            'POST',
            '/api/admin/users',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            $body,
        );

        $this->assertSame(
            401,
            $client->getResponse()->getStatusCode(),
            'POST /api/admin/users without signature headers must be rejected by ApiSignatureListener'
        );
    }

    public function testAdminWriteWithMalformedSignatureIsRejected(): void
    {
        [$client, $csrfToken] = $this->loginAdmin();

        $body = json_encode([
            'username' => 'sig-test-' . uniqid(),
            'email' => 'sigtest@example.invalid',
            'password' => 'TempPass@2024!',
            'firstName' => 'Sig',
            'lastName' => 'Test',
            'role' => 'ROLE_EMPLOYEE',
        ]);

        $client->request(
            'POST',
            '/api/admin/users',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
                'HTTP_X-API-SIGNATURE' => 'not-a-real-hex-signature',
                'HTTP_X-TIMESTAMP' => (string) time(),
            ],
            $body,
        );

        $this->assertSame(
            401,
            $client->getResponse()->getStatusCode(),
            'Malformed signature must be rejected (not hex / wrong length)'
        );
    }

    public function testAdminWriteWithInvalidSignatureIsRejected(): void
    {
        [$client, $csrfToken] = $this->loginAdmin();

        $body = json_encode([
            'username' => 'sig-test-' . uniqid(),
            'email' => 'sigtest@example.invalid',
            'password' => 'TempPass@2024!',
            'firstName' => 'Sig',
            'lastName' => 'Test',
            'role' => 'ROLE_EMPLOYEE',
        ]);

        // Sign with a WRONG key to prove the server rejects forged signatures.
        $sig = $this->sign('POST', '/api/admin/users', $body, 'not-the-session-key');

        $client->request(
            'POST',
            '/api/admin/users',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
                'HTTP_X-API-SIGNATURE' => $sig['signature'],
                'HTTP_X-TIMESTAMP' => $sig['timestamp'],
            ],
            $body,
        );

        $this->assertSame(
            401,
            $client->getResponse()->getStatusCode(),
            'Signature computed with wrong key must be rejected'
        );
    }

    public function testAdminWriteWithValidSignatureSucceeds(): void
    {
        [$client, $csrfToken] = $this->loginAdmin();

        $suffix = uniqid('', true);
        $body = json_encode([
            'username' => 'sig-test-' . $suffix,
            'email' => "sigtest+$suffix@example.invalid",
            'password' => 'TempPass@2024!',
            'firstName' => 'Sig',
            'lastName' => 'Test',
            'role' => 'ROLE_EMPLOYEE',
        ]);

        $sig = $this->sign('POST', '/api/admin/users', $body, $csrfToken);

        $client->request(
            'POST',
            '/api/admin/users',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
                'HTTP_X-API-SIGNATURE' => $sig['signature'],
                'HTTP_X-TIMESTAMP' => $sig['timestamp'],
            ],
            $body,
        );

        $status = $client->getResponse()->getStatusCode();
        $this->assertSame(
            201,
            $status,
            'Valid signature (keyed by session CSRF token) must allow the admin write through to the controller'
        );
    }

    public function testAdminReadEndpointsDoNotRequireSignature(): void
    {
        [$client] = $this->loginAdmin();

        // GET is a read — listener should NOT enforce HMAC signatures on it.
        $client->request('GET', '/api/admin/users');

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Admin GET reads must remain accessible via standard session auth'
        );
    }
}
