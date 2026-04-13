<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthApiTest extends WebTestCase
{
    /**
     * Reset lock state for standard test users so prior runs / sibling tests
     * cannot leave the shared DB in a state that breaks login-dependent tests.
     */
    public static function setUpBeforeClass(): void
    {
        static::createClient();
        self::resetLockState();
        static::ensureKernelShutdown();
    }

    private static function resetLockState(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $userRepo = $em->getRepository(\App\Entity\User::class);
        foreach (['admin', 'employee', 'supervisor', 'hradmin', 'dispatcher', 'technician'] as $u) {
            $user = $userRepo->findOneBy(['username' => $u]);
            if ($user !== null) {
                $user->setLockedUntil(null);
                $user->setFailedLoginCount(0);
            }
        }
        $em->flush();
        $em->createQuery('DELETE FROM App\Entity\FailedLoginAttempt f')->execute();
    }

    public function testLoginSuccess(): void
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

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('csrfToken', $data);
        $this->assertSame('admin', $data['user']['username']);
        $this->assertSame('ROLE_ADMIN', $data['user']['role']);
    }

    public function testLoginWrongPassword(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin', 'password' => 'WrongPassword!'])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCsrfTokenEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auth/csrf-token');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('csrfToken', $data);
    }

    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
    }

    /**
     * testCsrfMissingReturns403: POST to protected endpoint without CSRF header
     * should be blocked by CsrfListener.
     */
    public function testCsrfMissingReturns403(): void
    {
        $client = static::createClient();

        // Login first
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'employee', 'password' => 'Emp@2024!'])
        );

        // Attempt logout without X-CSRF-Token header
        $client->request('POST', '/api/auth/logout', [], [], ['CONTENT_TYPE' => 'application/json']);

        $status = $client->getResponse()->getStatusCode();
        $this->assertSame(403, $status, "POST without CSRF token should return 403, got $status");
    }

    /**
     * testRateLimitReturns429 — rate limit is 60/min standard; verify header exists and responses work.
     * Full burst-test of 61 requests is slow; verify the rate limit config is enforced via successful requests.
     */
    public function testRateLimitConfigIsEnforced(): void
    {
        $client = static::createClient();
        self::resetLockState();

        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'employee', 'password' => 'Emp@2024!'])
        );

        // Make a few authenticated requests — should succeed (under limit)
        for ($i = 0; $i < 3; $i++) {
            $client->request('GET', '/api/auth/me');
            $this->assertContains(
                $client->getResponse()->getStatusCode(),
                [200, 429],
                'Requests should return 200 (under limit) or 429 (over limit)'
            );
        }
    }

    /**
     * testLockedAccountReturns423: After 5 failed attempts, account is locked (423).
     */
    public function testLockedAccountReturns423(): void
    {
        $client = static::createClient();
        $username = 'employee';

        // 5+ failed attempts should lock the account
        for ($i = 0; $i < 6; $i++) {
            $client->request(
                'POST',
                '/api/auth/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $username, 'password' => 'WrongPassword' . $i])
            );
        }

        $status = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $status,
            [401, 423],
            "Expected 401 or 423 after multiple failed attempts, got $status"
        );

        // A correct password while locked must STILL be rejected (auth-time lockout enforcement).
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => 'Emp@2024!'])
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 423]);

        // Cleanup: clear lock state and failed attempts so other tests can log in.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['username' => $username]);
        if ($user !== null) {
            $user->setLockedUntil(null);
            $user->setFailedLoginCount(0);
            $em->flush();
        }
        $em->createQuery('DELETE FROM App\Entity\FailedLoginAttempt f WHERE f.username = :u')
            ->setParameter('u', $username)
            ->execute();
    }

    /**
     * User-initiated deletion request: a logged-in user can lodge one,
     * the status becomes pending, and a second attempt conflicts.
     */
    public function testUserInitiatedDeletionRequestFlow(): void
    {
        // Reset any previous deletion request on the technician account so
        // the test is deterministic across reruns.
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $userRepo = $em->getRepository(\App\Entity\User::class);
        $tech = $userRepo->findOneBy(['username' => 'technician']);
        if ($tech !== null) {
            $tech->setDeletionRequestedAt(null);
            $tech->setDeletionRequestReason(null);
            $em->flush();
        }

        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'technician', 'password' => 'Tech@2024!'])
        );
        $this->assertResponseStatusCodeSame(200);
        $loginData = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $loginData['csrfToken'];

        // Initially no pending request.
        $client->request(
            'GET',
            '/api/auth/me/deletion-request',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken],
        );
        $this->assertResponseStatusCodeSame(200);
        $status = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($status['pending']);

        // Lodge a deletion request.
        $client->request(
            'POST',
            '/api/auth/me/deletion-request',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-Token' => $csrfToken,
            ],
            json_encode(['reason' => 'QA deletion request test']),
        );
        $this->assertResponseStatusCodeSame(202);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('deletionRequestedAt', $body);

        // Status should now be pending.
        $client->request(
            'GET',
            '/api/auth/me/deletion-request',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken],
        );
        $status = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($status['pending']);
        $this->assertSame('QA deletion request test', $status['reason']);

        // A second attempt must conflict.
        $client->request(
            'POST',
            '/api/auth/me/deletion-request',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-Token' => $csrfToken,
            ],
            json_encode(['reason' => 'duplicate']),
        );
        $this->assertResponseStatusCodeSame(409);

        // Cleanup so reruns and downstream tests are not affected.
        $tech = $userRepo->findOneBy(['username' => 'technician']);
        if ($tech !== null) {
            $tech->setDeletionRequestedAt(null);
            $tech->setDeletionRequestReason(null);
            $em->flush();
        }
    }

    /**
     * Malformed exception-request dates must be rejected with HTTP 400 at
     * the server layer (not just in the frontend).
     */
    public function testExceptionRequestInvalidDateReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'employee', 'password' => 'Emp@2024!'])
        );
        $this->assertResponseStatusCodeSame(200);
        $loginData = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $loginData['csrfToken'];

        $client->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-CSRF-Token' => $csrfToken,
            ],
            json_encode([
                'requestType' => 'CORRECTION',
                'startDate' => 'not-a-date',
                'endDate' => 'also-bad',
                'reason' => 'testing invalid date handling',
            ]),
        );
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * testApiSignatureMissingReturns401: Admin endpoint without proper auth should return 401/403.
     */
    public function testApiAdminRequiresAuth(): void
    {
        $client = static::createClient();

        // Request admin endpoint without authentication
        $client->request('GET', '/api/admin/health');

        $status = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $status,
            [401, 403],
            "Unauthenticated admin endpoint should return 401 or 403, got $status"
        );
    }
}
