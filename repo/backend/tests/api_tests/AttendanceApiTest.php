<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AttendanceApiTest extends WebTestCase
{
    /**
     * Perform a login request and return [client, csrfToken].
     * The client's cookie jar retains the session cookie automatically.
     */
    private function loginAsEmployee(): array
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

        $this->assertResponseStatusCodeSame(200, 'Employee login must succeed');

        $data = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $data['csrfToken'] ?? '';

        return [$client, $csrfToken];
    }

    public function testGetTodayAttendanceCard(): void
    {
        [$client, $csrfToken] = $this->loginAsEmployee();

        $client->request(
            'GET',
            '/api/attendance/today',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'Response body must be a JSON object');
        $this->assertArrayHasKey('recordDate', $data, 'Response must contain "recordDate"');
        $this->assertArrayHasKey('exceptions',  $data, 'Response must contain "exceptions"');
        $this->assertArrayHasKey('punches',     $data, 'Response must contain "punches"');
    }

    public function testGetHistoryPaginated(): void
    {
        [$client, $csrfToken] = $this->loginAsEmployee();

        $client->request(
            'GET',
            '/api/attendance/history?page=1',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'Response body must be a JSON object');
        $this->assertArrayHasKey('data',  $data, 'Paginated response must contain "data"');
        $this->assertArrayHasKey('total', $data, 'Paginated response must contain "total"');
        $this->assertArrayHasKey('page',  $data, 'Paginated response must contain "page"');
        $this->assertIsArray($data['data'], '"data" field must be an array');
        $this->assertIsInt($data['total'],  '"total" field must be an integer');
        $this->assertSame(1, $data['page'], '"page" field must equal the requested page number');
    }

    public function testHealthEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);
    }
}
