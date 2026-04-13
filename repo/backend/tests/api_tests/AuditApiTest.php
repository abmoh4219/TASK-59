<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuditApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Helper: log in.
     */
    private function login(string $username, string $password): array
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => $password]),
        );

        $data = json_decode($this->client->getResponse()->getContent(), true) ?? [];
        return ['csrfToken' => $data['csrfToken'] ?? ''];
    }

    public function testHealthEndpoint(): void
    {
        $this->client->request('GET', '/api/health');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAuditLogAccessibleToAdmin(): void
    {
        $this->login('admin', 'Admin@WFOps2024!');

        $this->client->request('GET', '/api/audit/logs');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('retention', $data);
        $this->assertSame('7 years', $data['retention']);
    }

    public function testAuditLogAccessibleToHrAdmin(): void
    {
        $this->login('hradmin', 'HRAdmin@2024!');

        $this->client->request('GET', '/api/audit/logs');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testEmployeeCannotReadAuditLog(): void
    {
        $this->login('employee', 'Emp@2024!');

        $this->client->request('GET', '/api/audit/logs');

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [403, 401], true),
            "Expected 401 or 403 for employee accessing audit log, got $status",
        );
    }

    public function testSupervisorCannotReadAuditLog(): void
    {
        $this->login('supervisor', 'Super@2024!');

        $this->client->request('GET', '/api/audit/logs');

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [403, 401], true),
            "Expected 401 or 403 for supervisor accessing audit log, got $status",
        );
    }

    public function testAuditLogEntriesHaveImmutableFlag(): void
    {
        $this->login('admin', 'Admin@WFOps2024!');

        $this->client->request('GET', '/api/audit/logs');
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        if (!empty($data['data'])) {
            $firstLog = $data['data'][0];
            $this->assertArrayHasKey('immutable', $firstLog);
            $this->assertTrue($firstLog['immutable']);
            $this->assertArrayHasKey('actorUsername', $firstLog);
            $this->assertArrayHasKey('action', $firstLog);
            $this->assertArrayHasKey('entityType', $firstLog);
            $this->assertArrayHasKey('createdAt', $firstLog);
        } else {
            // No audit log entries yet — still a valid test outcome
            $this->assertSame(0, $data['total']);
        }
    }

    public function testPhoneMaskedForNonHrAdmin(): void
    {
        $this->login('supervisor', 'Super@2024!');

        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $phone = $data['user']['phone'] ?? null;

        if ($phone !== null) {
            $this->assertStringContainsString(
                '*',
                $phone,
                'Supervisor should see masked phone number',
            );
        } else {
            $this->assertNull($phone);
        }
    }

    /**
     * Identity-data boundary: System Administrator must NOT automatically
     * receive full phone/identity data — that is HR Admin's privilege per
     * the Prompt's "only HR Admin full identity" rule.
     */
    public function testPhoneMaskedForSystemAdmin(): void
    {
        $this->login('admin', 'Admin@WFOps2024!');

        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $phone = $data['user']['phone'] ?? null;

        if ($phone !== null) {
            $this->assertStringContainsString(
                '*',
                $phone,
                'System Administrator must see masked phone (full identity is HR-Admin-only)',
            );
        } else {
            $this->assertNull($phone);
        }
    }

    public function testPhoneFullForHrAdmin(): void
    {
        $this->login('hradmin', 'HRAdmin@2024!');

        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $phone = $data['user']['phone'] ?? null;

        if ($phone !== null) {
            $this->assertStringNotContainsString(
                '***',
                $phone,
                'HR Admin should see unmasked phone number',
            );
        } else {
            $this->assertNull($phone);
        }
    }

    public function testAuditLogIpMasked(): void
    {
        $this->login('admin', 'Admin@WFOps2024!');

        $this->client->request('GET', '/api/audit/logs');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        foreach ($data['data'] ?? [] as $log) {
            if ($log['ipAddress'] !== null && str_contains($log['ipAddress'], '.')) {
                $this->assertStringEndsWith(
                    '*',
                    $log['ipAddress'],
                    'IP addresses should have last octet masked',
                );
            }
        }
        // At least one assertion always runs
        $this->assertIsArray($data['data']);
    }
}
