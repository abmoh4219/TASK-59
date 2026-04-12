<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ApprovalApiTest — integration tests for /api/approvals endpoints.
 *
 * Requires a running MySQL test database with seed data.
 * Tests the full approve-step-1 flow: employee creates request,
 * supervisor sees it in the queue and approves it.
 */
class ApprovalApiTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helper: login and return [client, csrfToken]
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

        $this->assertResponseStatusCodeSame(200, "Login failed for: $username");

        $data = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $data['csrfToken'] ?? '';

        return [$client, $csrfToken];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Full happy-path approval flow:
     *  1. Employee creates an exception request.
     *  2. Supervisor fetches their approval queue and finds the step.
     *  3. Supervisor approves step 1 with a comment.
     *  4. Response confirms success.
     */
    public function testSupervisorApproveStep1(): void
    {
        // Step 1 — Employee creates a request
        [$employeeClient, $employeeCsrf] = $this->loginAs('employee', 'Emp@2024!');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $clientKey = 'approve-flow-' . uniqid('', true);

        $employeeClient->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $employeeCsrf,
            ],
            json_encode([
                'requestType' => 'PTO',
                'startDate'   => $today,
                'endDate'     => $today,
                'reason'      => 'Supervisor approval flow test',
                'clientKey'   => $clientKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Employee should be able to create a request');

        $requestData = json_decode($employeeClient->getResponse()->getContent(), true);
        $requestId = $requestData['id'];
        $this->assertNotNull($requestId, 'Created request must have an ID');
        $this->assertNotEmpty($requestData['steps'], 'Request must have at least one approval step');

        // Grab the step ID from the response (step 1)
        $stepId = $requestData['steps'][0]['id'];
        $this->assertNotNull($stepId, 'Step 1 must have an ID');

        // Step 2 — Supervisor fetches their queue
        [$supervisorClient, $supervisorCsrf] = $this->loginAs('supervisor', 'Super@2024!');

        $supervisorClient->request(
            'GET',
            '/api/approvals/queue',
            [],
            [],
            [
                'HTTP_X-CSRF-Token' => $supervisorCsrf,
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $queue = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertIsArray($queue, 'Queue response must be an array');

        // Verify the newly created request's step appears in the queue
        $stepIds = array_column($queue, 'stepId');
        $this->assertContains(
            $stepId,
            $stepIds,
            'The newly created approval step must appear in the supervisor queue',
        );

        // Step 3 — Supervisor approves step 1
        $supervisorClient->request(
            'POST',
            "/api/approvals/{$stepId}/approve",
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $supervisorCsrf,
            ],
            json_encode(['comment' => 'Approved during automated test'])
        );

        $this->assertResponseStatusCodeSame(200, 'Supervisor should be able to approve step 1');

        $approveData = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $approveData);
        $this->assertStringContainsStringIgnoringCase(
            'approved',
            $approveData['message'],
            'Success message should say approved',
        );
    }

    /**
     * Supervisor fetching their approval queue must return HTTP 200 and an array.
     * The queue may be empty if no pending requests exist, but the shape must be correct.
     */
    public function testApprovalQueueReturnsData(): void
    {
        [$supervisorClient, $supervisorCsrf] = $this->loginAs('supervisor', 'Super@2024!');

        $supervisorClient->request(
            'GET',
            '/api/approvals/queue',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $supervisorCsrf]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertIsArray($data, 'Approval queue must return a JSON array');

        // If items exist, each must have the required fields
        foreach ($data as $item) {
            $this->assertArrayHasKey('stepId', $item, 'Queue item must have stepId');
            $this->assertArrayHasKey('requestType', $item, 'Queue item must have requestType');
            $this->assertArrayHasKey('employeeName', $item, 'Queue item must have employeeName');
            $this->assertArrayHasKey('slaDeadline', $item, 'Queue item must have slaDeadline');
            $this->assertArrayHasKey('remainingMinutes', $item, 'Queue item must have remainingMinutes');
            $this->assertArrayHasKey('isOverdue', $item, 'Queue item must have isOverdue flag');
        }
    }

    /**
     * The /api/health endpoint must return HTTP 200 and indicate the API is alive.
     * This test is deliberately lightweight — it verifies routing and container boot.
     */
    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data, 'Health endpoint must return valid JSON');
    }
}
