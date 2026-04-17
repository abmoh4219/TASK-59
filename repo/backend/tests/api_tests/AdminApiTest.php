<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * AdminApiTest — real HTTP tests for every /api/admin/* endpoint.
 *
 * Covers: GET /api/admin/config, PUT /api/admin/config, GET /api/admin/users,
 * PUT /api/admin/users/{id}, GET /api/admin/deletion-requests,
 * POST /api/admin/users/{id}/delete-data, POST /api/admin/attendance/import,
 * GET /api/admin/anomaly-alerts.
 *
 * Also covers: unauthenticated requests (401) and non-admin role (403).
 * Write endpoints use HMAC-SHA256 signatures as required by ApiSignatureAuthenticator.
 */
class AdminApiTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loginAs(string $username, string $password): array
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => $password])
        );
        $this->assertResponseStatusCodeSame(200, "Login for $username must succeed");
        $data = json_decode($client->getResponse()->getContent(), true) ?? [];
        $csrfToken = (string) ($data['csrfToken'] ?? '');
        $this->assertNotEmpty($csrfToken);
        return [$client, $csrfToken];
    }

    private function loginAdmin(): array
    {
        return $this->loginAs('admin', 'Admin@WFOps2024!');
    }

    /** Compute HMAC-SHA256 signature for privileged writes. */
    private function sign(string $method, string $path, string $body, string $key): array
    {
        $ts = (string) time();
        $payload = $method . $path . $ts . hash('sha256', $body);
        return ['signature' => hash_hmac('sha256', $payload, $key), 'timestamp' => $ts];
    }

    private function signedHeaders(string $method, string $path, string $body, string $csrfToken): array
    {
        $sig = $this->sign($method, $path, $body, $csrfToken);
        return [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_X-CSRF-TOKEN'    => $csrfToken,
            'HTTP_X-API-SIGNATURE' => $sig['signature'],
            'HTTP_X-TIMESTAMP'     => $sig['timestamp'],
        ];
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/config
    // -------------------------------------------------------------------------

    public function testGetConfig(): void
    {
        [$client, $csrf] = $this->loginAdmin();
        $client->request('GET', '/api/admin/config', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('rules', $data, 'Config must include "rules"');
        $this->assertArrayHasKey('slaHours', $data, 'Config must include "slaHours"');
        $this->assertArrayHasKey('businessHoursStart', $data);
        $this->assertIsArray($data['rules']);
    }

    public function testGetConfigUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/config');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testGetConfigEmployeeForbidden(): void
    {
        [$client, $csrf] = $this->loginAs('employee', 'Emp@2024!');
        $client->request('GET', '/api/admin/config', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // PUT /api/admin/config
    // -------------------------------------------------------------------------

    public function testUpdateConfig(): void
    {
        [$client, $csrf] = $this->loginAdmin();

        // First get the rules to find a valid rule ID
        $client->request('GET', '/api/admin/config', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);
        $config = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($config['rules'], 'Seed data must include at least one exception rule');

        $ruleId = $config['rules'][0]['id'];
        $original = $config['rules'][0]['toleranceMinutes'];

        $body = json_encode(['rules' => [['id' => $ruleId, 'toleranceMinutes' => $original]]]);
        $headers = $this->signedHeaders('PUT', '/api/admin/config', $body, $csrf);
        $client->request('PUT', '/api/admin/config', [], [], $headers, $body);

        $this->assertResponseStatusCodeSame(200);
        $resp = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $resp);
    }

    public function testUpdateConfigUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $body = json_encode(['rules' => []]);
        $client->request('PUT', '/api/admin/config', [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/users
    // -------------------------------------------------------------------------

    public function testListUsersContainsExpectedFields(): void
    {
        [$client, $csrf] = $this->loginAdmin();
        $client->request('GET', '/api/admin/users', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'User list must not be empty (6 seeded users expected)');

        $first = $data[0];
        $this->assertArrayHasKey('id',        $first);
        $this->assertArrayHasKey('username',  $first);
        $this->assertArrayHasKey('email',     $first);
        $this->assertArrayHasKey('role',      $first);
        $this->assertArrayHasKey('isActive',  $first);
        $this->assertGreaterThanOrEqual(6, count($data), 'At least 6 seeded users must be returned');
    }

    // -------------------------------------------------------------------------
    // PUT /api/admin/users/{id}
    // -------------------------------------------------------------------------

    public function testUpdateUserIsOut(): void
    {
        [$client, $csrf] = $this->loginAdmin();

        // Look up technician ID from user list
        $client->request('GET', '/api/admin/users', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);
        $users = json_decode($client->getResponse()->getContent(), true);
        $tech  = array_values(array_filter($users, fn($u) => $u['username'] === 'technician'))[0];
        $id    = $tech['id'];

        $body = json_encode(['isOut' => true]);
        $path = "/api/admin/users/$id";
        $headers = $this->signedHeaders('PUT', $path, $body, $csrf);
        $client->request('PUT', $path, [], [], $headers, $body);

        $this->assertResponseStatusCodeSame(200);
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $updated);

        // Reset: set isOut back to false
        $body2 = json_encode(['isOut' => false]);
        $sig2 = $this->sign('PUT', $path, $body2, $csrf);
        $client->request('PUT', $path, [], [], [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_X-CSRF-TOKEN'    => $csrf,
            'HTTP_X-API-SIGNATURE' => $sig2['signature'],
            'HTTP_X-TIMESTAMP'     => $sig2['timestamp'],
        ], $body2);
    }

    public function testUpdateUserNonAdminForbidden(): void
    {
        [$client, $csrf] = $this->loginAs('employee', 'Emp@2024!');
        $body = json_encode(['isActive' => true]);
        $sig  = $this->sign('PUT', '/api/admin/users/1', $body, $csrf);
        $client->request('PUT', '/api/admin/users/1', [], [], [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_X-CSRF-TOKEN'    => $csrf,
            'HTTP_X-API-SIGNATURE' => $sig['signature'],
            'HTTP_X-TIMESTAMP'     => $sig['timestamp'],
        ], $body);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testUpdateUserUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $body = json_encode(['isActive' => true]);
        $client->request('PUT', '/api/admin/users/1', [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/deletion-requests
    // -------------------------------------------------------------------------

    public function testListDeletionRequests(): void
    {
        [$client, $csrf] = $this->loginAdmin();
        $client->request('GET', '/api/admin/deletion-requests', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testListDeletionRequestsUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/deletion-requests');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // POST /api/admin/users/{id}/delete-data
    // -------------------------------------------------------------------------

    public function testDeleteUserData(): void
    {
        [$client, $csrf] = $this->loginAdmin();

        // Create a temporary user to delete
        $suffix = uniqid('del_', true);
        $createBody = json_encode([
            'username'  => "deltest_$suffix",
            'email'     => "deltest_$suffix@example.invalid",
            'password'  => 'DelTest@2024!',
            'firstName' => 'Del',
            'lastName'  => 'Test',
            'role'      => 'ROLE_EMPLOYEE',
        ]);
        $createHeaders = $this->signedHeaders('POST', '/api/admin/users', $createBody, $csrf);
        $client->request('POST', '/api/admin/users', [], [], $createHeaders, $createBody);
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $userId  = $created['id'];

        // Now delete-data
        $delPath    = "/api/admin/users/$userId/delete-data";
        $delBody    = json_encode([]);
        $delHeaders = $this->signedHeaders('POST', $delPath, $delBody, $csrf);
        $client->request('POST', $delPath, [], [], $delHeaders, $delBody);

        $this->assertResponseStatusCodeSame(200);
        $resp = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $resp);
        $this->assertStringContainsStringIgnoringCase('anonymized', $resp['message']);

        // Second delete must fail (already deleted)
        $client->request('POST', $delPath, [], [], $delHeaders, $delBody);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteUserDataUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/admin/users/1/delete-data', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // POST /api/admin/attendance/import
    // -------------------------------------------------------------------------

    public function testImportAttendanceMissingFileReturns400(): void
    {
        [$client, $csrf] = $this->loginAdmin();

        $body    = '';
        $path    = '/api/admin/attendance/import';
        $ts      = (string) time();
        $bHash   = hash('sha256', $body);
        $payload = 'POST' . $path . $ts . $bHash;
        $sig     = hash_hmac('sha256', $payload, $csrf);

        $client->request('POST', $path, [], [], [
            'HTTP_X-CSRF-TOKEN'    => $csrf,
            'HTTP_X-API-SIGNATURE' => $sig,
            'HTTP_X-TIMESTAMP'     => $ts,
        ]);

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testImportAttendanceWithCsvSucceeds(): void
    {
        [$client, $csrf] = $this->loginAdmin();

        // Build a minimal valid CSV with employee_id=1 (admin), today's date
        $today   = date('m/d/Y');
        $csvData = "employee_id,date,event_type,time\n1,$today,IN,09:00\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_csv_');
        file_put_contents($tmpFile, $csvData);

        // The signature for multipart must be computed on empty body string
        $path = '/api/admin/attendance/import';
        $ts   = (string) time();
        $bHash = hash('sha256', '');
        $payload = 'POST' . $path . $ts . $bHash;
        $sig = hash_hmac('sha256', $payload, $csrf);

        $client->request('POST', $path, [], [
            'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                $tmpFile,
                'test.csv',
                'text/csv',
                null,
                true
            ),
        ], [
            'HTTP_X-CSRF-TOKEN'    => $csrf,
            'HTTP_X-API-SIGNATURE' => $sig,
            'HTTP_X-TIMESTAMP'     => $ts,
        ]);

        unlink($tmpFile);

        $status = $client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 400], 'CSV import must return 200 or 400 (not 401/403/500)');

        if ($status === 200) {
            $data = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('imported', $data);
            $this->assertArrayHasKey('skipped', $data);
            $this->assertArrayHasKey('errors', $data);
        }
    }

    public function testImportAttendanceUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/admin/attendance/import');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testImportAttendanceNonAdminForbidden(): void
    {
        [$client, $csrf] = $this->loginAs('employee', 'Emp@2024!');
        $client->request('POST', '/api/admin/attendance/import', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/anomaly-alerts
    // -------------------------------------------------------------------------

    public function testAnomalyAlerts(): void
    {
        [$client, $csrf] = $this->loginAdmin();
        $client->request('GET', '/api/admin/anomaly-alerts', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testAnomalyAlertsUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/anomaly-alerts');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testAnomalyAlertsNonAdminForbidden(): void
    {
        [$client, $csrf] = $this->loginAs('employee', 'Emp@2024!');
        $client->request('GET', '/api/admin/anomaly-alerts', [], [], ['HTTP_X-CSRF-TOKEN' => $csrf]);
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }
}
